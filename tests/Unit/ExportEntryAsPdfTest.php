<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests\Unit;

use Illuminate\Validation\ValidationException;
use PdfGate\Statamic\Actions\ExportEntryAsPdf;
use PdfGate\Statamic\EntryPdfGenerator;
use PdfGate\Statamic\Tests\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportEntryAsPdfTest extends TestCase
{
    public function testBuildsAUrlRequestWithTheConfiguredOptions(): void
    {
        $entry = $this->makeEntry('about');

        $request = $this->app->make(EntryPdfGenerator::class)->buildRequest($entry);

        $this->assertSame('http://localhost/about', $request['url']);
        $this->assertSame('a4', $request['pageSizeType']);
        $this->assertTrue($request['printBackground']);
        $this->assertArrayNotHasKey('html', $request);
    }

    public function testDraftsAreHiddenInUrlModeAndVisibleInHtmlMode(): void
    {
        $draft = $this->makeEntry('draft', false);
        $action = new ExportEntryAsPdf();

        $this->assertFalse($action->visibleTo($draft));

        config()->set('pdfgate-statamic.entries.source', 'html');

        $this->assertTrue($action->visibleTo($draft));
    }

    public function testDownloadStreamsThePdfAndDeletesTheRemoteDocument(): void
    {
        $entry = $this->makeEntry('about');
        $this->queueDocument('doc_1');
        $this->queueFile();
        $this->queueNoContent();

        $response = (new ExportEntryAsPdf())->download(collect([$entry]), []);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('about.pdf', (string) $response->headers->get('content-disposition'));

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $this->assertSame(self::PDF, $body);

        $methods = array_map(fn ($request) => $request->getMethod() . ' ' . $request->getUrl(), $this->transport->requests);
        $this->assertSame('POST https://api-sandbox.pdfgate.com/v1/generate/pdf', $methods[0]);
        $this->assertSame('http://localhost/about', $this->transport->requests[0]->getJsonBody()['url'] ?? null);
        $this->assertStringContainsString('/file/doc_1', $methods[1]);
        $this->assertStringStartsWith('DELETE ', $methods[2]);
        $this->assertStringContainsString('doc_1', $methods[2]);
    }

    public function testRemoteDocumentsAreKeptWhenCleanupIsOff(): void
    {
        config()->set('pdfgate-statamic.delete_remote_documents', false);
        $entry = $this->makeEntry('about');
        $this->queueDocument('doc_1');
        $this->queueFile();

        $response = (new ExportEntryAsPdf())->download(collect([$entry]), []);
        ob_start();
        $response->sendContent();
        ob_end_clean();

        $this->assertCount(2, $this->transport->requests);
    }

    public function testApiErrorsBecomeValidationMessages(): void
    {
        $entry = $this->makeEntry('about');
        $this->transport->queueJson(['statusCode' => 400, 'message' => 'url: The URL is invalid.'], 400);

        try {
            (new ExportEntryAsPdf())->download(collect([$entry]), []);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['PDFGate: url: The URL is invalid.'], $exception->errors()['pdfgate']);
        }
    }

    public function testSeveralEntriesAreZipped(): void
    {
        $first = $this->makeEntry('one');
        $second = $this->makeEntry('two');
        foreach (['doc_1', 'doc_2'] as $id) {
            $this->queueDocument($id);
            $this->queueFile();
        }
        $this->queueNoContent();
        $this->queueNoContent();

        $response = (new ExportEntryAsPdf())->download(collect([$first, $second]), []);

        ob_start();
        $response->sendContent();
        $zip = (string) ob_get_clean();

        $this->assertStringStartsWith("PK\x03\x04", $zip);
        $this->assertStringContainsString('one.pdf', $zip);
        $this->assertStringContainsString('two.pdf', $zip);
        $this->assertCount(6, $this->transport->requests);
    }

    public function testHtmlModeRendersTheTemplateWithABaseTag(): void
    {
        config()->set('pdfgate-statamic.entries.source', 'html');
        config()->set('app.url', 'https://example.test');
        $entry = $this->makeEntry('about');

        $request = $this->app->make(EntryPdfGenerator::class)->buildRequest($entry);

        $this->assertArrayNotHasKey('url', $request);
        $this->assertStringContainsString('<h1>About</h1>', $request['html']);
        $this->assertStringContainsString('<head><base href="https://example.test/">', $request['html']);
    }

    public function testHtmlModeExportsADraft(): void
    {
        config()->set('pdfgate-statamic.entries.source', 'html');
        $draft = $this->makeEntry('draft', false);
        $this->queueDocument('doc_1');
        $this->queueFile();
        $this->queueNoContent();

        $response = (new ExportEntryAsPdf())->download(collect([$draft]), []);
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $this->assertSame(self::PDF, $body);
        $html = $this->transport->requests[0]->getJsonBody()['html'] ?? '';
        $this->assertStringContainsString('<h1>Draft</h1>', $html);
        $this->assertStringContainsString('Status: draft', $html);
    }
}
