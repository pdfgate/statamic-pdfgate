<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

use PdfGate\Exception\ApiException;
use PdfGate\Exception\PdfGateException;

/**
 * Turns a PDFGate exception into one sentence an editor can act on.
 */
final class ApiErrorMessage
{
    private function __construct()
    {
    }

    public static function from(PdfGateException $exception): string
    {
        if (!$exception instanceof ApiException) {
            return $exception->getMessage();
        }

        $body = json_decode($exception->getResponseBody(), true);
        $message = is_array($body) ? ($body['message'] ?? null) : null;

        if (is_array($message)) {
            $message = implode(' ', array_filter($message, 'is_string'));
        }

        if (!is_string($message) || trim($message) === '') {
            return 'PDFGate responded with status ' . $exception->getStatusCode() . '.';
        }

        return 'PDFGate: ' . trim($message);
    }
}
