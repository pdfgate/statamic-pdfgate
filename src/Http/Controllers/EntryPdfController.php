<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use PdfGate\Statamic\EntryPdfGenerator;
use Statamic\Facades\Entry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the signed download link produced by the {{ pdfgate:download_url }} tag.
 */
final class EntryPdfController
{
    public function __construct(private readonly EntryPdfGenerator $generator)
    {
    }

    public function __invoke(Request $request, string $entry): StreamedResponse
    {
        $found = Entry::find($entry);

        if ($found === null || !$this->generator->canExport($found)) {
            abort(404);
        }

        return $this->generator->download($found, $request);
    }
}
