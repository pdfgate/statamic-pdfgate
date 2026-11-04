<?php

declare(strict_types=1);

namespace PdfGate\Statamic\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use PdfGate\PdfGateClient;
use PdfGate\Statamic\ServiceProvider;
use PdfGate\Statamic\Tests\Support\FakeTransport;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected const API_KEY = 'test_statamic_key';
    protected const PDF = "%PDF-1.4\n%fake statamic test file\n";

    protected string $addonServiceProvider = ServiceProvider::class;

    /** Set to false in a test class to boot the addon with the download route turned off. */
    protected bool $downloadRoute = true;

    protected FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeTransport();
        $this->app->instance(
            PdfGateClient::class,
            PdfGateClient::createWithTransport(self::API_KEY, $this->transport)
        );
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [\PdfGate\Laravel\PdfGateServiceProvider::class]);
    }

    /**
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('pdfgate.api_key', self::API_KEY);
        // Set the whole array: nested keys set before the addon boots would shallow-merge the defaults away.
        $addonConfig = require __DIR__ . '/../config/pdfgate-statamic.php';
        $addonConfig['entries']['download_route'] = $this->downloadRoute;
        $addonConfig['entries']['cache_minutes'] = 0;
        $app['config']->set('pdfgate-statamic', $addonConfig);
        $app['config']->set('view.paths', [__DIR__ . '/__fixtures__/views']);
        $app['config']->set('statamic.users.repositories.file.paths.roles', $this->tempPath('roles.yaml'));
        $app['config']->set('filesystems.disks.assets', ['driver' => 'local', 'root' => $this->tempPath('assets')]);
        $app['config']->set('filesystems.disks.local', ['driver' => 'local', 'root' => $this->tempPath('local')]);
        $app['config']->set('statamic.system.file_uploads_disk', 'local');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempPath());

        parent::tearDown();
    }

    protected function tempPath(string $suffix = ''): string
    {
        return sys_get_temp_dir() . '/pdfgate-statamic-tests' . ($suffix !== '' ? '/' . $suffix : '');
    }

    protected function makeEntry(string $slug = 'about', bool $published = true): EntryContract
    {
        if (Collection::find('pages') === null) {
            Collection::make('pages')->routes('/{slug}')->template('default')->layout('layout')->save();
        }

        $entry = Entry::make()->collection('pages')->slug($slug)->published($published)->data(['title' => ucfirst($slug)]);
        $entry->save();

        return $entry;
    }

    /**
     * A signed-in user holding exactly these Statamic permissions.
     *
     * @param list<string> $permissions
     */
    protected function actingAsUserWith(array $permissions): void
    {
        $handle = 'role_' . substr(sha1(implode('|', $permissions)), 0, 8);

        if (Role::find($handle) === null) {
            Role::make($handle)->title($handle)->permissions($permissions)->save();
        }

        $user = User::make()->id('user_' . $handle)->email($handle . '@example.com');
        $user->assignRole($handle);
        $user->save();

        $this->actingAs($user);
    }

    protected function makePdfAsset(string $path = 'docs/report.pdf'): AssetContract
    {
        if (AssetContainer::find('assets') === null) {
            AssetContainer::make('assets')->disk('assets')->save();
        }

        Storage::disk('assets')->put($path, self::PDF);

        $asset = Asset::make()->container('assets')->path($path);
        $asset->save();

        return $asset;
    }

    /**
     * @param array<string,mixed> $extra
     */
    protected function queueDocument(string $id, array $extra = []): void
    {
        $this->transport->queueJson(array_merge([
            'id' => $id,
            'status' => 'completed',
            'size' => strlen(self::PDF),
            'createdAt' => '2026-11-02T08:00:00.000Z',
        ], $extra), 201);
    }

    protected function queueFile(string $contents = self::PDF): void
    {
        $this->transport->queue(new \PdfGate\Http\HttpResponse(200, $contents, ['content-type' => 'application/pdf']));
    }

    protected function queueNoContent(): void
    {
        $this->transport->queue(new \PdfGate\Http\HttpResponse(204, '', []));
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
