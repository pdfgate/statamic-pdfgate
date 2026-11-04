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
        $this->assertSame(self::PDF, $response->getContent());
        $this->assertStringContainsString('about.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function testUnsignedAndUnknownLinksAreRefused(): void
    {
        $entry = $this->makeEntry('about');

        $this->get('/pdfgate/entries/' . $entry->id())->assertForbidden();
        $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => 'missing']))->assertNotFound();
        $this->assertCount(0, $this->transport->requests);
    }

    public function testDraftsAreNeverServedPublicly(): void
    {
        $draft = $this->makeEntry('draft', false);

        $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => $draft->id()]))->assertNotFound();

        config()->set('pdfgate-statamic.entries.source', 'html');
        $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => $draft->id()]))->assertNotFound();

        $this->assertCount(0, $this->transport->requests);
    }

    public function testRecentRendersAreReusedForVisitors(): void
    {
        config()->set('pdfgate-statamic.entries.cache_minutes', 30);
        $entry = $this->makeEntry('about');
        $this->queueDocument('doc_1');
        $this->queueFile();
        $this->queueNoContent();

        $url = URL::signedRoute('pdfgate.entries.pdf', ['entry' => $entry->id()]);
        $this->get($url)->assertOk();
        $second = $this->get($url);

        $second->assertOk();
        $this->assertSame(self::PDF, $second->getContent());
        $this->assertCount(3, $this->transport->requests);
    }

    public function testTagPrintsNothingWithoutAUsableKey(): void
    {
        $entry = $this->makeEntry('about');
        config()->set('pdfgate.api_key', null);

        $tag = new PdfGateTags();
        $tag->setContext(['id' => $entry->id()]);
        $tag->setParameters([]);

        $this->assertNull($tag->downloadUrl());
    }

    public function testApiFailuresBecomeAGatewayError(): void
    {
        $entry = $this->makeEntry('about');
        $this->transport->queueJson(['statusCode' => 400, 'message' => 'url: The URL is invalid.'], 400);

        $response = $this->get(URL::signedRoute('pdfgate.entries.pdf', ['entry' => $entry->id()]));

        $response->assertStatus(502);
    }

    public function testProtectedEntriesAreNotServedToGuests(): void
    {
        $entry = $this->makeEntry('members');
        $entry->set('protect', 'logged_in')->save();
        $url = URL::signedRoute('pdfgate.entries.pdf', ['entry' => $entry->id()]);

        $this->get($url)->assertRedirect();

        config()->set('pdfgate-statamic.entries.source', 'html');
        $this->get($url)->assertRedirect();

        $this->assertCount(0, $this->transport->requests);
    }

    public function testProtectedEntriesAreServedToSignedInUsersAndNeverCached(): void
    {
        config()->set('pdfgate-statamic.entries.cache_minutes', 30);
        $entry = $this->makeEntry('members');
        $entry->set('protect', 'logged_in')->save();
        $this->actingAs(\Statamic\Facades\User::make()->id('member')->email('member@example.com'));
        foreach (['doc_1', 'doc_2'] as $id) {
            $this->queueDocument($id);
            $this->queueFile();
            $this->queueNoContent();
        }

        $url = URL::signedRoute('pdfgate.entries.pdf', ['entry' => $entry->id()]);
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertCount(6, $this->transport->requests);
    }

    public function testHtmlRendersAreNeverShared(): void
    {
        config()->set('pdfgate-statamic.entries.cache_minutes', 30);
        config()->set('pdfgate-statamic.entries.source', 'html');
        $entry = $this->makeEntry('about');
        foreach (['doc_1', 'doc_2'] as $id) {
            $this->queueDocument($id);
            $this->queueFile();
            $this->queueNoContent();
        }

        $url = URL::signedRoute('pdfgate.entries.pdf', ['entry' => $entry->id()]);
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertCount(6, $this->transport->requests);
    }
}
