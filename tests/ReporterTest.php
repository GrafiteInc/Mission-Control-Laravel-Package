<?php

use Illuminate\Http\Request;
use Omnipulse\Reporter\Services\Reporter;

class ReporterTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->service = app(Reporter::class);
    }

    public function testReporter()
    {
        $request = app(Request::class);
        $exception = new Exception('This is a test');
        $test = $this->service->processException($request, $exception);

        $this->assertEquals($test['report_server_software'], null);
        $this->assertEquals($test['report_server_name'], 'localhost');
        $this->assertEquals($test['exception_content'], 'This is a test');
    }
}
