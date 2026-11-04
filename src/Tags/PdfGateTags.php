<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tags;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PdfGate\Statamic\ApiKey;
use Statamic\Tags\Tags;

/**
 * {{ pdfgate:download_url }} gives a signed link that downloads the current entry as a PDF.
 */
final class PdfGateTags extends Tags
{
    /** @var string */
    protected static $handle = 'pdfgate';

    /**
     * {{ pdfgate }} is the same as {{ pdfgate:download_url }}.
     */
    public function index(): ?string
    {
        return $this->downloadUrl();
    }

    /**
     * Signed URL of the PDF download route for an entry.
     *
     * Parameters: "entry" (ID, defaults to the current entry). Prints nothing when no usable API key is set.
     */
    public function downloadUrl(): ?string
    {
        $id = $this->params->get('entry') ?? $this->context->value('id');
        $id = is_scalar($id) ? trim((string) $id) : '';

        if ($id === '' || !ApiKey::isUsable() || !Route::has('pdfgate.entries.pdf')) {
            return null;
        }

        return URL::signedRoute('pdfgate.entries.pdf', ['entry' => $id]);
    }
}
