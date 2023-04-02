<?php

use Grafite\MissionControlLaravel\Notify;

class NotifyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Notify::class);
    }

    public function testNotify()
    {
        $response = $this->service->send('test', 'info', 'testing');

        $this->assertTrue($response);
    }
}
