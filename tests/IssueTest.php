<?php

use Grafite\MissionControlLaravel\Issue;

class IssueTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Issue::class);
    }

    public function testIssue()
    {
        $response = $this->service->log('testing', 'info');

        $this->assertTrue($response);
    }

    public function testException()
    {
        $response = false;

        try {
            throw new \Exception("This is a test", 1);
        } catch (\Exception $e) {
            $response = $this->service->exception($e);
        }

        $this->assertTrue($response);
    }
}
