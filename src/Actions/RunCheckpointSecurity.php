<?php

namespace Grafite\MissionControlLaravel\Actions;

use Illuminate\Support\Facades\Artisan;

class RunCheckpointSecurity
{
    /**
     * Execute the checkpoint:scan --json command and return a structured payload.
     *
     * @param  array{only?: string|null, skip?: string|null}  $options
     * @return array
     */
    public function handle(array $options = []): array
    {
        $packageProof = base_path('vendor/andreapollastri/checkpoint/composer.json');

        if (!file_exists($packageProof)) {
            return [
                'scan_type'  => 'quality',
                'scanned_at' => now()->toIso8601String(),
                'status'     => 'fail',
                'error'      => 'Checkpoint package not found. Please run: composer require --dev andreapollastri/checkpoint.',
            ];
        }

        $args = ['--json' => true];

        if (!empty($options['only'])) {
            $args['--only'] = $options['only'];
        }

        if (!empty($options['skip'])) {
            $args['--skip'] = $options['skip'];
        }

        Artisan::call('checkpoint:scan', $args);

        $raw = trim(Artisan::output());
        $results = json_decode($raw, true);

        if (!is_array($results)) {
            return [
                'scan_type'  => 'security',
                'scanned_at' => now()->toIso8601String(),
                'status'     => 'fail',
                'error'      => 'Could not parse Checkpoint JSON output.',
                'raw'        => $raw,
            ];
        }

        $passed = [];
        $failed = [];
        $warnings = [];

        foreach ($results as $result) {
            $entry = [
                'name'    => $result['check'] ?? 'Unknown',
                'message' => $result['message'] ?? '',
                'details' => $result['details'] ?? [],
                'hashes'  => $result['hashes'] ?? [],
            ];

            match ($result['status'] ?? '') {
                'pass' => $passed[]   = $entry,
                'fail' => $failed[]   = $entry,
                'warn' => $warnings[] = $entry,
                default => null,
            };
        }

        // Determine overall status
        $status = empty($failed) ? (empty($warnings) ? 'pass' : 'warning') : 'fail';

        return [
            'scan_type'      => 'security',
            'scanned_at'     => now()->toIso8601String(),
            'status'         => $status,
            'summary'        => [
                'total'    => count($results),
                'passed'   => count($passed),
                'failed'   => count($failed),
                'warnings' => count($warnings),
            ],
            'failed_checks'  => $failed,
            'warning_checks' => $warnings,
        ];
    }
}
