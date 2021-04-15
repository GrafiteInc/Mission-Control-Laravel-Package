<?php

use Grafite\MissionControlLaravel\Notify;

class NotifyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $mockCurl = new MockRequest();

        $this->service = app(Notify::class);

        $this->service->notifyService->setCurl($mockCurl);
    }

    public function testNotify()
    {
        $response = $this->service->send('test', 'info', 'testing');

        $this->assertTrue($response);
    }
}
