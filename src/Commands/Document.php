<?php

namespace Grafite\MissionControlLaravel\Commands;

use Exception;
use Illuminate\Console\Command;
use Grafite\MissionControlLaravel\Document as DocumentAction;

class Document extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:document
                            {title : The document title}
                            {--type= : The document type, see mission-control:documents}
                            {--file= : The file to read the content from, otherwise STDIN is read}
                            {--uuid= : The uuid of an existing document to update}
                            {--format=markdown : The content format: markdown or html}
                            {--json : Output the response as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update a document in Mission Control';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $content = $this->content();

        if (is_null($content)) {
            return 1;
        }

        try {
            $document = app(DocumentAction::class)->push(
                $this->argument('title'),
                $content,
                $this->option('type'),
                [
                    'format' => $this->option('format'),
                    'uuid' => $this->option('uuid'),
                ]
            );
        } catch (Exception $exception) {
            $this->error('Failed to push the document: ' . $exception->getMessage());

            return 1;
        }

        if ($this->option('json')) {
            $this->line(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->info(($document['created'] ? 'Document created: ' : 'Document updated: ') . ($document['url'] ?? ''));
        $this->line('UUID: ' . ($document['uuid'] ?? ''));

        return 0;
    }

    /**
     * Read the content from the file option, or from piped input.
     *
     * @return string|null
     */
    protected function content()
    {
        $file = $this->option('file');

        if ($file) {
            if (! is_file($file) || ! is_readable($file)) {
                $this->error("Unable to read the file: {$file}");

                return null;
            }

            $content = file_get_contents($file);
        } elseif (! stream_isatty(STDIN)) {
            $content = stream_get_contents(STDIN);
        } else {
            $content = '';
        }

        if (trim((string) $content) === '') {
            $this->error('No content given, use --file or pipe the content in.');

            return null;
        }

        return $content;
    }
}
