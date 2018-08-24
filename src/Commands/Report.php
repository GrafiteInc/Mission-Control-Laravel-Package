<?php

namespace Grafite\MissionControlLaravel\Commands;

use Grafite\MissionControl\PerformanceService;
use Grafite\MissionControl\TrafficService;
use Illuminate\Console\Command;

class Report extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reports back data to Mission Control';

    /**
     * Create a new Report instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->trafficService = new TrafficService(config('mission-control.token', null));
        $this->performanceService = new PerformanceService(config('mission-control.token', null));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $log = config('mission-control.access_log_file_path');
        $format = config('mission-control.format');

        $this->trafficService->sendTraffic($log, $format);

        $this->performanceService->sendPerformance();
    }
}
