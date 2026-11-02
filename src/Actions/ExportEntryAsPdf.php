<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Actions;

use Illuminate\Validation\ValidationException;
use PdfGate\Exception\PdfGateException;
use PdfGate\Statamic\ApiErrorMessage;
use PdfGate\Statamic\EntryPdfGenerator;
use PdfGate\Statamic\Text;
use Statamic\Actions\Action;
use Statamic\Actions\Concerns\MakesZips;
use Statamic\Contracts\Entries\Entry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads one entry as a PDF, or several entries as a zip of PDFs.
 */
final class ExportEntryAsPdf extends Action
{
    use MakesZips;

    /** @var string */
    protected $icon = 'pdf';

    /** @var bool */
    protected $confirm = false;

    public static function title(): string
    {
        return Text::get('Export as PDF');
    }

    /**
     * @param mixed $item
     */
    public function visibleTo($item): bool
    {
        return $item instanceof Entry && $this->apiKeyIsConfigured() && $this->generator()->canExport($item);
    }

    /**
     * @param mixed $user
     * @param mixed $item
     */
    public function authorize($user, $item): bool
    {
        return $user->can('view', $item);
    }

    /**
     * @param \Illuminate\Support\Collection<int,Entry> $items
     * @param array<string,mixed> $values
     */
    public function download($items, $values): StreamedResponse
    {
        $generator = $this->generator();

        try {
            if ($items->count() === 1) {
                return $generator->download($items->first());
            }

            $documentIds = [];
            $files = [];

            foreach ($items as $entry) {
                $documentId = $generator->generate($entry);
                $documentIds[] = $documentId;
                $files[$this->uniqueName($files, $generator->filename($entry))] = $generator->stream($documentId);
            }
        } catch (PdfGateException $exception) {
            throw ValidationException::withMessages(['pdfgate' => ApiErrorMessage::from($exception)]);
        }

        return tap(
            $this->makeZipResponse('entries.zip', collect($files)),
            fn () => $generator->cleanup(...$documentIds)
        );
    }

    /**
     * @param array<string,mixed> $files
     */
    private function uniqueName(array $files, string $filename): string
    {
        if (!isset($files[$filename])) {
            return $filename;
        }

        $base = substr($filename, 0, -4);

        for ($i = 2; ; $i++) {
            $candidate = $base . '-' . $i . '.pdf';
            if (!isset($files[$candidate])) {
                return $candidate;
            }
        }
    }

    /**
     * Without a key the client cannot be built, so the action stays out of the menu instead of breaking the listing.
     */
    private function apiKeyIsConfigured(): bool
    {
        $key = config('pdfgate.api_key');

        return is_string($key) && trim($key) !== '';
    }

    private function generator(): EntryPdfGenerator
    {
        return app(EntryPdfGenerator::class);
    }
}
