<?php

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Config;
use Grafite\MissionControlLaravel\LongRunningMonitor;
use Grafite\MissionControlLaravel\GrafiteMissionControlLaravelProvider;

class LongRunningMonitorTest extends \TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Config::set('mission-control.environments', ['testing']);
    }

    protected function bootProvider(): void
    {
        (new GrafiteMissionControlLaravelProvider($this->app))->boot();
    }

    public function testSlowJobNotifiesMissionControl()
    {
        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/notify')
                && $request['title'] === 'Long running job: App\Jobs\SyncOrders'
                && $request['tag'] === 'warning'
                && str_contains($request['message'], 'on database:default took 61s (threshold 60s), attempt 1.');
        });
    }

    public function testFastJobDoesNotNotify()
    {
        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 59);

        Http::assertNothingSent();
    }

    public function testJobThresholdIsConfigurable()
    {
        Config::set('mission-control.long_running.jobs.threshold', 5);

        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 6);

        Http::assertSentCount(1);
    }

    public function testJobMonitoringCanBeDisabled()
    {
        Config::set('mission-control.long_running.jobs.enabled', false);

        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 120);

        Http::assertNothingSent();
    }

    public function testJobMonitoringIsOffWhenMissionControlIsDisabled()
    {
        Config::set('mission-control.environments', ['production']);

        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 120);

        Http::assertNothingSent();
    }

    public function testCooldownSuppressesRepeatNotifications()
    {
        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);
        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);
        $this->runJob($this->fakeJob('App\Jobs\OtherJob'), 61);

        Http::assertSentCount(2);
    }

    public function testCooldownExpires()
    {
        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);
        $this->travel(16)->minutes();
        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);

        Http::assertSentCount(2);
    }

    public function testZeroCooldownAlwaysNotifies()
    {
        Config::set('mission-control.long_running.cooldown', 0);

        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);
        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);

        Http::assertSentCount(2);
    }

    public function testBootingTwiceDoesNotDuplicateNotifications()
    {
        Config::set('mission-control.long_running.cooldown', 0);

        $this->bootProvider();
        $this->bootProvider();

        $this->runJob($this->fakeJob('App\Jobs\SyncOrders'), 61);

        Http::assertSentCount(1);
    }

    public function testSlowScheduledTaskNotifiesMissionControl()
    {
        $this->bootProvider();

        $task = app(Schedule::class)->command('inspire')->everyFiveMinutes();

        app('events')->dispatch(new ScheduledTaskStarting($task));
        app('events')->dispatch(new ScheduledTaskFinished($task, 301.5));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/notify')
                && str_starts_with($request['title'], 'Long running scheduled task: ')
                && str_contains($request['title'], 'inspire')
                && str_contains($request['message'], '(*/5 * * * *) took 301.5s (threshold 300s).');
        });
    }

    public function testFastScheduledTaskDoesNotNotify()
    {
        $this->bootProvider();

        $task = app(Schedule::class)->command('inspire');

        app('events')->dispatch(new ScheduledTaskFinished($task, 299.0));

        Http::assertNothingSent();
    }

    public function testSlowFailedScheduledTaskNotifiesMissionControl()
    {
        $this->bootProvider();

        $task = app(Schedule::class)->command('inspire');

        app('events')->dispatch(new ScheduledTaskStarting($task));
        $this->travel(301)->seconds();
        app('events')->dispatch(new ScheduledTaskFailed($task, new Exception('boom'), 301.0));

        Http::assertSentCount(1);
    }

    public function testTaskMonitoringCanBeDisabled()
    {
        Config::set('mission-control.long_running.tasks.enabled', false);

        $this->bootProvider();

        $task = app(Schedule::class)->command('inspire');

        app('events')->dispatch(new ScheduledTaskFinished($task, 900.0));

        Http::assertNothingSent();
    }

    public function testReportJobUsesTheJobConnectionWhenNoneIsGiven()
    {
        $this->assertTrue(app(LongRunningMonitor::class)->reportJob($this->fakeJob('App\Jobs\SyncOrders', 3), 92.44, 60));

        Http::assertSent(function ($request) {
            return $request['message'] === 'App\Jobs\SyncOrders on database:default took 92.4s (threshold 60s), attempt 3.';
        });
    }

    public function testReportTaskSendsNotification()
    {
        $task = app(Schedule::class)->command('inspire');

        $this->assertTrue(app(LongRunningMonitor::class)->reportTask($task, 400, 300));

        Http::assertSentCount(1);
    }

    public function testNotificationFailuresAreSwallowed()
    {
        Config::set('mission-control.api_token', null);

        $this->assertFalse(app(LongRunningMonitor::class)->reportJob($this->fakeJob('App\Jobs\SyncOrders'), 120, 60));
    }

    protected function runJob(object $job, int $seconds): void
    {
        app('events')->dispatch(new JobProcessing('database', $job));
        $this->travel($seconds)->seconds();
        app('events')->dispatch(new JobAttempted('database', $job, null, $seconds * 1000));
    }

    protected function fakeJob(string $name, int $attempts = 1): object
    {
        return new class($name, $attempts)
        {
            public function __construct(protected string $name, protected int $attempts)
            {
            }

            public function resolveName()
            {
                return $this->name;
            }

            public function attempts()
            {
                return $this->attempts;
            }

            public function getConnectionName()
            {
                return 'database';
            }

            public function getQueue()
            {
                return 'default';
            }

            public function payload()
            {
                return [];
            }

            public function hasFailed()
            {
                return false;
            }
        };
    }
}
