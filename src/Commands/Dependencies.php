<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;
use Grafite\MissionControl\DependencyService;

class Dependencies extends Command
{
    public $statsService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:dependencies';

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

        $this->statsService = new DependencyService(
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
        try {
            $payload = app('App\\Actions\\AppDependencies')->handle();

            $this->statsService->send($payload->toArray());
        } catch (\Throwable $th) {
            $this->info('No App\\Actions\\AppDependencies action found.');
            //throw $th;
        }

        return 0;
    }
}
