<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Actions;

use PdfGate\Dto\PdfGateDocumentMetadata;
use PdfGate\Laravel\Facades\PdfGate;
use PdfGate\Statamic\Text;

/**
 * Stamps a text watermark on every page of PDF assets.
 */
final class WatermarkPdf extends PdfAssetAction
{
    /** @var string */
    protected $icon = 'text-formatting-shadow-text';

    public static function title(): string
    {
        return Text::get('Watermark PDF');
    }

    public function confirmationText(): string
    {
        return Text::get('Stamp this text on every page of the PDF.|Stamp this text on every page of the :count PDFs.');
    }

    protected function operationFields(): array
    {
        return [
            'text' => [
                'display' => Text::get('Text'),
                'type' => 'text',
                'default' => 'CONFIDENTIAL',
                'validate' => 'required',
            ],
            'font_size' => [
                'display' => Text::get('Font size'),
                'type' => 'integer',
                'default' => 48,
                'validate' => 'required|integer|min:1|max:500',
                'width' => 33,
            ],
            'opacity' => [
                'display' => Text::get('Opacity (%)'),
                'type' => 'integer',
                'default' => 30,
                'validate' => 'required|integer|min:1|max:100',
                'width' => 33,
            ],
            'rotate' => [
                'display' => Text::get('Rotation (degrees)'),
                'type' => 'integer',
                'default' => 45,
                'validate' => 'required|integer|min:-360|max:360',
                'width' => 33,
            ],
            'font_color' => [
                'display' => Text::get('Color'),
                'type' => 'color',
                'default' => '#000000',
            ],
        ];
    }

    protected function operation(string $documentId, array $values): PdfGateDocumentMetadata
    {
        $request = [
            'documentId' => $documentId,
            'type' => 'text',
            'text' => (string) ($values['text'] ?? ''),
            'fontSize' => (int) ($values['font_size'] ?? 48),
            'opacity' => ((int) ($values['opacity'] ?? 30)) / 100,
            'rotate' => (int) ($values['rotate'] ?? 45),
        ];

        $color = trim((string) ($values['font_color'] ?? ''));
        if ($color !== '') {
            $request['fontColor'] = $color;
        }

        return PdfGate::watermarkPdf($request);
    }

    protected function suffix(): string
    {
        return 'watermarked';
    }

    protected function successMessage(int $count): string
    {
        return trans_choice('PDF watermarked.|:count PDFs watermarked.', $count);
    }
}
