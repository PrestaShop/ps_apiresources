---
name: ps-api-endpoint
description: >
  Generates or reworks PrestaShop Admin API endpoints (ApiResource PHP class, DTO sub objects, integration test) in the ps_apiresources module, following the CQRS patterns and the module conventions. Invoke it whenever the user wants to add a REST endpoint, expose an entity via the Admin API, contribute a resource to ps_apiresources, fix or modify an existing resource (fields, mappings, errors, OpenAPI documentation, DTO migration), or asks how to wire a CQRS command/query to an endpoint in PrestaShop 9. Use it even when the user just says "I want to add X to the API", "how do I create an endpoint for Y" or "fix the Z endpoint".
---

# PrestaShop Admin API Endpoint Generator

This skill walks through creating a new endpoint in `ps_apiresources`, from discovery to the written files.
The conventions are written once, in `.ai/CONTEXT.md` and its on-demand files; each step below names
the section to read, and this skill never summarizes it. The files are started from the canonical
resources and tests the context names, never from a blank template: a template drifts, a canonical file
runs in CI.

## Step 1: Gather requirements

Ask the user (all in one message if not already specified):

1. **Entity name** — e.g. `TaxRule`, `Warehouse` (PascalCase, singular)
2. **Operations needed** — GET single, POST (create), PATCH (partial update), PUT (full update), DELETE, GET list (paginated). Also ask about bulk operations (e.g. bulk delete) or custom sub-resource actions (e.g. status toggle).
3. **PrestaShop core path** — absolute path to the PS root, e.g. `/home/user/prestashop-90x`. Needed to look up CQRS classes. Offer to skip this step if the user already knows the class names.
4. **Minimum core version** — does the endpoint rely on CQRS classes or core behaviour only available (or only working) since a specific PrestaShop version? If so, the operations declare `minVersion` (`.ai/VERSIONS.md`) and the test skips accordingly (`.ai/TESTING.md` → "Version-dependent endpoints").

## Step 2: Discover CQRS classes

Search the PS core for the entity's domain classes. The standard layout is:

```
{PS_ROOT}/src/Core/Domain/{Entity}/
  Command/Add{Entity}Command.php
  Command/Edit{Entity}Command.php
  Command/Delete{Entity}Command.php
  Command/BulkDelete{Entity}Command.php
  Query/Get{Entity}ForEditing.php
  Query/Get{Entity}ListForEditing.php     ← may not exist; Grid is used for lists
  QueryResult/{Entity}ForEditing.php      ← shows what fields the query returns
  Exception/{Entity}NotFoundException.php
  Exception/{Entity}ConstraintException.php
```

Use Glob/Grep to find these files. Then **read the QueryResult class** (e.g. `{Entity}ForEditing.php`) — its constructor arguments and getters reveal the exact field names returned by the query. This is the ground truth for `QUERY_MAPPING`. Read the query handler too: it tells what really fills each field (a `0`/`1` tiny int, a formatted amount, an always-empty array) and which exceptions it throws.

Also read the Command constructors and setters (multi-parameter setters become one sub object) to learn the parameter names needed for `CQRSCommandMapping`, and the value objects they build (`{Entity}Id`, `ShopId`…): their exceptions must be mapped in `exceptionToStatus`. Finally read the handler of each command for the exceptions of the referenced entities (`ZoneNotFoundException`, `ShopNotFoundException`…).

When the core lacks something the endpoint needs (a guard, a setter, a typed query result), the fix goes to the core in a related PR (`CONTEXT.md` → "Working with the core"), never in the module.

If some of these classes don't exist (e.g. no Add command), note it — only include operations that have backing CQRS classes.

## Step 3: Gather field information

After reading the QueryResult, confirm with the user:
- Which fields should be exposed in the API (not all internal fields need to be public)
- Which fields are **structured** (an object, a list of objects) → a typed DTO class (`CONTEXT.md` → "Sub objects: typed DTOs")
- Which fields are **localized**, and which of them are required in the default language on create or optional on update (`CONTEXT.md` → "Property naming", the `#[LocalizedValue]` and `#[DefaultLanguage]` paragraphs)
- Which fields have a default on creation (`CONTEXT.md` → "Do", `defaultValues`)
- Whether the entity has a shop association (`.ai/MULTISHOP.md`)

## Step 4: Write the ApiResource class

**File location:** `src/ApiPlatform/Resources/{Domain}/{Entity}.php`

Copy the canonical resource that matches the feature (`CONTEXT.md` → "Canonical examples": `Contact`
for a CRUD on a localized entity, `CustomerGroup` for a shop association, `CustomerDetails` for DTO sub
objects, the others for lists, bulk operations, command-style operations and uploads), then adapt it
with the sections of the context: the operations and their method (`CONTEXT.md` → "Choosing the
operation"), the URIs and scopes ("URI conventions", "Scopes"), the properties ("Property naming",
"Sub objects: typed DTOs"), the mappings ("Field mapping"), the exceptions and constraints
(`.ai/ERRORS.md`), the summaries and descriptions (`.ai/OPENAPI.md`). Remove what the copied resource
has and the new one does not (operations, mappings, attributes, options): nothing is kept because the
original has it.

A list lives in its own `{Entity}List.php` next to the resource (`.ai/LISTS.md`); a bulk operation in its
own `Bulk{Entities}.php` (`BulkDelete{Entities}.php`, `BulkUpdateStatus{Entities}.php` when more
specific), with the shape of `CONTEXT.md` → "URI conventions" and the constraints of `.ai/ERRORS.md`.

## Step 5: Write the integration test

**File location:** `tests/Integration/ApiPlatform/{Entity}EndpointTest.php`

Copy the canonical test `.ai/TESTING.md` → "Canonical tests" names and adapt it with the checklist of
that section. What the test must cover (whole-response assertions, both languages on every localized
field, fixtures through the API, the error cases, the lists, the version skips) and the helpers of
`ApiTestCase` are in `.ai/TESTING.md`; the parent class already prepares the languages and the API
clients, the test class never repeats that setup.

## Step 6: Write the files

Create both files at their correct paths. Then tell the user:

1. **Where the files were written**
2. **How to register the resource** — if the ApiResource isn't auto-discovered, a service definition may be needed (usually not required for standard resources)
3. **How to run the tests:**
   ```bash
   composer setup-local-tests  # first time only
   composer run-module-tests
   ```
4. **What to double-check**: mappings between CQRS fields and API fields — these are the most common source of errors. Ask the user to run the endpoint manually via Swagger UI to verify, and to export the OpenAPI documentation (`bin/console api:openapi:export --yaml` in the core) to compare the generated schema with the real payloads (types, nullability, `required` list, descriptions).
5. **Review it**: run the `review-api-pr` skill (`REVIEW.md`) on the new files before opening the PR.

## Reference files

- `../../CONTEXT.md` (`.ai/CONTEXT.md`) — the conventions, with the on-demand `.ai/*.md` contexts (`TESTING.md`, `OPENAPI.md`, `LISTS.md`, `ERRORS.md`, `MULTISHOP.md`, `VERSIONS.md`); ADR 0023 and the devdocs are linked from its "Authoritative external references"
