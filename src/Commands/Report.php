<?php

namespace Grafite\MissionControlLaravel\Commands;

use Grafite\MissionControl\PerformanceService;
use Illuminate\Console\Command;
use Exception;

class Report extends Command
{
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
    protected $description = 'Reports data back to Mission Control';

    /**
     * Create a new Report instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->performanceService = new PerformanceService(
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
        $this->performanceService->sendPerformance();

        return true;
    }
}
