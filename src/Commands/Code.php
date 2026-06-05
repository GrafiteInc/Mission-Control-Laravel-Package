<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;
use Grafite\MissionControlLaravel\CodeService;
use Grafite\MissionControlLaravel\Actions\RunCheckpointSecurity;
use Grafite\MissionControlLaravel\Actions\RunCognitiveQuality;

class Code extends Command
{
    public $codeService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:code
                            {--type=both : Scan type (security|quality|both)}
                            {--only= : Comma-separated list of Checkpoint check names to run}
                            {--skip= : Comma-separated list of Checkpoint check names to skip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs security and/or quality code scans and sends results to Mission Control';

    /**
     * Create a new Code command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->codeService = new CodeService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $type = $this->option('type') ?? 'both';

        if (!in_array($type, ['security', 'quality', 'both'])) {
            $this->error('Invalid type. Must be: security, quality, or both');

            return 1;
        }

        try {
            // Run security scan if requested
            if (in_array($type, ['security', 'both'])) {
                if (!class_exists(\Checkpoint\Scanner::class)) {
                    $this->warn('Checkpoint not installed, skipping security scan. Run: composer require --dev andreapollastri/checkpoint');
                } else {
                    $payload = (new RunCheckpointSecurity())->handle([
                        'only' => $this->option('only'),
                        'skip' => $this->option('skip'),
                    ]);

                    $this->codeService->send($payload);
                    $this->info('Security scan results sent to Mission Control.');
                }
            }

            // Run quality scan if requested
            if (in_array($type, ['quality', 'both'])) {
                if (!file_exists(base_path('vendor/bin/phpcca'))) {
                    $this->warn('Cognitive Code Analysis not installed, skipping quality scan. Run: composer require --dev phauthentic/cognitive-code-analysis');
                } else {
                    $payload = (new RunCognitiveQuality())->handle(app_path());

                    $this->codeService->send($payload);
                    $this->info('Quality scan results sent to Mission Control.');
                }
            }
        } catch (\Throwable $th) {
            $this->error('Error running code scans: ' . $th->getMessage());

            return 1;
        }

        return 0;
    }
}
