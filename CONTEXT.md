# ps_apiresources — AI Context

This file is the AI-agnostic source of truth for working in the
`ps_apiresources` module. Tool-specific pointer files at the repo root
(`CLAUDE.md`, `AGENTS.md`, `GEMINI.md`, `.cursor/rules/*`,
`.github/copilot-instructions.md`, `.windsurf/rules/*`) all defer here.

## Purpose

`ps_apiresources` declares the PrestaShop **Admin API** endpoints. It is
**purely declarative**: no business logic lives here. Each endpoint is a
plain PHP class decorated with `#[ApiResource]` whose operations delegate
to PrestaShop Core CQRS commands and queries. The module is scanned by
the PrestaShop Core at runtime; there is no controller layer to write.

If you find yourself adding business logic here, stop — it belongs in
PrestaShop Core, not in this module.

All resources live under `src/ApiPlatform/Resources/{Domain}/`.

## Architecture overview

A resource class binds an HTTP operation to a CQRS class from the Core,
using one of the operation attributes provided by `PrestaShopBundle`:

| Attribute            | HTTP   | Wraps                        | Use case                                  |
|----------------------|--------|------------------------------|-------------------------------------------|
| `CQRSGet`            | GET    | A `Get…ForEditing` query     | Fetch a single entity                     |
| `CQRSCreate`         | POST   | An `Add…Command`             | Create a new entity                       |
| `CQRSPartialUpdate`  | PATCH  | An `Edit…Command`            | Update a subset of fields                 |
| `CQRSUpdate`         | PUT    | An `Edit…Command`            | Full update (all fields required)         |
| `CQRSDelete`         | DELETE | A `Delete…Command`           | Delete an entity (no response body)       |
| `PaginatedList`      | GET    | A Grid data factory + Filters | List endpoint with pagination & filters  |
| `CQRSGetCollection`  | GET    | A `…Query` returning a list  | Non-paginated collection                  |

Each operation declares:

- A `uriTemplate` (kebab-case, plural — see below)
- One or more OAuth2 `scopes` required to call it
- Field mappings (`CQRSQueryMapping`, `CQRSCommandMapping`,
  `ApiResourceMapping`) when DTO field names differ from CQRS field names
- An `exceptionToStatus` map at the class level so domain exceptions are
  translated into proper HTTP responses

Single-entity, list, and bulk operations are split across **separate
classes** in the same domain folder. For example:

```
src/ApiPlatform/Resources/Contact/
├── Contact.php          ← CQRSGet / CQRSCreate / CQRSPartialUpdate / CQRSDelete
└── ContactList.php      ← PaginatedList

src/ApiPlatform/Resources/Attribute/
├── AttributeGroup.php
├── AttributeGroupList.php
└── BulkAttributeGroups.php  ← bulk-delete operation
```

## URI conventions

- **Plural, lowercase, kebab-case**: `/contacts`, `/attributes/groups`,
  `/customer-groups` — never `/contact`, `/attributeGroups`, or
  `/customerGroups`.
- **Sub-resources** nest under the parent ID:
  `/products/{productId}/combinations`,
  `/categories/{categoryId}/cover`.
- **Bulk operations** use the `bulk-` prefix: `/contacts/bulk-delete`,
  `/attributes/groups/bulk-delete`.
- **URI parameters** use the domain identifier name, not `id`:
  `{contactId}`, `{attributeGroupId}`, `{taxRuleId}`.
- The DTO property exposed as the identifier must match the URI
  parameter and be marked `#[ApiProperty(identifier: true)]`.

## Scopes

OAuth2 scope naming: `{entity_snake_case}_{action}`.

- Single-word entity: `contact_read`, `contact_write`
- Multi-word entity: `attribute_group_read`, `tax_rule_write`
- `_read` for GET operations, `_write` for POST / PATCH / PUT / DELETE
- Every operation must declare at least one scope. There are no
  unscoped endpoints.

## Property naming

| Rule                                          | Correct                  | Wrong                          |
|-----------------------------------------------|--------------------------|--------------------------------|
| Identifier — domain name + `Id`               | `$contactId`             | `$id`                          |
| Boolean — no `is` prefix                      | `$enabled`, `$ready`     | `$isEnabled`, `$isReady`       |
| Status uses `enabled`, not `active` / `status`| `$enabled`               | `$active`, `$status`           |
| Localized fields — no `localized` prefix      | `$names`                 | `$localizedNames`              |
| All public properties strictly typed          | `public int $contactId`  | `public $contactId`            |

Localized properties must be marked with `#[LocalizedValue]`. To require
the default language on create / update, add `#[DefaultLanguage]` with
the appropriate validation `groups` and `fieldName` set to the API field
name (not the internal CQRS field name).

## Field mapping

- `CQRSQueryMapping` (often defined as a `QUERY_MAPPING` constant) maps
  **query result field → API field**. Read the `Get{Entity}ForEditing`
  query result class in PrestaShop Core to find source field names.
- `CQRSCommandMapping` (often `CREATE_COMMAND_MAPPING` /
  `UPDATE_COMMAND_MAPPING`, or a shared `COMMAND_MAPPING` when both
  commands accept the same parameters) maps **API field → command
  constructor parameter**.
