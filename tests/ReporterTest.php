<?php

use Grafite\MissionControlLaravel\Issue;

class IssueTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = app(Issue::class);
    }

    public function testIssue()
    {
        $response = app(Issue::class)->log('testing', 'info');

        $this->assertTrue($response);
        // $exception = new Exception('This is a test');
        // $test = $this->service->processException($request, $exception);

        // $this->assertEquals($test['report_server_software'], null);
        // $this->assertEquals($test['report_server_name'], 'localhost');
        // $this->assertEquals($test['exception_content'], 'This is a test');
    }

    public function testException()
    {
        // $response = app(Issue::class)->exception('testing', 'info');

        // $this->assertTrue($response);
        // $exception = new Exception('This is a test');
        // $test = $this->service->processException($request, $exception);

        // $this->assertEquals($test['report_server_software'], null);
        // $this->assertEquals($test['report_server_name'], 'localhost');
        // $this->assertEquals($test['exception_content'], 'This is a test');
    }
}
