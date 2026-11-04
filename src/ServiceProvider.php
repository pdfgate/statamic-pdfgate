<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
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
        // The file is not named web.php on purpose: Statamic auto-loads routes/web.php
        // whatever the config says, and this route must be optional.
        $routesCached = $this->app instanceof CachesRoutes && $this->app->routesAreCached();

        if (config('pdfgate-statamic.entries.download_route', true) && !$routesCached) {
            Route::middleware('web')->group(__DIR__ . '/../routes/download.php');
        }
    }
}
