<?php

use Grafite\MissionControlLaravel\Webhook;

class WebhookTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $mockCurl = new MockRequest();

        $this->service = app(Webhook::class);

        $this->service->webhookService->setCurl($mockCurl);
    }

    public function testWebhook()
    {
        $response = $this->service->send('test', 'testing', 'info');

        $this->assertTrue($response);
    }
}
