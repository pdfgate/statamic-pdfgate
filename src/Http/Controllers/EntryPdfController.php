<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PdfGate\Exception\PdfGateException;
use PdfGate\Statamic\ApiErrorMessage;
use PdfGate\Statamic\EntryPdfGenerator;
use Statamic\Auth\Protect\Protection;
use Statamic\Facades\Entry;

/**
 * Serves the signed download link produced by the {{ pdfgate:download_url }} tag.
 */
final class EntryPdfController
{
    public function __construct(private readonly EntryPdfGenerator $generator)
    {
    }

    public function __invoke(Request $request, string $entry): Response
    {
        $found = Entry::find($entry);

        if ($found === null || !$this->generator->canServePublicly($found)) {
            abort(404);
        }

        // Apply the entry's protection scheme (login, password, IP) exactly as a page view would,
        // on every request, so a signed link never bypasses it and cache hits are checked too.
        app(Protection::class)->setData($found)->protect();

        try {
            $bytes = $this->generator->cachedBytes($found, $request);
        } catch (PdfGateException $exception) {
            // The upstream render failed; report it as a gateway error with PDFGate's message instead of a stack trace.
            abort(502, ApiErrorMessage::from($exception));
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->generator->filename($found) . '"',
        ]);
    }
}
