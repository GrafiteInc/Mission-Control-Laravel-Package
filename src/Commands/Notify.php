<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;
use Grafite\MissionControlLaravel\Notify as NotifyAction;

class Notify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:notify {title} {content} {tag}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a notification to Mission Control';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (app(NotifyAction::class)->send(
            $this->argument('title'),
            $this->argument('tag'),
            $this->argument('content')
        )) {
            $this->info('Sent.');
        } else {
            $this->error('Failed to send.');
        }

        return 0;
    }
}
