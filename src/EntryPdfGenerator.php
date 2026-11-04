<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use PdfGate\Laravel\PdfGateManager;
use Statamic\Auth\Protect\Protection;
use Statamic\Contracts\Entries\Entry;
use Statamic\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders a Statamic entry to a PDF with the PDFGate generate endpoint.
 */
final class EntryPdfGenerator
{
    public function __construct(
        private readonly PdfGateManager $pdfGate,
        private readonly Config $config,
        private readonly Cache $cache
    ) {
    }

    /**
     * Whether an editor can export the entry from the Control Panel.
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
     * Whether the entry may be served to visitors through the download route.
     *
     * Drafts are never public, whichever source is configured.
     */
    public function canServePublicly(Entry $entry): bool
    {
        return $entry->status() === 'published' && $this->canExport($entry);
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
        $payload = $this->options();

        if ($this->source() === 'html') {
            $payload['html'] = $this->renderHtml($entry);
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
     * Generates the PDF and returns its bytes.
     */
    public function bytes(Entry $entry, ?Request $request = null): string
    {
        $documentId = $this->generate($entry, $request);
        $stream = $this->stream($documentId);

        try {
            $bytes = (string) stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        $this->cleanup($documentId);

        return $bytes;
    }

    /**
     * Like bytes(), but reuses a recent render of the same entry.
     *
     * Visitors clicking a download link on a popular page would otherwise spend
     * one PDFGate conversion each. The cache key changes when the entry is saved
     * or the render options change, and entries.cache_minutes sets the lifetime.
     *
     * Only URL renders are shared. PDFGate loads the page as an anonymous visitor,
     * so the result is the same for everyone. HTML renders happen inside the
     * visitor's own request, with their session and user, and are never cached.
     * Protected entries are not cached either, matching Statamic's own rule.
     */
    public function cachedBytes(Entry $entry, ?Request $request = null): string
    {
        $minutes = (int) $this->config->get('pdfgate-statamic.entries.cache_minutes', 60);

        if ($minutes <= 0 || $this->source() === 'html' || app(Protection::class)->setData($entry)->scheme() !== null) {
            return $this->bytes($entry, $request);
        }

        $key = 'pdfgate-statamic:entry:' . sha1((string) json_encode([
            $entry->id(),
            $entry->lastModified()?->getTimestamp(),
            $this->source(),
            $this->options(),
        ]));

        return $this->cache->remember($key, $minutes * 60, fn (): string => $this->bytes($entry, $request));
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
     * @return array<string,mixed>
     */
    private function options(): array
    {
        $options = $this->config->get('pdfgate-statamic.entries.options', []);
        $payload = is_array($options) ? $options : [];

        unset($payload['url'], $payload['html']);

        return $payload;
    }

    /**
     * Renders the entry's template and layout and points relative URLs at the site.
     *
     * The view is rendered directly rather than through the entry's HTTP response,
     * which refuses drafts outside Live Preview.
     */
    private function renderHtml(Entry $entry): string
    {
        $html = app(View::class)
            ->template($entry->template())
            ->layout($entry->layout())
            ->cascadeContent($entry)
            ->render();

        if (preg_match('/<base\s/i', $html) === 1) {
            return $html;
        }

        $base = '<base href="' . htmlspecialchars(rtrim((string) $this->config->get('app.url'), '/') . '/', ENT_QUOTES) . '">';
        $withBase = preg_replace('/<head([^>]*)>/i', '<head$1>' . $base, $html, 1);

        return $withBase ?? $html;
    }
}
