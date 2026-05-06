# Development Agent Rules

## Communication

- Answer in Hungarian for the project owner.
- Keep documentation and file names in English.
- Explain technical decisions in plain language.

## Git Workflow

- Never work directly on `main`.
- Always create a branch named `feature/<scope-name>`.
- Commit and push only when explicitly requested by the project owner.
- Before any commit or push, run:
  - `make test`
  - `make quality`

## WordPress and WooCommerce Rules

- Do not install unnecessary plugins.
- Ask before adding paid plugins.
- Prefer maintainable custom plugin code for business-specific behavior.
- Keep business logic out of WordPress hooks, REST controllers, templates, and admin screens.

## Architecture

Use DDD and CQRS inside custom plugins:

- `src/Domain`: entities, value objects, domain rules.
- `src/Application`: command/query objects, handlers, DTOs.
- `src/Infrastructure`: WordPress/WooCommerce adapters and repositories.
- `src/Interfaces`: REST, hook, and admin entry points.

## Testing Rules

- No real payment calls in tests.
- No real external API calls in tests.
- No live scraping in tests.
- Use mocks, stubs, or local fixtures.

## Environment Rules

- Docker configuration must be environment-driven.
- `.env` contains local values and secrets.
- `.env.example` contains safe example values only.
- When adding a new environment key, update both `.env` and `.env.example`.

## Required Documentation Updates

When behavior, API, architecture, or workflow changes, update:

- `README.md`
- Relevant files in `docs/`
- `deficiencies.md`
