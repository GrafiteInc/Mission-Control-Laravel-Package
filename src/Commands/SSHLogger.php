<?php

namespace Grafite\MissionControlLaravel\Commands;

use Illuminate\Console\Command;

class SSHLogger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mission-control:ssh-logger';

    /**µ
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Appends a webhook to the bashrc file.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $key = config('mission-control.api_key', null);
        $uuid = config('mission-control.api_uuid', null);
        $url = config('mission-control.url', null);

        $snippet = <<<EOL
        if [[ -n \$SSH_CONNECTION ]] ; then
            curl --location --request POST '{$url}/api/webhook/{$uuid}/notify?key={$key}' \
            --header 'Content-Type: application/json' \
            --data-raw '{
                "title": "SSH Access",
                "content": "CI tooling or Manual Access for maintenance via: '"\$SSH_CLIENT"'",
                "tag": "security:ssh"
            }'
        fi
        EOL;

        file_put_contents('../.bashrc', $snippet, FILE_APPEND);

        $this->info('SSH Logger has been added to your .bashrc file.');

        return 0;
    }
}
