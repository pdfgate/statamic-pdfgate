<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

use PdfGate\Statamic\Actions\CompressPdf;
use PdfGate\Statamic\Actions\ExportEntryAsPdf;
use PdfGate\Statamic\Actions\ProtectPdf;
use PdfGate\Statamic\Actions\WatermarkPdf;
use PdfGate\Statamic\Tags\PdfGateTags;
use Statamic\Providers\AddonServiceProvider;

/**
 * Registers the Control Panel actions, the Antlers tag and the download route.
 */
final class ServiceProvider extends AddonServiceProvider
{
    protected $actions = [
        ExportEntryAsPdf::class,
        CompressPdf::class,
        WatermarkPdf::class,
        ProtectPdf::class,
    ];

    protected $tags = [
        PdfGateTags::class,
    ];

    public function bootAddon(): void
    {
        if (config('pdfgate-statamic.entries.download_route', true)) {
            $this->registerWebRoutes(__DIR__ . '/../routes/web.php');
        }
    }
}
