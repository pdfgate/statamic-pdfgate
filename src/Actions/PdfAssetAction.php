<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Actions;

use Illuminate\Validation\ValidationException;
use PdfGate\Dto\PdfGateDocumentMetadata;
use PdfGate\Exception\PdfGateException;
use PdfGate\Statamic\ApiErrorMessage;
use PdfGate\Statamic\AssetProcessor;
use PdfGate\Statamic\Text;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;

/**
 * Shared behaviour of the asset actions: PDF-only visibility, the result mode field and the run loop.
 */
abstract class PdfAssetAction extends Action
{
    /**
     * @param mixed $item
     */
    public function visibleTo($item): bool
    {
        $key = config('pdfgate.api_key');

        return $item instanceof Asset
            && is_string($key) && trim($key) !== ''
            && strtolower($item->extension()) === 'pdf';
    }

    /**
     * @param mixed $user
     * @param mixed $item
     */
    public function authorize($user, $item): bool
    {
        return $user->can('edit', $item);
    }

    public function buttonText(): string
    {
        return static::title();
    }

    /**
     * @param \Illuminate\Support\Collection<int,Asset> $items
     * @param array<string,mixed> $values
     */
    public function run($items, $values): string
    {
        $processor = app(AssetProcessor::class);
        $mode = ($values['mode'] ?? AssetProcessor::MODE_REPLACE) === AssetProcessor::MODE_COPY
            ? AssetProcessor::MODE_COPY
            : AssetProcessor::MODE_REPLACE;
        $count = 0;

        foreach ($items as $asset) {
            try {
                $processor->process(
                    $asset,
                    $mode,
                    $this->suffix(),
                    fn (string $documentId): PdfGateDocumentMetadata => $this->operation($documentId, $values)
                );
            } catch (PdfGateException $exception) {
                throw ValidationException::withMessages([
                    'mode' => $asset->basename() . ': ' . ApiErrorMessage::from($exception),
                ]);
            }

            $count++;
        }

        return $this->successMessage($count);
    }

    /**
     * Fields specific to the operation, shown above the result mode field.
     *
     * @return array<string,array<string,mixed>>
     */
    abstract protected function operationFields(): array;

    /**
     * Calls the PDFGate operation for an uploaded document.
     *
     * @param array<string,mixed> $values Submitted dialog values.
     */
    abstract protected function operation(string $documentId, array $values): PdfGateDocumentMetadata;

    /**
     * File name suffix used in copy mode, for example "compressed".
     */
    abstract protected function suffix(): string;

    abstract protected function successMessage(int $count): string;

    /**
     * @return array<string,array<string,mixed>>
     */
    protected function fieldItems(): array
    {
        return $this->operationFields() + [
            'mode' => [
                'display' => Text::get('Result'),
                'type' => 'select',
                'options' => [
                    AssetProcessor::MODE_REPLACE => Text::get('Replace the original file'),
                    AssetProcessor::MODE_COPY => Text::get('Save as a new file next to the original'),
                ],
                'default' => AssetProcessor::MODE_REPLACE,
                'validate' => 'required',
            ],
        ];
    }
}
