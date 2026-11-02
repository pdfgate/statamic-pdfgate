<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Actions;

use PdfGate\Dto\PdfGateDocumentMetadata;
use PdfGate\Laravel\Facades\PdfGate;
use PdfGate\Statamic\Text;

/**
 * Reduces the file size of PDF assets.
 */
final class CompressPdf extends PdfAssetAction
{
    /** @var string */
    protected $icon = 'shrink';

    public static function title(): string
    {
        return Text::get('Compress PDF');
    }

    public function confirmationText(): string
    {
        return Text::get('Compress this PDF with PDFGate?|Compress these :count PDFs with PDFGate?');
    }

    protected function operationFields(): array
    {
        return [];
    }

    protected function operation(string $documentId, array $values): PdfGateDocumentMetadata
    {
        return PdfGate::compressPdf([
            'documentId' => $documentId,
            'linearize' => (bool) config('pdfgate-statamic.assets.compress.linearize', false),
        ]);
    }

    protected function suffix(): string
    {
        return 'compressed';
    }

    protected function successMessage(int $count): string
    {
        return trans_choice('PDF compressed.|:count PDFs compressed.', $count);
    }
}
