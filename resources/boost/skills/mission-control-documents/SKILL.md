---
name: mission-control-documents
description: Generate or refresh this application's Mission Control project documents (incident response plan, rebuild processes, key rotation, QA, security and performance audits, upgrade processes, third party integrations) from the codebase and push them to Mission Control. Use when asked to write, generate, update or sync Mission Control documentation.
---

# Mission Control Documents

Mission Control stores operational documents for each project. This skill writes them from what is actually in the codebase and pushes them with the `grafite/mission-control-laravel` artisan commands.

## 1. See what exists

```bash
php artisan mission-control:documents --json
```

The `types` map holds the only valid `--type` keys. The `documents` list shows what has already been pushed (uuid, title, type, updated_at). Content is not returned, so check `docs/mission-control/` for the Markdown from earlier runs and revise it rather than starting over.

If the user didn't name the documents they want, propose the types that are missing, or out of date, and confirm before generating them all.

## 2. Investigate the codebase

Every statement in a document should be traceable to the code, config or infrastructure files. Where something can't be determined, such as who is on call or where backups live, leave a clearly marked `TODO:` for a person to fill in. Don't invent it.

What to look at for each type:

- **incident_response_plan**: exception handling and logging (`bootstrap/app.php`, `config/logging.php`), the `config/mission-control.php` levels and notifications, queues and failed jobs, health check routes, the firewall middleware, and who to contact (TODO if unknown). Cover detection, triage, communication, mitigation, recovery and the post-incident review.
- **application_rebuild_process**: `composer.json`, `package.json` and the build scripts, `.env.example` (variable names only), migrations and seeders, storage links, queue workers, the scheduler, deploy scripts (Forge, Envoyer, Vapor, GitHub Actions, Dockerfiles) and cache warming commands.
- **access_key_rotations_process**: `config/services.php` and every `env()` key referenced in `config/`, the third party SDKs, `APP_KEY` implications (encrypted data, sessions), and Sanctum or Passport tokens. List each credential, where it is used and how to rotate it without downtime. Never record values.
- **qa_process**: the test suites (`phpunit.xml`, `tests/`, Pest, Dusk, JS tests), CI workflows, linters and static analysis (Pint, Larastan, ESLint), and how to run each locally.
- **security_audit_process**: auth and authorization (policies, gates, middleware), the firewall, CSRF and CORS config, dependency auditing (`composer audit`, `npm audit`), the `mission-control:code` scans, file uploads, and rate limiters.
- **performance_audit_process**: caching config, queues, database indexes and slow query thresholds (`query_threshold`, `page_load_threshold`), eager loading, Octane or Horizon if present, and the asset build.
- **language_update_upgrade_process**: the PHP version constraint in `composer.json`, the Laravel version, Node version files, and the CI matrix. Describe the upgrade steps and how to verify them.
- **server_update_upgrade_process**: infrastructure files (Docker, provisioning scripts, Forge or Vapor config), required PHP extensions, and the database, cache and queue services.
- **infrastructure_rebuild_process**: everything needed to recreate the environment from nothing: services, DNS, storage buckets, queues, schedulers, backups and restore steps. Mark unknowns as TODO.
- **third_party_integration**: write one document per integration (for example "Stripe Integration"). Cover `config/services.php`, the HTTP clients and SDK usage, webhooks and their routes, the env variable names, and the failure behaviour.

## 3. Write the Markdown

Save each document to `docs/mission-control/<type_key>.md`, or `docs/mission-control/third-party-<name>.md` for integrations. Use clear headings, numbered steps for procedures, and code blocks for commands. Don't include secrets, tokens, credentials or `.env` values, only the variable names.

## 4. Push it

```bash
php artisan mission-control:document "Incident Response Plan" --type=incident_response_plan --file=docs/mission-control/incident_response_plan.md
```

- Use the type's label as the title unless the document is one of several of that type, such as third party integrations.
- Re-pushing the same title and type updates the existing document. Pass `--uuid=<uuid>` to update a document whose title has changed.
- On failure the command exits with 1 and prints Mission Control's error, for example an invalid type key. Fix the error and retry. Don't create a renamed duplicate.

Finish by reporting each document's title and the Mission Control URL the command printed, along with any TODOs that need a person.
