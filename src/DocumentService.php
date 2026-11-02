<?php

namespace Grafite\MissionControlLaravel;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Grafite\MissionControl\BaseService;
use Illuminate\Http\Client\ConnectionException;

class DocumentService extends BaseService
{
    public $token;

    public $key;

    protected $missionControlUrl;

    public function __construct($token = null, $key = null)
    {
        $this->token = $token;
        $this->key = $key;
        $this->missionControlUrl = $this->missionControlDomain('documents');
    }

    /**
     * List the available document types and the project's documents.
     *
     * @return array
     */
    public function list(): array
    {
        $response = $this->request(fn ($request) => $request->get($this->missionControlUrl));

        return $response->json('data', []);
    }

    /**
     * Create or update a document. Mission Control matches on uuid
     * when given, otherwise on title and type.
     *
     * @param  array  $payload
     * @return array
     */
    public function push(array $payload): array
    {
        $response = $this->request(fn ($request) => $request->post($this->missionControlUrl, $payload));

        return $response->json('data', []) + [
            'created' => $response->status() === 201,
            'message' => $response->json('message'),
        ];
    }

    /**
     * Documents are pushed on purpose, so unlike the logging
     * services, failures are thrown for the caller to see.
     *
     * @param  callable  $callback
     * @return \Illuminate\Http\Client\Response
     */
    protected function request(callable $callback)
    {
        if (is_null($this->token)) {
            throw new Exception("Missing token", 1);
        }

        if (is_null($this->key)) {
            throw new Exception("Missing key", 1);
        }

        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'key' => $this->key,
        ])->acceptJson()->retry(3, 100, fn ($exception) => $exception instanceof ConnectionException, throw: false);

        try {
            $response = $callback($request);
        } catch (ConnectionException $exception) {
            throw new Exception('Unable to reach Mission Control: ' . $exception->getMessage(), 1);
        }

        if ($response->failed()) {
            $errors = Arr::flatten((array) $response->json('errors', []));
            $message = $response->json('message') ?: $response->reason();

            throw new Exception(trim($message . ' ' . implode(' ', $errors)), $response->status());
        }

        return $response;
    }
}
