<?php

namespace Grafite\MissionControlLaravel\Commands;

use Grafite\MissionControl\PerformanceService;
use Grafite\MissionControl\TrafficService;
use Illuminate\Console\Command;

class Report extends Command
{
    public $trafficService;

    public $performanceService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:report';

    /**µ
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

        $this->trafficService = new TrafficService(config('mission-control.api_token', null));
        $this->performanceService = new PerformanceService(config('mission-control.api_token', null));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $log = config('mission-control.access_log_file_path');

        if (empty($log)) {
            throw new Exception("The Mission Control config for 'access_log_file_path' cannot be null or empty.", 1);
        }

        $format = config('mission-control.format', '%a %l %u %t "%m %U %H" %>s %O "%{Referer}i" \"%{User-Agent}i"');

        $this->trafficService->sendTraffic($log, $format);

        $this->performanceService->sendPerformance();

        return true;
    }
}
