<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use PdfGate\Laravel\PdfGateManager;
use Statamic\Contracts\Entries\Entry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders a Statamic entry to a PDF with the PDFGate generate endpoint.
 */
final class EntryPdfGenerator
{
    public function __construct(
        private readonly PdfGateManager $pdfGate,
        private readonly Config $config
    ) {
    }

    /**
     * Whether the entry can be exported with the configured source.
     *
     * URL rendering needs a public URL and a published entry. HTML rendering
     * only needs a template, so drafts work too.
     */
    public function canExport(Entry $entry): bool
    {
        if ($entry->absoluteUrl() === null) {
            return false;
        }

        return $this->source() === 'html' || $entry->status() === 'published';
    }

    /**
     * File name used for downloads, for example "about-us.pdf".
     */
    public function filename(Entry $entry): string
    {
        $slug = (string) ($entry->slug() ?? $entry->id());

        return ($slug !== '' ? $slug : 'entry') . '.pdf';
    }

    /**
     * Generates the PDF and returns the PDFGate document ID.
     */
    public function generate(Entry $entry, ?Request $request = null): string
    {
        return $this->pdfGate->generatePdf($this->buildRequest($entry, $request))->getId();
    }

    /**
     * The generate request sent to PDFGate for an entry.
     *
     * @return array<string,mixed>
     */
    public function buildRequest(Entry $entry, ?Request $request = null): array
    {
        $options = $this->config->get('pdfgate-statamic.entries.options', []);
        $payload = is_array($options) ? $options : [];

        unset($payload['url'], $payload['html']);

        if ($this->source() === 'html') {
            $payload['html'] = $this->renderHtml($entry, $request ?? request());
        } else {
            $payload['url'] = (string) $entry->absoluteUrl();
        }

        return $payload;
    }

    /**
     * Streams the entry as a PDF download.
     */
    public function download(Entry $entry, ?Request $request = null): StreamedResponse
    {
        $documentId = $this->generate($entry, $request);
        $stream = $this->stream($documentId);

        return response()->streamDownload(
            function () use ($stream, $documentId): void {
                fpassthru($stream);
                fclose($stream);
                $this->cleanup($documentId);
            },
            $this->filename($entry),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Opens the generated file for reading.
     *
     * @return resource
     */
    public function stream(string $documentId)
    {
        return $this->pdfGate->getFile($documentId);
    }

    /**
     * Deletes generated documents from the PDFGate account when configured to.
     */
    public function cleanup(string ...$documentIds): void
    {
        if ($this->config->get('pdfgate-statamic.delete_remote_documents', true) !== true) {
            return;
        }

        foreach ($documentIds as $documentId) {
            try {
                $this->pdfGate->deleteDocument($documentId);
            } catch (\Throwable) {
                // The file was already delivered; a failed cleanup is not an error for the user.
            }
        }
    }

    private function source(): string
    {
        return $this->config->get('pdfgate-statamic.entries.source') === 'html' ? 'html' : 'url';
    }

    /**
     * Renders the entry's template and points relative URLs at the site.
     */
    private function renderHtml(Entry $entry, Request $request): string
    {
        $html = (string) $entry->toResponse($request)->getContent();

        if (preg_match('/<base\s/i', $html) === 1) {
            return $html;
        }

        $base = '<base href="' . htmlspecialchars(rtrim((string) $this->config->get('app.url'), '/') . '/', ENT_QUOTES) . '">';
        $withBase = preg_replace('/<head([^>]*)>/i', '<head$1>' . $base, $html, 1);

        return $withBase ?? $html;
    }
}
