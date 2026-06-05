<?php

namespace Grafite\MissionControlLaravel\Actions;

use Symfony\Component\Process\Process;

class RunCognitiveQuality
{
    /**
     * Execute phpcca analyse for cognitive complexity analysis.
     * Requires: composer require --dev phauthentic/cognitive-code-analysis
     *
     * @param  string  $appPath  Path to scan (e.g., app_path())
     * @return array
     */
    public function handle(string $appPath): array
    {
        $executable = base_path('vendor/bin/phpcca');

        if (!file_exists($executable)) {
            return [
                'scan_type'  => 'quality',
                'scanned_at' => now()->toIso8601String(),
                'status'     => 'fail',
                'error'      => 'cognitive-code-analysis not installed. Run: composer require --dev phauthentic/cognitive-code-analysis',
            ];
        }

        $tmpFile = sys_get_temp_dir() . '/phpcca-report-' . time() . '.json';

        try {
            $process = new Process([
                $executable,
                'analyse',
                $appPath,
                '--report-type',
                'json',
                '--report-file',
                $tmpFile,
            ]);

            $process->run();

            if (!file_exists($tmpFile)) {
                return [
                    'scan_type'  => 'quality',
                    'scanned_at' => now()->toIso8601String(),
                    'status'     => 'fail',
                    'error'      => 'Failed to generate cognitive complexity report.',
                ];
            }

            $raw = file_get_contents($tmpFile);
            \file_put_contents(base_path('storage/logs/phpcca-debug.log'), $raw);
            @unlink($tmpFile);

            $results = json_decode($raw, true);

            if (!is_array($results)) {
                return [
                    'scan_type'  => 'quality',
                    'scanned_at' => now()->toIso8601String(),
                    'status'     => 'fail',
                    'error'      => 'Could not parse cognitive complexity JSON output.',
                    'raw'        => substr($raw, 0, 500),
                ];
            }

            return $this->parseResults($results);
        } catch (\Throwable $th) {
            return [
                'scan_type'  => 'quality',
                'scanned_at' => now()->toIso8601String(),
                'status'     => 'fail',
                'error'      => $th->getMessage(),
            ];
        }
    }

    /**
     * Parse phpcca JSON output into Mission Control payload format.
     *
     * @param  array  $results
     * @return array
     */
    private function parseResults(array $results): array
    {
        $classes = [];
        $totalCognitiveScore = 0;
        $totalMethods = 0;
        $methodsAboveThreshold = 0;
        $highRiskThreshold = 15; // cognitive complexity score threshold for "high risk"

        // Extract class-level metrics from the results structure
        foreach ($results as $className => $classData) {
            $methods = $classData['methods'] ?? $classData['method_metrics'] ?? [];

            $classMethods = [];
            $classScore = 0;

            if (is_array($methods)) {
                foreach ($methods as $methodData) {
                    $methodName = $methodData['name'] ?? $methodData['method'] ?? 'unknown';
                    $cogScore = $methodData['score'] ?? $methodData['score'] ?? 0;

                    $classMethods[] = [
                        'name'                  => $methodName,
                        'cognitive_complexity' => $cogScore,
                        'lines'                 => $methodData['lineCount'] ?? 0,
                        'arguments'             => $methodData['argCount'] ?? 0,
                        'variables'             => $methodData['variableCount'] ?? 0,
                    ];

                    $classScore += $cogScore;
                    $totalCognitiveScore += $cogScore;
                    $totalMethods++;

                    if ($cogScore > $highRiskThreshold) {
                        $methodsAboveThreshold++;
                    }
                }
            }

            $classes[] = [
                'name'                 => $className,
                'cognitive_complexity' => $classScore,
                'method_count'         => count($classMethods),
                'methods'              => $classMethods,
            ];
        }

        // Determine overall status based on complexity metrics
        $avgMethodComplexity = $totalMethods > 0 ? $totalCognitiveScore / $totalMethods : 0;
        $status = $this->determineStatus($avgMethodComplexity, $methodsAboveThreshold, $totalMethods);

        $worstClasses = collect($classes)->sortByDesc('cognitive_complexity')->take(5)->values()->all();

        $worstClasses = collect($worstClasses)->map(function ($c) {
            return [
                'name' => $c['name'],
                'score' => $c['cognitive_complexity'],
            ];
        })->all();

        return [
            'scan_type'  => 'quality',
            'scanned_at' => now()->toIso8601String(),
            'status'     => $status,
            'summary'    => [
                'total_classes'            => count($classes),
                'total_methods'            => $totalMethods,
                'total_cognitive_score'    => $totalCognitiveScore,
                'average_method_complexity' => round($avgMethodComplexity, 2),
                'methods_above_threshold'  => $methodsAboveThreshold,
                'high_risk_threshold'      => $highRiskThreshold,
            ],
            'worst_classes' => $worstClasses,
        ];
    }

    /**
     * Determine overall scan status based on complexity metrics.
     *
     * @param  float  $avgComplexity
     * @param  int    $methodsAboveThreshold
     * @param  int    $totalMethods
     * @return string 'pass'|'warning'|'fail'
     */
    private function determineStatus(float $avgComplexity, int $methodsAboveThreshold, int $totalMethods): string
    {
        if ($methodsAboveThreshold === 0 && $avgComplexity < 10) {
            return 'pass';
        }

        if ($avgComplexity < 12 || $methodsAboveThreshold < ($totalMethods * 0.2)) {
            return 'warning';
        }

        return 'fail';
    }
}
