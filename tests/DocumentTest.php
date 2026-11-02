<?php

use Grafite\MissionControlLaravel\Document;

class DocumentTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = app(Document::class);
    }

    public function testListReturnsTypesAndDocuments()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'status' => 'success',
                'data' => [
                    'types' => ['qa_process' => 'QA Process'],
                    'documents' => [
                        ['uuid' => 'abc', 'title' => 'QA Process', 'type' => 'qa_process', 'updated_at' => '2026-11-01'],
                    ],
                ],
            ]),
        ]);

        $this->assertEquals(['qa_process' => 'QA Process'], $this->service->types());
        $this->assertEquals('abc', $this->service->documents()[0]['uuid']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/documents')
                && $request->method() === 'GET'
                && $request->hasHeader('key', 'testing')
                && $request->hasHeader('Authorization', 'Bearer testing');
        });
    }

    public function testPushPostsThePayload()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'status' => 'success',
                'message' => 'Document created.',
                'data' => ['uuid' => 'abc', 'url' => 'https://missioncontrolapp.io/documents/abc'],
            ], 201),
        ]);

        $document = $this->service->push('QA Process', '## Steps', 'qa_process');

        $this->assertTrue($document['created']);
        $this->assertEquals('abc', $document['uuid']);
        $this->assertEquals('https://missioncontrolapp.io/documents/abc', $document['url']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/documents')
                && $request->method() === 'POST'
                && $request->hasHeader('key', 'testing')
                && $request->data() === [
                    'title' => 'QA Process',
                    'content' => '## Steps',
                    'type' => 'qa_process',
                ];
        });
    }

    public function testPushSendsTheOptions()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'status' => 'success',
                'message' => 'Document updated.',
                'data' => ['uuid' => 'abc', 'url' => 'https://missioncontrolapp.io/documents/abc'],
            ]),
        ]);

        $document = $this->service->push('QA Process', '<p>Steps</p>', null, [
            'format' => 'html',
            'uuid' => 'abc',
        ]);

        $this->assertFalse($document['created']);

        Http::assertSent(function ($request) {
            return $request->data() === [
                'title' => 'QA Process',
                'content' => '<p>Steps</p>',
                'format' => 'html',
                'uuid' => 'abc',
            ];
        });
    }

    public function testValidationFailuresAreThrownWithoutRetrying()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'message' => 'The selected type is invalid.',
                'errors' => ['type' => ['The selected type is invalid.']],
            ], 422),
        ]);

        try {
            $this->service->push('QA Process', '## Steps', 'not_a_type');
            $this->fail('Expected an exception.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('The selected type is invalid.', $exception->getMessage());
            $this->assertEquals(422, $exception->getCode());
        }

        Http::assertSentCount(1);
    }

    public function testConnectionFailuresAreThrown()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->expectExceptionMessage('Unable to reach Mission Control');

        $this->service->list();
    }

    public function testHelperPushesTheDocument()
    {
        Http::fake([
            '*/api/documents' => Http::response(['data' => ['uuid' => 'abc', 'url' => 'url']], 201),
        ]);

        $this->assertEquals('abc', mission_control_document('QA Process', '## Steps')['uuid']);
    }
}
