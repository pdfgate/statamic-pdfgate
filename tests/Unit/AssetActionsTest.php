<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PdfGate\Statamic\Actions\CompressPdf;
use PdfGate\Statamic\Actions\ProtectPdf;
use PdfGate\Statamic\Actions\WatermarkPdf;
use PdfGate\Statamic\Tests\TestCase;
use Statamic\Facades\Asset;

final class AssetActionsTest extends TestCase
{
    private const PROCESSED = "%PDF-1.4\n%processed\n";

    public function testCompressReplacesTheOriginalFile(): void
    {
        $asset = $this->makePdfAsset('docs/report.pdf');
        $this->queueDocument('up_1');
        $this->queueDocument('out_1', ['derivedFrom' => 'up_1']);
        $this->queueFile(self::PROCESSED);
        $this->queueNoContent();
        $this->queueNoContent();

        $message = (new CompressPdf())->run(collect([$asset]), ['mode' => 'replace']);

        $this->assertSame('PDF compressed.', $message);
        $this->assertSame(self::PROCESSED, Storage::disk('assets')->get('docs/report.pdf'));
        $this->assertNull(Asset::find('assets::docs/report-compressed.pdf'));
        $this->assertSame([], Storage::disk('local')->allFiles('statamic/file-uploads'));

        $requests = $this->transport->requests;
        $this->assertStringContainsString('/upload', $requests[0]->getUrl());
        $this->assertSame('report.pdf', $requests[0]->getMultipartBody()['file']->getPostFilename());
        $this->assertStringContainsString('/compress/pdf', $requests[1]->getUrl());
        $this->assertSame(['documentId' => 'up_1', 'linearize' => false, 'jsonResponse' => true], $requests[1]->getJsonBody());
        $this->assertStringContainsString('/file/out_1', $requests[2]->getUrl());
        $this->assertSame('DELETE', $requests[3]->getMethod());
        $this->assertSame('DELETE', $requests[4]->getMethod());
    }

    public function testWatermarkCanSaveACopyNextToTheOriginal(): void
    {
        $asset = $this->makePdfAsset('docs/report.pdf');
        Storage::disk('assets')->put('docs/report-watermarked.pdf', 'taken');
        $this->queueDocument('up_1');
        $this->queueDocument('out_1');
        $this->queueFile(self::PROCESSED);
        $this->queueNoContent();
        $this->queueNoContent();

        $values = ['mode' => 'copy', 'text' => 'DRAFT', 'font_size' => 40, 'opacity' => 25, 'rotate' => 30, 'font_color' => '#ff0000'];
        (new WatermarkPdf())->run(collect([$asset]), $values);

        $this->assertSame(self::PDF, Storage::disk('assets')->get('docs/report.pdf'));
        $this->assertSame(self::PROCESSED, Storage::disk('assets')->get('docs/report-watermarked-2.pdf'));
        $this->assertNotNull(Asset::find('assets::docs/report-watermarked-2.pdf'));

        $body = $this->transport->requests[1]->getMultipartBody();
        $this->assertStringContainsString('/watermark/pdf', $this->transport->requests[1]->getUrl());
        $this->assertSame('text', $body['type']);
        $this->assertSame('DRAFT', $body['text']);
        $this->assertSame(0.25, $body['opacity']);
        $this->assertSame('#ff0000', $body['fontColor']);
    }

    public function testProtectUsesTheOpenPasswordAsOwnerPasswordWhenEmpty(): void
    {
        $asset = $this->makePdfAsset('docs/report.pdf');
        $this->queueDocument('up_1');
        $this->queueDocument('out_1');
        $this->queueFile(self::PROCESSED);
        $this->queueNoContent();
        $this->queueNoContent();

        (new ProtectPdf())->run(collect([$asset]), [
            'mode' => 'replace',
            'user_password' => 'open-secret',
            'owner_password' => '',
            'disable_print' => true,
            'disable_copy' => false,
            'disable_editing' => false,
        ]);

        $body = $this->transport->requests[1]->getJsonBody();
        $this->assertStringContainsString('/protect/pdf', $this->transport->requests[1]->getUrl());
        $this->assertSame('open-secret', $body['userPassword']);
        $this->assertSame('open-secret', $body['ownerPassword']);
        $this->assertTrue($body['disablePrint']);
        $this->assertFalse($body['disableCopy']);
    }

    public function testApiErrorsNameTheFile(): void
    {
        $asset = $this->makePdfAsset('docs/report.pdf');
        $this->queueDocument('up_1');
        $this->transport->queueJson(['statusCode' => 400, 'message' => 'Please provide an ownerPassword value.'], 400);
        $this->queueNoContent();

        try {
            (new ProtectPdf())->run(collect([$asset]), ['mode' => 'replace', 'user_password' => 'x']);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['report.pdf: PDFGate: Please provide an ownerPassword value.'], $exception->errors()['mode']);
        }

        $this->assertSame(self::PDF, Storage::disk('assets')->get('docs/report.pdf'));
        $this->assertSame('DELETE', $this->transport->requests[2]->getMethod());
    }
}
