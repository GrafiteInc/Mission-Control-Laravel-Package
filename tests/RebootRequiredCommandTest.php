<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class RebootRequiredCommandTest extends \TestCase
{
    protected string $path;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->path = sys_get_temp_dir().'/mission-control-reboot-required-'.uniqid();
    }

    public function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path.'.pkgs');

        parent::tearDown();
    }

    public function testNothingIsSentWhenTheFileIsMissing()
    {
        $this->artisan('mission-control:reboot-required', ['--path' => $this->path])
            ->expectsOutput('No reboot required.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function testNothingIsSentWhenTheFileIsEmpty()
    {
        file_put_contents($this->path, "  \n");

        $this->artisan('mission-control:reboot-required', ['--path' => $this->path])
            ->expectsOutput('No reboot required.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function testNotificationIsSentWhenARebootIsRequired()
    {
        file_put_contents($this->path, "*** System restart required ***\n");
        file_put_contents($this->path.'.pkgs', "linux-base\nlibc6\nlinux-base\n");

        $this->artisan('mission-control:reboot-required', ['--path' => $this->path])
            ->expectsOutput('Sent.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request['title'] === 'Reboot Required'
                && $request['tag'] === 'security:reboot'
                && $request['message'] === "*** System restart required ***\nPackages: linux-base, libc6";
        });
    }

    public function testNotificationIsSentWithoutAPackagesFile()
    {
        file_put_contents($this->path, "*** System restart required ***\n");

        $this->artisan('mission-control:reboot-required', ['--path' => $this->path])
            ->expectsOutput('Sent.');

        Http::assertSent(fn (Request $request) => $request['message'] === '*** System restart required ***');
    }
}
