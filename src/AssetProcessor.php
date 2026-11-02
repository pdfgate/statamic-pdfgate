<?php

declare(strict_types=1);

namespace PdfGate\Statamic;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use PdfGate\Dto\PdfGateDocumentMetadata;
use PdfGate\Laravel\PdfGateManager;
use Statamic\Assets\ReplacementFile;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Asset as Assets;

/**
 * Sends a PDF asset through a PDFGate operation and writes the result back to Statamic.
 */
final class AssetProcessor
{
    public const MODE_REPLACE = 'replace';
    public const MODE_COPY = 'copy';

    public function __construct(
        private readonly PdfGateManager $pdfGate,
        private readonly Config $config,
        private readonly FilesystemFactory $filesystems
    ) {
    }

    /**
     * Uploads the asset, runs the operation and stores the result.
     *
     * @param string $mode "replace" overwrites the asset file, "copy" saves a new asset next to it.
     * @param string $suffix Appended to the file name in copy mode, for example "compressed".
     * @param callable(string): PdfGateDocumentMetadata $operation Receives the uploaded document ID and returns the processed document.
     * @return Asset The asset that now holds the processed file.
     */
    public function process(Asset $asset, string $mode, string $suffix, callable $operation): Asset
    {
        $uploadedId = $this->upload($asset);
        $resultId = null;

        try {
            $resultId = $operation($uploadedId)->getId();

            return $mode === self::MODE_COPY
                ? $this->saveCopy($asset, $resultId, $suffix)
                : $this->replace($asset, $resultId);
        } finally {
            $this->cleanup(...array_filter([$uploadedId, $resultId]));
        }
    }

    private function upload(Asset $asset): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'pdfgate-');
        if ($temp === false) {
            throw new \RuntimeException('Could not create a temporary file for the upload.');
        }

        try {
            $this->writeStream($temp, $asset->stream());

            $document = $this->pdfGate->uploadFile([
                'file' => new \CURLFile($temp, 'application/pdf', $asset->basename()),
            ]);

            return $document->getId();
        } finally {
            @unlink($temp);
        }
    }

    private function replace(Asset $asset, string $documentId): Asset
    {
        $diskName = (string) $this->config->get('statamic.system.file_uploads_disk', 'local');
        $directory = rtrim((string) $this->config->get('statamic.system.file_uploads_path', 'statamic/file-uploads'), '/');
        $path = $directory . '/pdfgate-' . Str::uuid() . '.' . $asset->extension();
        $disk = $this->filesystems->disk($diskName);

        $stream = $this->pdfGate->getFile($documentId);

        try {
            $disk->put($path, $stream);
        } finally {
            fclose($stream);
        }

        try {
            $asset->reupload(new ReplacementFile($path));
        } finally {
            $disk->delete($path);
        }

        return $asset;
    }

    private function saveCopy(Asset $asset, string $documentId, string $suffix): Asset
    {
        $path = $this->uniqueCopyPath($asset, $suffix);
        $disk = $this->filesystems->disk($asset->container()->diskHandle());

        $stream = $this->pdfGate->getFile($documentId);

        try {
            $disk->put($path, $stream);
        } finally {
            fclose($stream);
        }

        $copy = Assets::make()
            ->container($asset->container()->handle())
            ->path($path);

        $copy->save();

        return $copy;
    }

    private function uniqueCopyPath(Asset $asset, string $suffix): string
    {
        $folder = (string) $asset->folder();
        $prefix = ($folder === '' || $folder === '/') ? '' : rtrim($folder, '/') . '/';
        $extension = $asset->extension();
        $container = $asset->container();

        for ($attempt = 1; ; $attempt++) {
            $name = $asset->filename() . '-' . $suffix . ($attempt > 1 ? '-' . $attempt : '');
            $path = $prefix . $name . '.' . $extension;

            if (Assets::find($container->handle() . '::' . $path) === null
                && !$this->filesystems->disk($container->diskHandle())->exists($path)) {
                return $path;
            }
        }
    }

    private function cleanup(string ...$documentIds): void
    {
        if ($this->config->get('pdfgate-statamic.delete_remote_documents', true) !== true) {
            return;
        }

        foreach ($documentIds as $documentId) {
            try {
                $this->pdfGate->deleteDocument($documentId);
            } catch (\Throwable) {
                // The result is already stored in Statamic; a failed cleanup is not an error for the user.
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function writeStream(string $path, $stream): void
    {
        $target = fopen($path, 'wb');
        if ($target === false) {
            fclose($stream);
            throw new \RuntimeException('Could not open the temporary file for writing.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
            fclose($stream);
        }
    }
}
