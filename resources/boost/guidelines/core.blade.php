## Mission Control Documents

This application uses `grafite/mission-control-laravel`, which can push project documentation (incident response plans, rebuild processes, QA processes and so on) to Mission Control.

- Run `php artisan mission-control:documents --json` first. It returns the valid document `types` (key and label) and the project's existing `documents` (uuid, title, type, updated_at). Content is not returned.
- Push a document with `php artisan mission-control:document "Title" --type=<type_key> --file=path/to/document.md`. The content is Markdown by default, add `--format=html` to send HTML. The content can also be piped in on STDIN.
- Pushing is an upsert: a document in the project with the same title and type is updated in place, or pass `--uuid=<uuid>` to update a specific document. Reuse the existing title and type when refreshing a document, so it isn't duplicated.
- Mission Control cannot return document content, so keep the generated Markdown in `docs/mission-control/<type_key>.md` and revise that file on later runs.
- Never include secrets, credentials, tokens or `.env` values in a document. Refer to environment variables by name only.
- In code, `mission_control_document($title, $content, $type, ['format' => 'markdown', 'uuid' => null])` does the same as the command and throws an exception on failure.
- Use the `mission-control-documents` skill when asked to generate or refresh Mission Control documents.
