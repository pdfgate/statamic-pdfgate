<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Actions;

use PdfGate\Dto\PdfGateDocumentMetadata;
use PdfGate\Laravel\Facades\PdfGate;
use PdfGate\Statamic\Text;

/**
 * Encrypts PDF assets with a password and optional permission restrictions.
 */
final class ProtectPdf extends PdfAssetAction
{
    /** @var string */
    protected $icon = 'padlock-locked';

    public static function title(): string
    {
        return Text::get('Protect PDF');
    }

    public function confirmationText(): string
    {
        return Text::get('Encrypt the PDF with a password.|Encrypt the :count PDFs with a password.');
    }

    protected function operationFields(): array
    {
        return [
            'user_password' => [
                'display' => Text::get('Password to open'),
                'instructions' => Text::get('Required to open the PDF.'),
                'type' => 'text',
                'input_type' => 'password',
                'validate' => 'required',
                'width' => 50,
            ],
            'owner_password' => [
                'display' => Text::get('Owner password'),
                'instructions' => Text::get('Lifts the restrictions below. Leave empty to use the open password.'),
                'type' => 'text',
                'input_type' => 'password',
                'width' => 50,
            ],
            'disable_print' => [
                'display' => Text::get('Disable printing'),
                'type' => 'toggle',
                'default' => false,
                'width' => 33,
            ],
            'disable_copy' => [
                'display' => Text::get('Disable copying'),
                'type' => 'toggle',
                'default' => false,
                'width' => 33,
            ],
            'disable_editing' => [
                'display' => Text::get('Disable editing'),
                'type' => 'toggle',
                'default' => false,
                'width' => 33,
            ],
        ];
    }

    protected function operation(string $documentId, array $values): PdfGateDocumentMetadata
    {
        $userPassword = (string) ($values['user_password'] ?? '');
        $ownerPassword = (string) ($values['owner_password'] ?? '');

        // AES-256 refuses an empty owner password, so the open password doubles as owner password.
        return PdfGate::protectPdf([
            'documentId' => $documentId,
            'userPassword' => $userPassword,
            'ownerPassword' => $ownerPassword !== '' ? $ownerPassword : $userPassword,
            'disablePrint' => (bool) ($values['disable_print'] ?? false),
            'disableCopy' => (bool) ($values['disable_copy'] ?? false),
            'disableEditing' => (bool) ($values['disable_editing'] ?? false),
        ]);
    }

    protected function suffix(): string
    {
        return 'protected';
    }

    protected function successMessage(int $count): string
    {
        return trans_choice('PDF protected.|:count PDFs protected.', $count);
    }
}
