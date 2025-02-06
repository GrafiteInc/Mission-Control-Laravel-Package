<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Grafite\MissionControl\QueueService;

class QueueStats extends Command
{
    public $queueService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reports data back to Mission Control';

    /**
     * Create a new Report instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->queueService = new QueueService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $stats = [];

        foreach (config('mission-control.queues') as $queue => $connection) {
            $stats[$connection][$queue]['processed_jobs'] = 0;
            $stats[$connection][$queue]['initiated_jobs'] = 0;

            $cacheNameProcessed = 'mission-control-processed-'.$connection.'-'.$queue.'-jobs';
            $cacheNameInitiated = 'mission-control-initiated-'.$connection.'-'.$queue.'-jobs';

            if (cache()->has($cacheNameProcessed)) {
                $stats[$connection][$queue]['processed_jobs'] += cache($cacheNameProcessed);
            }

            if (cache()->has($cacheNameInitiated)) {
                $stats[$connection][$queue]['initiated_jobs'] += cache($cacheNameInitiated);
            }

            $stats[$connection][$queue]['failed_jobs'] = DB::connection(config('queue.failed.database'))
                ->table(config('queue.failed.table'))
                ->where('queue', 'like', "%$queue%")
                ->where('connection', $connection)
                ->count();

            $stats[$connection][$queue]['current_size'] = app('queue')->connection($connection)->size($queue);

            cache()->forget($cacheNameProcessed);
            cache()->forget($cacheNameInitiated);
        }

        $this->queueService->send($stats);

        return 0;
    }
}
