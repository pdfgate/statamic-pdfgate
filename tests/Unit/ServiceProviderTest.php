<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests\Unit;

use Illuminate\Support\Facades\Route;
use PdfGate\Statamic\Actions\CompressPdf;
use PdfGate\Statamic\Actions\ExportEntryAsPdf;
use PdfGate\Statamic\Actions\ProtectPdf;
use PdfGate\Statamic\Actions\WatermarkPdf;
use PdfGate\Statamic\Tests\TestCase;
use Statamic\Actions\Action;
use Statamic\Facades\Action as Actions;
use Statamic\Facades\User;

final class ServiceProviderTest extends TestCase
{
    public function testConfigIsMerged(): void
    {
        $this->assertSame('url', config('pdfgate-statamic.entries.source'));
        $this->assertTrue(config('pdfgate-statamic.delete_remote_documents'));
        $this->assertSame('a4', config('pdfgate-statamic.entries.options.pageSizeType'));
    }

    public function testDownloadRouteIsRegistered(): void
    {
        $this->assertTrue(Route::has('pdfgate.entries.pdf'));
    }

    public function testActionsAreRegisteredForEntriesAndPdfAssets(): void
    {
        $this->actingAs(User::make()->makeSuper());

        $entryActions = Actions::for($this->makeEntry(), ['view' => 'list'])->map(fn (Action $action) => $action::class)->all();
        $this->assertContains(ExportEntryAsPdf::class, $entryActions);

        $assetActions = Actions::for($this->makePdfAsset(), ['view' => 'list'])->map(fn (Action $action) => $action::class)->all();
        $this->assertContains(CompressPdf::class, $assetActions);
        $this->assertContains(WatermarkPdf::class, $assetActions);
        $this->assertContains(ProtectPdf::class, $assetActions);
    }

    public function testAssetActionsAreHiddenForOtherFileTypes(): void
    {
        $this->actingAs(User::make()->makeSuper());

        $this->makePdfAsset();
        \Illuminate\Support\Facades\Storage::disk('assets')->put('docs/photo.jpg', 'not a pdf');
        $image = \Statamic\Facades\Asset::make()->container('assets')->path('docs/photo.jpg');
        $image->save();

        $actions = Actions::for($image, ['view' => 'list'])->map(fn (Action $action) => $action::class)->all();

        $this->assertNotContains(CompressPdf::class, $actions);
        $this->assertNotContains(WatermarkPdf::class, $actions);
        $this->assertNotContains(ProtectPdf::class, $actions);
    }

    public function testActionsAreHiddenWithoutAnApiKey(): void
    {
        $this->actingAs(User::make()->makeSuper());
        config()->set('pdfgate.api_key', null);

        $entryActions = Actions::for($this->makeEntry(), ['view' => 'list'])->map(fn (Action $action) => $action::class)->all();
        $assetActions = Actions::for($this->makePdfAsset(), ['view' => 'list'])->map(fn (Action $action) => $action::class)->all();

        $this->assertNotContains(ExportEntryAsPdf::class, $entryActions);
        $this->assertNotContains(CompressPdf::class, $assetActions);
    }
}
