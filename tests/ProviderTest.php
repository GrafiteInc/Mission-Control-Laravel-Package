<?php

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Grafite\MissionControlLaravel\GrafiteMissionControlLaravelProvider;

class ProviderTest extends \TestCase
{
    protected function bootProvider(): void
    {
        (new GrafiteMissionControlLaravelProvider($this->app))->boot();
    }

    public function testDirectiveReturnsEmptyWhenDisabled()
    {
        Config::set('mission-control.environments', ['production']);

        $this->bootProvider();

        $compiled = app('blade.compiler')->compileString("@missionControl('abc123')");

        $this->assertStringNotContainsString('MISSION CONTROL', $compiled);
    }

    public function testDirectiveIncludesScriptWhenEnabled()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.log_traffic', false);
        Config::set('mission-control.log_javascript_errors', false);

        $this->bootProvider();

        $compiled = app('blade.compiler')->compileString("@missionControl('abc123')");

        $this->assertStringContainsString('MISSION CONTROL', $compiled);
        $this->assertStringContainsString('script type="module"', $compiled);
    }

    public function testDirectiveRespectsTrafficAndErrorFeatureFlags()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.log_traffic', true);
        Config::set('mission-control.log_javascript_errors', true);

        $this->bootProvider();

        $compiled = app('blade.compiler')->compileString("@missionControl('abc123')");

        $this->assertStringContainsString('/traffic?key=', $compiled);
        $this->assertStringContainsString('/issue?key=', $compiled);
    }

    public function testQueueListenersOnlyTrackConfiguredQueues()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.queues', [
            'default' => 'database',
        ]);
        Config::set('queue.connections.database.prefix', null);

        $this->bootProvider();

        Cache::forget('mission-control-initiated-database-default-jobs');
        Cache::forget('mission-control-processed-database-default-jobs');
        Cache::forget('mission-control-initiated-database-other-jobs');
        Cache::forget('mission-control-processed-database-other-jobs');

        $defaultJob = $this->fakeQueueJob('default');
        $otherJob = $this->fakeQueueJob('other');

        app('events')->dispatch(new JobProcessing('database', $defaultJob));
        app('events')->dispatch(new JobProcessed('database', $defaultJob));
        app('events')->dispatch(new JobProcessing('database', $otherJob));
        app('events')->dispatch(new JobProcessed('database', $otherJob));

        $this->assertSame(1, (int) cache('mission-control-initiated-database-default-jobs', 0));
        $this->assertSame(1, (int) cache('mission-control-processed-database-default-jobs', 0));
        $this->assertSame(0, (int) cache('mission-control-initiated-database-other-jobs', 0));
        $this->assertSame(0, (int) cache('mission-control-processed-database-other-jobs', 0));
    }

    public function testQueuePrefixIsRemovedBeforeMatchingConfiguredQueues()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.queues', [
            'default' => 'database',
        ]);
        Config::set('queue.connections.database.prefix', 'queues:');

        $this->bootProvider();

        Cache::forget('mission-control-initiated-database-default-jobs');
        Cache::forget('mission-control-processed-database-default-jobs');

        $prefixedJob = $this->fakeQueueJob('queues:/default');

        app('events')->dispatch(new JobProcessing('database', $prefixedJob));
        app('events')->dispatch(new JobProcessed('database', $prefixedJob));

        $this->assertSame(1, (int) cache('mission-control-initiated-database-default-jobs', 0));
        $this->assertSame(1, (int) cache('mission-control-processed-database-default-jobs', 0));
    }

    public function testQueueCountersRemainMonotonicAcrossRepeatedEvents()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.queues', [
            'default' => 'database',
        ]);
        Config::set('queue.connections.database.prefix', null);

        $this->bootProvider();

        Cache::forget('mission-control-initiated-database-default-jobs');
        Cache::forget('mission-control-processed-database-default-jobs');

        $job = $this->fakeQueueJob('default');

        for ($i = 0; $i < 5; $i++) {
            app('events')->dispatch(new JobProcessing('database', $job));
            app('events')->dispatch(new JobProcessed('database', $job));
        }

        $this->assertSame(5, (int) cache('mission-control-initiated-database-default-jobs', 0));
        $this->assertSame(5, (int) cache('mission-control-processed-database-default-jobs', 0));
    }

    protected function fakeQueueJob(string $queue): object
    {
        return new class($queue)
        {
            protected $queue;

            public function __construct(string $queue)
            {
                $this->queue = $queue;
            }

            public function getQueue()
            {
                return $this->queue;
            }

            public function payload()
            {
                return [];
            }
        };
    }
}
