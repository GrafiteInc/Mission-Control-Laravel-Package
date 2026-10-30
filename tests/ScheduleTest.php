<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Grafite\MissionControlLaravel\Middleware\Firewall;
use Grafite\MissionControlLaravel\GrafiteMissionControlLaravelProvider;

class ScheduleTest extends \TestCase
{
    protected function bootProvider(): void
    {
        $this->app->forgetInstance(Schedule::class);

        (new GrafiteMissionControlLaravelProvider($this->app))->boot();
    }

    protected function scheduledCommands(): array
    {
        return collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'mission-control:'))
            ->mapWithKeys(fn ($event) => [
                preg_replace('/.*(mission-control:[\w-]+).*/', '$1', $event->command) => $event->expression,
            ])
            ->all();
    }

    public function testNothingIsScheduledWhenDisabled()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.schedule.enabled', false);

        $this->bootProvider();

        $this->assertSame([], $this->scheduledCommands());
    }

    public function testNothingIsScheduledOutsideEnabledEnvironments()
    {
        Config::set('mission-control.environments', ['production']);
        Config::set('mission-control.schedule.enabled', true);

        $this->bootProvider();

        $this->assertSame([], $this->scheduledCommands());
    }

    public function testDefaultCommandsAreScheduledWhenEnabled()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.schedule.enabled', true);

        $this->bootProvider();

        $commands = $this->scheduledCommands();

        $this->assertSame('*/5 * * * *', $commands['mission-control:report']);
        $this->assertSame('*/15 * * * *', $commands['mission-control:queue']);

        // The app action classes don't exist, and the rest are off by default.
        $this->assertArrayNotHasKey('mission-control:stats', $commands);
        $this->assertArrayNotHasKey('mission-control:dependencies', $commands);
        $this->assertArrayNotHasKey('mission-control:virus-scan', $commands);
        $this->assertArrayNotHasKey('mission-control:code', $commands);
    }

    public function testCommandsCanBeSkippedOrRescheduled()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.schedule.enabled', true);
        Config::set('mission-control.schedule.commands.report', false);
        Config::set('mission-control.schedule.commands.virus-scan', '0 1 * * *');

        $this->bootProvider();

        $commands = $this->scheduledCommands();

        $this->assertArrayNotHasKey('mission-control:report', $commands);
        $this->assertSame('0 1 * * *', $commands['mission-control:virus-scan']);
    }

    public function testFirewallAliasIsRegistered()
    {
        $this->assertSame(Firewall::class, app('router')->getMiddleware()['firewall']);
    }

    public function testFirewallAliasCanBeDisabled()
    {
        $router = $this->app['router'];

        (fn () => $this->middleware = [])->call($router);

        Config::set('mission-control.firewall.alias', null);

        $this->bootProvider();

        $this->assertArrayNotHasKey('firewall', app('router')->getMiddleware());
    }

    public function testCommandsAreRegistered()
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('mission-control:notify', $commands);
        $this->assertArrayHasKey('mission-control:virus-scan', $commands);
        $this->assertArrayHasKey('mission-control:virus-scanner', $commands);
    }
}
