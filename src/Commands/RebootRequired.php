<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;

class RebootRequired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:reboot-required
        {--path=/var/run/reboot-required : The reboot-required file to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify Mission Control when the server needs a reboot';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = $this->option('path');
        $contents = $this->read($path);

        if ($contents === '') {
            $this->info('No reboot required.');

            return 0;
        }

        $message = $contents;
        $packages = $this->read($path.'.pkgs');

        if ($packages !== '') {
            $packages = collect(preg_split('/\R/', $packages))
                ->map(fn ($package) => trim($package))
                ->filter()
                ->unique()
                ->implode(', ');

            $message .= "\nPackages: {$packages}";
        }

        if (mission_control_notify('Reboot Required', 'security:reboot', $message)) {
            $this->info('Sent.');
        } else {
            $this->error('Failed to send.');
        }

        return 0;
    }

    /**
     * Read a file, returning an empty string when it's missing or unreadable.
     */
    protected function read(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        return trim((string) file_get_contents($path));
    }
}