- `ApiResourceMapping` plays the same role for `PaginatedList`, mapping
  Grid filter / data factory fields to API fields.
- Nested fields use bracket notation:
  `'[basicInformation][localizedNames]' => '[names]'`.

## Do / Don't

### Do

- Define `exceptionToStatus` for every domain exception the operations
  can throw — at minimum the entity's `…ConstraintException` →
  `HTTP_UNPROCESSABLE_ENTITY` and `…NotFoundException` → `HTTP_NOT_FOUND`.
- Use a `CQRSQuery` on `CQRSCreate` and `CQRSPartialUpdate` when the
  endpoint should return the full updated state, not just an identifier.
- Use `#[LocalizedValue]` for any field stored as `array<locale, value>`.
- Split single / list / bulk operations into separate classes inside the
  same domain folder.
- Add an integration test for every new endpoint (see Testing below).

### Don't

- Don't add business logic in a resource class. Delegate to a Core CQRS
  command or query — always.
- Don't write custom normalizers or custom processors. Use
  `#[LocalizedValue]`, `ApiResourceMapping`, `CQRSQueryMapping`, and
  `CQRSCommandMapping` instead. CI enforces this.
- Don't expose Value Objects as DTO properties. Public properties must
  be scalar (`int`, `string`, `bool`, `float`) or `array`.
- Don't return raw command results. If the endpoint should return data,
  define proper DTO properties and wire a `CQRSQuery`.
- Don't declare an operation without a scope.
- Don't use plural names for boolean / status fields, or `id` as a
  property name — see Property naming.

## Testing expectations

- Every new endpoint requires an integration test.
- Test class location:
  `tests/Integration/ApiPlatform/{Entity}EndpointTest.php`
- Test class extends `ApiTestCase` (in the same directory).
- Use `setUpBeforeClass` / `tearDownAfterClass` to seed the API client
  with the right scopes and to restore the relevant DB tables via
  `DatabaseDump::restoreTables([...])`.
- Implement `getProtectedEndpoints()` listing every operation that
  requires authentication so the base class can verify scope enforcement.
- Chain CRUD test methods with `@depends` so each step builds on the
  previous one's created entity.
- Always include a `testInvalid…` method covering validation errors
  (expecting `HTTP_UNPROCESSABLE_ENTITY`).
- For entities with localized fields: call
  `LanguageResetter::resetLanguages()` and seed `fr-FR` in
  `setUpBeforeClass` / `tearDownAfterClass`.

Run the suite with:

```bash
composer setup-local-tests   # first time only
composer run-module-tests
```

## Canonical examples

Read these before adding new endpoints — they are the source of truth
for the patterns above:

- **Full CRUD on a simple entity** —
  `src/ApiPlatform/Resources/Contact/Contact.php`
  (`CQRSGet`, `CQRSCreate`, `CQRSPartialUpdate` with `LocalizedValue`,
  `QUERY_MAPPING`, `CREATE_COMMAND_MAPPING`, `exceptionToStatus`).
- **Full CRUD with multiple scopes** —
  `src/ApiPlatform/Resources/ApiClient/ApiClient.php`.
- **Paginated list** —
  `src/ApiPlatform/Resources/Contact/ContactList.php`
  (uses `PaginatedList`, `gridDataFactory`, `filtersClass`,
  `ApiResourceMapping`).
- **Sub-resource** —
  `src/ApiPlatform/Resources/Product/ProductCombination.php`.
- **Bulk operation** —
  `src/ApiPlatform/Resources/Attribute/BulkAttributeGroups.php`
  (URI `/attributes/groups/bulk-delete`, `attributeGroupIds` input).
- **Matching test** —
  `tests/Integration/ApiPlatform/ContactEndpointTest.php`.

## Coding standards

- Coding style is enforced by `php-cs-fixer` (`.php-cs-fixer.dist.php`).
- All resource classes live in the namespace
  `PrestaShop\Module\APIResources\ApiPlatform\Resources\{Domain}`.
- Every file must carry the AFL 3.0 license header used across the
  module (run `composer header-stamp-fix` if missing).
- Static analysis: `composer phpstan` (requires `_PS_ROOT_DIR_` and
  `_PS_BRANCH_` env vars).
- Automated refactor / upgrade rules: `composer rector`.

## Related skills

- `.claude/skills/ps-api-endpoint/` — Claude Code skill that walks
  through creating a new endpoint end-to-end (resource class +
  integration test) following the conventions above.
  See `.claude/skills/ps-api-endpoint/SKILL.md` and
  `.claude/skills/ps-api-endpoint/references/conventions.md`.

## Authoritative external references

- Module repository: <https://github.com/PrestaShop/ps_apiresources>
- Devdocs — Contribute to the Core API:
  <https://devdocs.prestashop-project.org/9/admin-api/contribute-to-core-api/>
- Devdocs — API Platform & CQRS integration:
  <https://devdocs.prestashop-project.org/9/admin-api/resource_server/api-platform/>
</content>
</invoke>