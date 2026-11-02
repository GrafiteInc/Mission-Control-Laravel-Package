<?php

namespace Grafite\MissionControlLaravel\Commands;

use Exception;
use Illuminate\Console\Command;
use Grafite\MissionControlLaravel\Document as DocumentAction;

class Documents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:documents
                            {--json : Output the types and documents as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the document types and existing documents in Mission Control';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $data = app(DocumentAction::class)->list();
        } catch (Exception $exception) {
            $this->error('Failed to list documents: ' . $exception->getMessage());

            return 1;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->table(['Type', 'Label'], collect($data['types'] ?? [])
            ->map(fn ($label, $type) => [$type, $label])
            ->values()
            ->all());

        $this->table(['UUID', 'Title', 'Type', 'Updated'], collect($data['documents'] ?? [])
            ->map(fn ($document) => [
                $document['uuid'] ?? null,
                $document['title'] ?? null,
                $document['type'] ?? null,
                $document['updated_at'] ?? null,
            ])
            ->all());

        return 0;
    }
}
