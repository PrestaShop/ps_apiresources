# .ai/ — AI context of the module

Same architecture as the PrestaShop core (`.ai/STRUCTURE.md` there): one folder holds the context, the
tool-specific touch points at the repository root (`CLAUDE.md`, `AGENTS.md`, `GEMINI.md`,
`.cursor/rules/ps-apiresources.mdc`, `.windsurf/rules/ps-apiresources.md`,
`.github/copilot-instructions.md`) only point to it.

```
.ai/
├── CONTEXT.md        # always-true conventions, opens with the "Read when" table of the files below
├── TESTING.md        # integration tests
├── OPENAPI.md        # generated documentation as part of the contract
├── LISTS.md          # PaginatedList / CQRSPaginate / CQRSGetCollection
├── MULTISHOP.md      # shop association and shop context
├── ERRORS.md         # exception mapping, validation, bulk operations, status codes
├── VERSIONS.md       # minVersion, features per core version
├── UPLOAD.md         # file uploads: multipart body, POST updates, File property, tests
├── REVIEW.md         # review checklist (CodeRabbit guideline, review-api-pr skill)
├── skills/           # ps-api-endpoint, review-api-pr — symlinked from .claude/skills/
├── rules/            # path-scoped rules, source of the per-tool copies (see below)
└── bin/sync-rules.sh # writes the per-tool copies of the rules
```

## Path-scoped rules

`rules/{name}.md` holds a `description`, the `paths` to watch (YAML list) and a short body saying which
context to read. `bash .ai/bin/sync-rules.sh` symlinks it into `.claude/rules/` (Claude Code reads
`paths`) and writes a copy with the right frontmatter for Cursor (`.cursor/rules/{name}.mdc`), Windsurf
(`.windsurf/rules/{name}.md`) and Copilot (`.github/instructions/{name}.instructions.md`). The copies are
generated ("do not edit" header) and committed; edit the source and run the script.

Rules: `api-resources` (`src/ApiPlatform/**`, `config/**` → `OPENAPI.md`, `LISTS.md`, `MULTISHOP.md`,
`ERRORS.md`, `VERSIONS.md`, `UPLOAD.md`), `api-tests` (`tests/Integration/**` → `TESTING.md`, `UPLOAD.md`).

## Maintenance

- One place per rule: `CONTEXT.md` holds what is always true, each on-demand file holds the rules of its
  topic, and `CONTEXT.md` keeps only the trigger and one sentence for them. `REVIEW.md`, the skills, the
  CodeRabbit path instructions and the root touch points point to the rule, they never restate it: a
  rule written twice is maintained twice and drifts.
- After any change to `rules/`: `bash .ai/bin/sync-rules.sh`.
- The contexts quote classes, files and behaviours of the module and of the core: check them when a
  resource, a helper or a core feature changes; an outdated example propagates (a resource copied from
  an older one carries its obsolete options).
- When a review (human or CodeRabbit) finds a rule the context does not state, or a false positive it
  should prevent, update `CONTEXT.md` / the on-demand file and `REVIEW.md` in the same PR
  (`REVIEW.md` → §5).
- The CodeRabbit configuration is `.coderabbit.yaml` at the repository root; its guidelines are `.ai/*.md`.
