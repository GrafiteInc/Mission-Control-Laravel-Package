<?php

use Illuminate\Mail\Events\MessageSent;
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

    public function testTrafficScriptIncludesBotDetection()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.log_traffic', true);

        $this->bootProvider();

        $compiled = app('blade.compiler')->compileString("@missionControl('abc123')");

        $this->assertStringContainsString('webdriver', $compiled);
        $this->assertStringContainsString('isTrusted', $compiled);
        $this->assertStringContainsString('"interacted"', $compiled);
        $this->assertStringContainsString('"dwell_ms"', $compiled);
        $this->assertStringContainsString('"response_ms"', $compiled);
        $this->assertStringContainsString('"load_ms"', $compiled);
        $this->assertStringContainsString('"signals"', $compiled);
        $this->assertStringContainsString('keepalive', $compiled);
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

    public function testEmailListenerReportsSentMessages()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.log_emails', true);

        Http::fake();

        $this->bootProvider();

        app('events')->dispatch($this->fakeMessageSent());

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/emails')
                && $request->data()['to'] === ['user@example.com']
                && $request->data()['status'] === 'sent';
        });
    }

    public function testEmailListenerIsNotRegisteredWhenDisabled()
    {
        Config::set('mission-control.environments', ['testing']);
        Config::set('mission-control.log_emails', false);

        Http::fake();

        $this->bootProvider();

        app('events')->dispatch($this->fakeMessageSent());

        Http::assertNothingSent();
    }

    protected function fakeMessageSent(): MessageSent
    {
        $message = (new \Symfony\Component\Mime\Email())
            ->from('noreply@example.com')
            ->to('user@example.com')
            ->subject('Ping')
            ->html('<p>Hello</p>');

        $sent = new \Illuminate\Mail\SentMessage(
            new \Symfony\Component\Mailer\SentMessage(
                $message,
                new \Symfony\Component\Mailer\Envelope(
                    new \Symfony\Component\Mime\Address('noreply@example.com'),
                    [new \Symfony\Component\Mime\Address('user@example.com')]
                )
            )
        );

        return new MessageSent($sent, ['mailer' => 'smtp']);
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
