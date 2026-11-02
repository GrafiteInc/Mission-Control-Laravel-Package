<?php

class DocumentCommandTest extends TestCase
{
    public $file;

    public function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'mission-control-document');
        file_put_contents($this->file, "## Steps\n\n1. Run the tests.");
    }

    public function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function testDocumentsListsAsJson()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'data' => [
                    'types' => ['qa_process' => 'QA Process'],
                    'documents' => [],
                ],
            ]),
        ]);

        $this->artisan('mission-control:documents', ['--json' => true])
            ->expectsOutputToContain('"qa_process": "QA Process"')
            ->assertExitCode(0);
    }

    public function testDocumentsListsAsTables()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'data' => [
                    'types' => ['qa_process' => 'QA Process'],
                    'documents' => [
                        ['uuid' => 'abc', 'title' => 'QA Process', 'type' => 'qa_process', 'updated_at' => '2026-11-01'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('mission-control:documents')
            ->expectsTable(['Type', 'Label'], [['qa_process', 'QA Process']])
            ->expectsTable(['UUID', 'Title', 'Type', 'Updated'], [['abc', 'QA Process', 'qa_process', '2026-11-01']])
            ->assertExitCode(0);
    }

    public function testDocumentPushesTheFile()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'message' => 'Document created.',
                'data' => ['uuid' => 'abc', 'url' => 'https://missioncontrolapp.io/documents/abc'],
            ], 201),
        ]);

        $this->artisan('mission-control:document', [
            'title' => 'QA Process',
            '--type' => 'qa_process',
            '--file' => $this->file,
        ])
            ->expectsOutput('Document created: https://missioncontrolapp.io/documents/abc')
            ->expectsOutput('UUID: abc')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return $request->data()['title'] === 'QA Process'
                && $request->data()['type'] === 'qa_process'
                && $request->data()['format'] === 'markdown'
                && $request->data()['content'] === "## Steps\n\n1. Run the tests.";
        });
    }

    public function testDocumentFailsForAMissingFile()
    {
        Http::fake();

        $this->artisan('mission-control:document', [
            'title' => 'QA Process',
            '--file' => '/does/not/exist.md',
        ])
            ->expectsOutput('Unable to read the file: /does/not/exist.md')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function testDocumentReportsServerErrors()
    {
        Http::fake([
            '*/api/documents' => Http::response([
                'message' => 'The selected type is invalid.',
                'errors' => ['type' => ['The selected type is invalid.']],
            ], 422),
        ]);

        $this->artisan('mission-control:document', [
            'title' => 'QA Process',
            '--type' => 'not_a_type',
            '--file' => $this->file,
        ])
            ->expectsOutputToContain('Failed to push the document: The selected type is invalid.')
            ->assertExitCode(1);
    }
}
