<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests\Unit;

use Illuminate\Support\Facades\URL;
use PdfGate\Statamic\Tags\PdfGateTags;
use PdfGate\Statamic\Tests\TestCase;

final class DownloadRouteTest extends TestCase
{
    public function testTagReturnsASignedUrlForTheCurrentEntry(): void
    {
        $entry = $this->makeEntry('about');

        $tag = new PdfGateTags();
        $tag->setContext(['id' => $entry->id()]);
        $tag->setParameters([]);

        $url = $tag->downloadUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('/pdfgate/entries/' . $entry->id() . '?signature=', $url);
        $this->assertSame($url, $tag->index());
    }

    public function testTagAcceptsAnEntryParameterAndReturnsNullWithoutAnId(): void
    {
        $entry = $this->makeEntry('about');

        $tag = new PdfGateTags();
        $tag->setContext([]);
        $tag->setParameters(['entry' => $entry->id()]);
        $this->assertStringContainsString($entry->id(), (string) $tag->downloadUrl());

        $tag->setParameters([]);
        $this->assertNull($tag->downloadUrl());
    }

    public function testSignedLinkDownloadsThePdf(): void
    {
        $entry = $this->makeEntry('about');
        $this->queueDocument('doc_1');
        $this->queueFile();
        $this->queueNoContent();

        $response = $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => $entry->id()]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame(self::PDF, $response->streamedContent());
    }

    public function testUnsignedAndUnknownLinksAreRefused(): void
    {
        $entry = $this->makeEntry('about');

        $this->get('/pdfgate/entries/' . $entry->id())->assertForbidden();
        $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => 'missing']))->assertNotFound();
        $this->assertCount(0, $this->transport->requests);
    }

    public function testDraftsAreNotServedInUrlMode(): void
    {
        $draft = $this->makeEntry('draft', false);

        $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => $draft->id()]))->assertNotFound();
    }
}
