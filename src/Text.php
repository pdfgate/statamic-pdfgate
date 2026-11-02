<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

/**
 * Translates Control Panel strings and always hands back a string.
 */
final class Text
{
    private function __construct()
    {
    }

    /**
     * @param array<string,string|int> $replace
     */
    public static function get(string $line, array $replace = []): string
    {
        $translated = __($line, $replace);

        return is_string($translated) ? $translated : $line;
    }
}
