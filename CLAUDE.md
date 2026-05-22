# Claude Code — ps_apiresources

The conventions, architecture, and Do/Don't list for this repository
live in **[`CONTEXT.md`](./CONTEXT.md)**. Read it before suggesting or
generating any code.

When the user asks to add, expose, or wire up a new Admin API endpoint,
invoke the [`ps-api-endpoint`](./.claude/skills/ps-api-endpoint/SKILL.md)
skill — it generates the resource class and integration test in line
with `CONTEXT.md`.
