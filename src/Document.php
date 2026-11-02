<?php

namespace Grafite\MissionControlLaravel;

class Document
{
    public $documentService;

    public function __construct()
    {
        $this->documentService = new DocumentService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function list()
    {
        return $this->documentService->list();
    }

    public function types()
    {
        return $this->list()['types'] ?? [];
    }

    public function documents()
    {
        return $this->list()['documents'] ?? [];
    }

    /**
     * Push a document, the options can include a format (markdown or html)
     * and the uuid of an existing document to update.
     *
     * @param  string  $title
     * @param  string  $content
     * @param  string|null  $type
     * @param  array  $options
     * @return array
     */
    public function push($title, $content, $type = null, array $options = [])
    {
        $payload = array_filter([
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'format' => $options['format'] ?? null,
            'uuid' => $options['uuid'] ?? null,
        ], fn ($value) => ! is_null($value));

        return $this->documentService->push($payload);
    }
}
