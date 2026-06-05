<?php

use Grafite\MissionControlLaravel\Code;

class CodeTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Code::class);
    }

    public function testSecurityScan()
    {
        $response = $this->service->send([
            'scan_type'  => 'security',
            'scanned_at' => '2026-06-03T10:00:00+00:00',
            'status'     => 'warning',
            'summary'    => [
                'total'    => 3,
                'passed'   => 1,
                'failed'   => 1,
                'warnings' => 1,
            ],
            'passed_checks'  => [
                [
                    'name'    => 'SQL Injection Risks',
                    'message' => 'No raw queries with variable interpolation detected.',
                    'details' => [],
                    'hashes'  => [],
                ],
            ],
            'failed_checks'  => [
                [
                    'name'    => 'Debug Functions in Production Code',
                    'message' => 'Debug calls found in production code.',
                    'details' => ['app/Http/Controllers/HomeController.php:42 — dd($request->all())'],
                    'hashes'  => ['abc123def456'],
                ],
            ],
            'warning_checks' => [
                [
                    'name'    => 'Composer CVE Audit',
                    'message' => 'One known vulnerability found.',
                    'details' => ['some/package:1.0.0 — CVE-2024-1234'],
                    'hashes'  => ['def456abc789'],
                ],
            ],
        ]);

        $this->assertTrue($response);
    }

    public function testQualityScan()
    {
        $response = $this->service->send([
            'scan_type'  => 'quality',
            'scanned_at' => '2026-06-03T10:00:00+00:00',
            'status'     => 'pass',
            'summary'    => [
                'total_classes'             => 5,
                'total_methods'             => 23,
                'total_cognitive_score'     => 145,
                'average_method_complexity' => 6.3,
                'methods_above_threshold'   => 2,
                'high_risk_threshold'       => 15,
            ],
            'classes' => [
                [
                    'name'                 => 'App\Http\Controllers\HomeController',
                    'cognitive_complexity' => 42,
                    'method_count'         => 3,
                    'methods'              => [
                        [
                            'name'                  => 'index',
                            'cognitive_complexity' => 8,
                            'lines'                 => 20,
                            'arguments'             => 0,
                            'variables'             => 5,
                            'cyclomatic_complexity' => 3,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($response);
    }
}
