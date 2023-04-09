<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class VirusScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:virus-scanner';

    /**µ
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan an app for viruses';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // timeout to 45 min
        $result = Process::timeout(2700)
            ->run('clamscan --infected --detect-pua=yes --recursive --remove '.base_path());

        mission_control_notify('Virus Scan', 'security:virus', $result->output());

        return 0;
    }
}
