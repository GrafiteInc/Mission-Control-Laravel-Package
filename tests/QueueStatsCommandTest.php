<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Grafite\MissionControlLaravel\Commands\QueueStats;

class QueueStatsCommandTest extends \TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('queue.failed.database', 'sqlite');
        Config::set('queue.failed.table', 'failed_jobs');
        Config::set('mission-control.queues', [
            'sync' => 'sync',
        ]);

        DB::connection('sqlite')->getSchemaBuilder()->create('failed_jobs', function ($table) {
            $table->increments('id');
            $table->string('connection');
            $table->string('queue');
        });
    }

    public function testHandlePullsAndClearsCounters()
    {
        Cache::put('mission-control-processed-sync-sync-jobs', 5, now()->addMinute());
        Cache::put('mission-control-initiated-sync-sync-jobs', 9, now()->addMinute());

        DB::connection('sqlite')->table('failed_jobs')->insert([
            'connection' => 'sync',
            'queue' => 'sync',
        ]);

        $sentStats = null;

        $command = app(QueueStats::class);
        $command->queueService = new class($sentStats)
        {
            public $sentStats;

            public function __construct(&$sentStats)
            {
                $this->sentStats = &$sentStats;
            }

            public function send($stats)
            {
                $this->sentStats = $stats;

                return true;
            }
        };

        $result = $command->handle();

        $this->assertIsArray($sentStats);
        $this->assertSame(0, $result);
        $this->assertSame(5, $sentStats['sync']['sync']['processed_jobs']);
        $this->assertSame(9, $sentStats['sync']['sync']['initiated_jobs']);
        $this->assertSame(1, $sentStats['sync']['sync']['failed_jobs']);
        $this->assertNull(cache('mission-control-processed-sync-sync-jobs'));
        $this->assertNull(cache('mission-control-initiated-sync-sync-jobs'));
    }

    public function testHandleDefaultsToZeroWhenCountersAreMissing()
    {
        $sentStats = null;

        $command = app(QueueStats::class);
        $command->queueService = new class($sentStats)
        {
            public $sentStats;

            public function __construct(&$sentStats)
            {
                $this->sentStats = &$sentStats;
            }

            public function send($stats)
            {
                $this->sentStats = $stats;

                return true;
            }
        };

        $command->handle();

        $this->assertIsArray($sentStats);
        $this->assertSame(0, $sentStats['sync']['sync']['processed_jobs']);
        $this->assertSame(0, $sentStats['sync']['sync']['initiated_jobs']);
        $this->assertSame(0, $sentStats['sync']['sync']['failed_jobs']);
    }
}
