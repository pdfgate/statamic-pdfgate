<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests\Unit;

use Illuminate\Support\Facades\Route;
use PdfGate\Statamic\Tags\PdfGateTags;
use PdfGate\Statamic\Tests\TestCase;

final class DownloadRouteDisabledTest extends TestCase
{
    protected bool $downloadRoute = false;

    public function testTheRouteIsNotRegisteredWhenTurnedOff(): void
    {
        $entry = $this->makeEntry('about');

        $this->assertFalse(Route::has('pdfgate.entries.pdf'));
        $this->get('/pdfgate/entries/' . $entry->id())->assertNotFound();
    }

    public function testTheTagPrintsNothingWhenTheRouteIsOff(): void
    {
        $entry = $this->makeEntry('about');

        $tag = new PdfGateTags();
        $tag->setContext(['id' => $entry->id()]);
        $tag->setParameters([]);

        $this->assertNull($tag->downloadUrl());
    }
}
