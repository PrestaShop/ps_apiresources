# ps-api-endpoint skill

Generates new PrestaShop Admin API endpoints for the `ps_apiresources` module — specifically, an **ApiResource PHP class** and a matching **integration test** — following the module's CQRS patterns and contribution guidelines.

## Trigger

Claude invokes this skill automatically when you express intent to:

- Add a new REST endpoint to the Admin API
- Expose a new entity via the Admin API
- Contribute a new resource to `ps_apiresources`
- Wire up a CQRS command or query to an API endpoint in PrestaShop 9

Example phrases: `"I want to add TaxRule to the API"`, `"create an endpoint for Warehouse"`, `"how do I expose AttributeGroup via the Admin API?"`

## Prerequisites

Have the following ready before invoking:

1. **Entity name** — PascalCase singular (e.g. `TaxRule`, `Warehouse`)
2. **Operations** — which HTTP methods are needed: GET, POST, PATCH, DELETE, list (paginated), bulk operations
3. **PrestaShop core path** — absolute path to a local PS 9.x checkout (e.g. `/home/user/prestashop-90x`), so the skill can discover CQRS classes. You can skip this if you already know the CQRS class names.

## What it generates

| File | Path |
|------|------|
| ApiResource class | `src/ApiPlatform/Resources/{Entity}/{Entity}.php` |
| Integration test | `tests/Integration/ApiPlatform/{Entity}EndpointTest.php` |

The **ApiResource class** contains:
- `#[ApiResource]` attribute with all requested operations (`CQRSGet`, `CQRSCreate`, `CQRSPartialUpdate`, `CQRSDelete`, `PaginatedList`)
- Public properties with strict types and Symfony validation constraints
- `QUERY_MAPPING` — maps CQRS query result fields to API response fields
- `CREATE_COMMAND_MAPPING` / `UPDATE_COMMAND_MAPPING` (or a shared `COMMAND_MAPPING`) — maps API request fields to CQRS command parameters
- OAuth2 scopes (`{entity}_read`, `{entity}_write`) and exception-to-HTTP-status mappings

The **integration test** contains:
- Full CRUD test methods chained via `@depends`
- A `testInvalid*` method covering validation errors
- `getProtectedEndpoints()` verifying all endpoints require authentication
- `setUpBeforeClass`/`tearDownAfterClass` with table restoration via `DatabaseDump`

## Usage example

```
User:  I want to add a GET and POST endpoint for TaxRule.
       My PS core is at /home/dev/prestashop.

Claude: [invokes ps-api-endpoint skill]
        — Asks for confirmation of operations and fields
        — Reads /home/dev/prestashop/src/Core/Domain/TaxRule/...
        — Generates src/ApiPlatform/Resources/TaxRule/TaxRule.php
        — Generates tests/Integration/ApiPlatform/TaxRuleEndpointTest.php
        — Reports file locations and how to run the tests
```

## Skill file structure

```
.claude/skills/ps-api-endpoint/
├── README.md              # This file — human-facing orientation
├── SKILL.md               # Skill logic executed by Claude (step-by-step instructions,
│                          #   templates for ApiResource class and integration test)
└── references/
    └── conventions.md     # Naming and structural conventions (URI format, scope naming,
                           #   property rules, mapping patterns, forbidden practices)
```
