<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebAppVersionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/cci-version-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/build', 0700, true);
        file_put_contents($this->directory.'/build/manifest.json', '{"app":{"file":"app-old.js"}}');
        file_put_contents($this->directory.'/sw.js', '// same worker');
        $this->app->usePublicPath($this->directory);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_version_and_shell_are_not_cached_and_share_the_same_fingerprint(): void
    {
        $response = $this->getJson('/app-version')->assertOk();
        $version = $response->json('version');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $version);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $shell = $this->get('/login')->assertOk()->assertSee('name="app-version" content="'.$version.'"', false);
        $this->assertStringContainsString('no-store', $shell->headers->get('Cache-Control'));
        $this->getJson('/app-version')->assertJsonPath('version', $version);
    }

    public function test_frontend_changes_are_detected_even_when_the_worker_does_not_change(): void
    {
        $before = $this->getJson('/app-version')->json('version');
        file_put_contents($this->directory.'/build/manifest.json', '{"app":{"file":"app-new.js"}}');
        $after = $this->getJson('/app-version')->assertOk()->json('version');
        $this->assertNotSame($before, $after);
        file_put_contents($this->directory.'/sw.js', '// new worker');
        $this->assertNotSame($after, $this->getJson('/app-version')->assertOk()->json('version'));
    }

    public function test_missing_build_does_not_report_a_fake_update(): void
    {
        unlink($this->directory.'/build/manifest.json');
        $this->getJson('/app-version')->assertStatus(503)->assertJsonPath('version', null);
        $this->get('/login')->assertOk();
    }
}
