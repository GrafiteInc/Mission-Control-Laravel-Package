<?php

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Config;
use Grafite\MissionControlLaravel\Email;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;

class EmailTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Email::class);
    }

    public function testSendPostsThePayload()
    {
        $response = $this->service->send([
            'to' => ['user@example.com'],
            'from' => 'noreply@example.com',
            'subject' => 'Welcome aboard',
            'status' => 'failed',
            'error' => 'Connection refused',
        ]);

        $this->assertTrue($response);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/emails')
                && $request->method() === 'POST'
                && $request->hasHeader('key', 'testing')
                && $request->data()['status'] === 'failed';
        });
    }

    public function testRecordBuildsThePayloadFromTheMessage()
    {
        $event = $this->messageSentEvent();

        $this->assertTrue($this->service->record($event));

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_ends_with($request->url(), '/api/emails')
                && $payload['to'] === ['user@example.com']
                && $payload['cc'] === ['cc@example.com']
                && $payload['bcc'] === []
                && $payload['reply_to'] === ['replies@example.com']
                && $payload['from'] === 'noreply@example.com'
                && $payload['subject'] === 'Welcome aboard'
                && $payload['body_html'] === '<p>Hello</p>'
                && $payload['body_text'] === 'Hello'
                && $payload['headers']['Subject'] === 'Welcome aboard'
                && $payload['mailable'] === 'App\Mail\Welcome'
                && $payload['mailer'] === 'smtp'
                && $payload['status'] === 'sent'
                && ! empty($payload['sent_at']);
        });
    }

    public function testBodiesAreOmittedWhenDisabled()
    {
        Config::set('mission-control.log_email_bodies', false);

        $this->service->record($this->messageSentEvent());

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return is_null($payload['body_html']) && is_null($payload['body_text']);
        });
    }

    public function testBodiesAreTruncatedToTheConfiguredLimit()
    {
        Config::set('mission-control.email_body_limit', 5);

        $this->service->record($this->messageSentEvent());

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['body_html'] === '<p>He' && $payload['body_text'] === 'Hello';
        });
    }

    public function testRecordIsSkippedWithoutRecipients()
    {
        $message = (new \Symfony\Component\Mime\Email())
            ->from('noreply@example.com')
            ->cc('cc@example.com')
            ->subject('Welcome aboard')
            ->html('<p>Hello</p>');

        $this->assertFalse($this->service->record($this->eventFor($message)));

        Http::assertNothingSent();
    }

    public function testConnectionFailuresDoNotEscape()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertFalse($this->service->record($this->messageSentEvent()));
    }

    protected function messageSentEvent(): MessageSent
    {
        $message = (new \Symfony\Component\Mime\Email())
            ->from('noreply@example.com')
            ->to('user@example.com')
            ->cc('cc@example.com')
            ->replyTo('replies@example.com')
            ->subject('Welcome aboard')
            ->html('<p>Hello</p>')
            ->text('Hello');

        return $this->eventFor($message);
    }

    protected function eventFor($message): MessageSent
    {
        $sent = new \Illuminate\Mail\SentMessage(
            new \Symfony\Component\Mailer\SentMessage(
                $message,
                new Envelope(new Address('noreply@example.com'), [new Address('cc@example.com')])
            )
        );

        return new MessageSent($sent, [
            'mailer' => 'smtp',
            '__laravel_mailable' => 'App\Mail\Welcome',
        ]);
    }
}
