# Where the conventions are

The conventions are written once, in `.ai/CONTEXT.md` and its on-demand files; this
reference only says which section each step of the skill needs. Read the section,
do not rely on a summary of it.

| Step of the skill | Read |
|-------------------|------|
| Operation attribute, HTTP method, duplicate, body-less action, upload | `CONTEXT.md` → "Architecture overview" and "Choosing the operation" |
| URI, bulk endpoint shape, action names | `CONTEXT.md` → "URI conventions" (and its table of endpoints deviating from ADR 0023) |
| Scope names | `CONTEXT.md` → "Scopes" |
| Property names and types, localized fields, `@var int[]` | `CONTEXT.md` → "Property naming" |
| Objects and lists of objects | `CONTEXT.md` → "Sub objects: typed DTOs" |
| `CQRSQueryMapping`, `CQRSCommandMapping`, `ApiResourceMapping`, `@index` | `CONTEXT.md` → "Field mapping", "Read and write formats must match" |
| Default values on creation, what never to do | `CONTEXT.md` → "Do / Don't" |
| `exceptionToStatus`, constraints, validation groups, bulk id constraints, status codes | `.ai/ERRORS.md` |
| Summaries, descriptions, examples, generator limits | `.ai/OPENAPI.md` |
| `PaginatedList` / `CQRSPaginate` / `CQRSGetCollection`, filters, envelope | `.ai/LISTS.md` |
| `shopIds`, shop context parameters, `[_context]` mappings | `.ai/MULTISHOP.md` |
| `minVersion`, `extraProperties`, features per core version | `.ai/VERSIONS.md` |
| The integration test | `.ai/TESTING.md` |
| The core PR, the reference branch, the PR description | `CONTEXT.md` → "Working with the core" |

External sources behind them: ADR 0023 (the contract) and the devdocs page
"Contribute to the Core API", both linked from `CONTEXT.md` → "Authoritative external
references".

## File placement

| File type | Path |
|-----------|------|
| ApiResource class | `src/ApiPlatform/Resources/{Domain}/{Entity}.php` (`{Entity}List.php`, `Bulk…{Entities}.php` next to it) |
| DTO sub objects | `src/ApiPlatform/Resources/{Domain}/Dto/{Name}.php` |
| Integration test | `tests/Integration/ApiPlatform/{Entity}EndpointTest.php` |
