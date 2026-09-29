<?php

namespace Grafite\MissionControlLaravel;

use Illuminate\Mail\Events\MessageSent;

class Email
{
    public $emailService;

    public function __construct()
    {
        $this->emailService = new EmailService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function send(array $payload)
    {
        return $this->emailService->send($payload);
    }

    /**
     * Record an email the application has just sent.
     *
     * @param  MessageSent  $event
     * @return bool
     */
    public function record(MessageSent $event)
    {
        $payload = $this->payload($event);

        if (is_null($payload)) {
            return false;
        }

        return $this->send($payload);
    }

    /**
     * Build the Mission Control payload from the sent message.
     *
     * @param  MessageSent  $event
     * @return array|null
     */
    protected function payload(MessageSent $event)
    {
        $message = $event->message;

        $to = $this->addresses($message->getTo());

        // Mission Control rejects a record without a recipient.
        if (empty($to)) {
            return null;
        }

        $from = $this->addresses($message->getFrom());

        return [
            'to' => $to,
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'reply_to' => $this->addresses($message->getReplyTo()),
            'from' => $from[0] ?? null,
            'subject' => $message->getSubject(),
            'body_html' => $this->body($message->getHtmlBody()),
            'body_text' => $this->body($message->getTextBody()),
            'headers' => $this->headers($message),
            'mailable' => $event->data['__laravel_mailable'] ?? null,
            'mailer' => $event->data['mailer'] ?? config('mail.default'),
            'status' => 'sent',
            'sent_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Reduce the Symfony addresses to plain email addresses.
     *
     * @param  array  $addresses
     * @return array
     */
    protected function addresses($addresses)
    {
        return collect($addresses)
            ->map(fn ($address) => $address->getAddress())
            ->values()
            ->all();
    }

    /**
     * Flatten the message headers into a name to value map.
     *
     * @param  \Symfony\Component\Mime\Email  $message
     * @return array
     */
    protected function headers($message)
    {
        $headers = [];

        foreach ($message->getHeaders()->all() as $header) {
            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }

    /**
     * Bodies are optional, and capped so a large email
     * cannot turn into an unreasonable payload.
     *
     * @param  resource|string|null  $content
     * @return string|null
     */
    protected function body($content)
    {
        if (is_null($content) || ! config('mission-control.log_email_bodies', true)) {
            return null;
        }

        if (is_resource($content)) {
            $content = stream_get_contents($content, -1, 0);
        }

        return mb_substr((string) $content, 0, config('mission-control.email_body_limit', 65536));
    }
}
