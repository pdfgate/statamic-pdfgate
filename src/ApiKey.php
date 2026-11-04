<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

/**
 * Tells whether the configured PDFGate API key can be used to build a client.
 */
final class ApiKey
{
    private function __construct()
    {
    }

    /**
     * True when a key is set and carries a prefix the SDK accepts.
     *
     * The actions stay out of the Control Panel and the tag prints nothing
     * otherwise, so a missing or malformed key never breaks a listing or a page.
     */
    public static function isUsable(): bool
    {
        $key = config('pdfgate.api_key');

        if (!is_string($key)) {
            return false;
        }

        $key = trim($key);

        return str_starts_with($key, 'live_') || str_starts_with($key, 'test_');
    }
}
