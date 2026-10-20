---
name: review-api-pr
description: >
  Reviews a ps_apiresources pull request or audits an existing Admin API endpoint against REVIEW.md and
  CONTEXT.md: contract with the core CQRS classes, OpenAPI documentation versus real behaviour, error
  mapping, tests. Trigger: "review this ps_apiresources PR", "review PR #443 of the API module",
  "pre-review my endpoint", "audit the {Entity} endpoint", "check the OpenAPI documentation of {resource}".
needs: []
produces: "A review report (findings with severity, rule reference, evidence and fix), optionally posted as PR comments on request."
subagent: recommended
---

# Review an Admin API pull request or endpoint

Read `.ai/REVIEW.md` (the checklist) and `.ai/CONTEXT.md` (the conventions, with the on-demand `.ai/TESTING.md`, `OPENAPI.md`, `LISTS.md`, `MULTISHOP.md`, `VERSIONS.md` the checks point to)
before anything else. The core side (providers, normalizers, OpenAPI adapters, features per core version)
is in the PrestaShop repository: `.ai/Component/AdminAPI/CONTEXT.md`.

## Inputs

Ask only for what is missing:

1. **What to review**: a PR number / URL of `PrestaShop/ps_apiresources` (or a fork), a local branch, or
   a resource class to audit (`src/ApiPlatform/Resources/{Domain}/{Resource}.php`).
2. **The core checkout** to compare against (`_PS_ROOT_DIR_`, usually `modules/ps_apiresources` is a
   symlink from it). The module must be read against the core branch of the **related core PR** named in
   the PR description when there is one (`gh pr view <n> --repo PrestaShop/ps_apiresources --json body`),
   otherwise against the most recent maintained stable branch (the highest `X.Y.x`, `9.2.x` today); the
   older supported cores (9.0.3, 9.1.5) are checked on top through the age of the guards (.ai/REVIEW.md → 0.2).
   Say which reference was used in the report.
3. Whether to **post** the findings on the PR (`gh pr comment` / `gh pr review`) or only report them.
   Never post without an explicit request.

## Steps

1. **Collect the diff**: `gh pr diff <n> --repo PrestaShop/ps_apiresources` (or `git diff <base>...`).
   List the resources, DTOs, tests, config and PHPStan/Rector files touched. For an audit, the "diff" is
   the whole resource file plus its test.
2. **Read the core classes** named by each resource (.ai/REVIEW.md → 0.3): command constructor and setters,
   query result getters, handler exceptions (private guard methods included), value objects, grid query
   builder for lists, BO form type. With a related core PR: `gh pr diff <core-n> --repo
   PrestaShop/PrestaShop` and read the classes on that branch (`git -C <core> fetch upstream
   pull/<core-n>/head:pr-<core-n>` then `git show pr-<core-n>:<path>`). Without one, read the most recent
   maintained stable branch, and for every guard, cast or null check you rely on, check its age (`git -C
   <core> log --format='%h %ad' --date=short -- <file>`, `git blame`, `git tag --contains <sha>`): a commit
   younger than the lowest supported tag (9.0.3) means the older CI legs run without it.
3. **Static checks**: go through .ai/REVIEW.md §1 sections A (design), B (contract with the core), C (OpenAPI),
   D (errors), E (tests), F (hygiene), reading the context section each one names. For every array
   property, write down its element constraints before reading the command (the expected set is in
   .ai/ERRORS.md → "Bulk operations"). For every candidate finding, write the evidence (core file and
   line, or the schema/payload) before keeping it; drop it if §2 says so; rate it with §3.
4. **Runtime checks**, only when the environment running the skill has a PrestaShop instance with the
   module installed (a developer's shop, set up with the core skill `setup-apiresources-dev`); CodeRabbit
   has no shop and stops at the static checks (.ai/REVIEW.md §1 C "without a shop"). What the shop needs:
   an API client with the scopes (`bin/console prestashop:api-client create <id> --all-scopes --secret-only`
   when the back office has none), the `admin_api_experimental_endpoints` feature flag for an experimental
   operation, plain HTTP accepted in debug mode (`PS_ADMIN_API_FORCE_DEBUG_SECURED=0` otherwise), a
   `cache:clear` run with `php -d memory_limit=-1`. Read the HTTP status, not the body: in debug mode the
   problem+json body says `"status":500` whatever the real code. Ask before running
   write operations on a shop that is not a disposable test instance:
   - `php bin/console api:openapi:export --yaml -o <scratch>/after.yaml` in the core with the PR applied,
     the same on the base branch, diff the operations of the PR;
   - call the endpoints with a token of an API client of that shop (BO > Advanced Parameters > Admin API,
     then `POST /admin-api/access_token` with `grant_type=client_credentials` and the scopes needed):
     happy path, unknown id, id `0`, empty body, invalid field; compare statuses and payloads with the
     schema;
   - run the test class: `_PS_ROOT_DIR_=<core> php -d date.timezone=UTC <core>/vendor/phpunit/phpunit/phpunit -c
     <module>/tests/Integration/phpunit-local.xml --filter=<Class>`; for a fix, revert it and check the
     test fails.
5. **Report** (.ai/REVIEW.md → §3): findings sorted by severity, each with `path:line`, the rule, the evidence,
   the fix; then the checks that passed in one line each (no detail); then the open questions for the
   maintainer. State the core reference used and whether the runtime checks ran.
6. **Compare with the existing review** when the PR already has human comments: list what they found that
   the checklist missed (a candidate rule for REVIEW.md) and what the checklist found that they did not.

## Output format

```markdown
## Review of PR #<n> — <title>
Core reference: <branch or related PR> · runtime checks: yes/no

### Blocker
- `src/ApiPlatform/Resources/X/Y.php:42` — <finding>. Rule: REVIEW.md D (404 rule). Evidence: <core class:line / payload>. Fix: <change>.

### Major
...

### Minor
...

### Passed
- A design/URIs · B contract (Add/Edit commands read) · C OpenAPI (exported, 0 diff outside the PR) · D errors · E tests · F hygiene

### Questions for the maintainer
- ...
```
