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
| `PaginatedList`      | GET    | A Grid data factory + Filters | List endpoint sourced from an admin Grid |
| `CQRSPaginate`       | GET    | A `…List` CQRS query + Filters | List endpoint sourced from a CQRS query (preferred when a Grid doesn't exist) |
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
| Decimals — use `DecimalNumber`, never `float` | `public DecimalNumber $price` | `public float $price`     |

Localized properties must be marked with `#[LocalizedValue]`. To require
the default language on create / update, add `#[DefaultLanguage]` with
the appropriate validation `groups`. See
`src/ApiPlatform/Resources/Contact/Contact.php` for the current usage
pattern.

## Field mapping

- `CQRSQueryMapping` (often defined as a `QUERY_MAPPING` constant) maps
  **query result field → API field**. Read the `Get{Entity}ForEditing`
  query result class in PrestaShop Core to find source field names.
- `CQRSCommandMapping` (often `CREATE_COMMAND_MAPPING` /
  `UPDATE_COMMAND_MAPPING`, or a shared `COMMAND_MAPPING` when both
  commands accept the same parameters) maps **API field → command
  constructor parameter**.
- `ApiResourceMapping` plays the same role for `PaginatedList` /
  `CQRSPaginate`, mapping Grid filter / data factory fields (or CQRS
  query result fields) to API fields. For `PaginatedList`, the source
  field names can often be read directly from the related query builder
  service wired via `gridDataFactory` — you only need to map the fields
  whose names don't already match the DTO.
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
  be scalar (`int`, `string`, `bool`) or `array`. The single allowed
  exception is `PrestaShop\Decimal\DecimalNumber`, which **must** be
  used instead of `float` for any decimal / monetary value — never
  `float`. This is checked by the CI.
- Don't return raw command results. If the endpoint should return data,
  define proper DTO properties and wire a `CQRSQuery`.
- Don't declare an operation without a scope.
- Don't use plural names for boolean / status fields, or `id` as a
  property name — see Property naming.

## Testing expectations

Every new endpoint requires an integration test. The test class lives at
`tests/Integration/ApiPlatform/{Entity}EndpointTest.php` and extends
`ApiTestCase` (same directory). Tests should focus on **API behaviour,
contract, and response format** — authentication and OAuth token setup
are handled automatically by the base class.

### Always use the `ApiTestCase` helper methods

Do not call `static::createClient()->request(...)` directly. Use the
helpers below — they build the request, request a Bearer token for the
given scopes (auto-creating an API client that holds them, cached
across tests), and assert the expected HTTP status code.

| Helper                    | HTTP   | Purpose                                       |
|---------------------------|--------|-----------------------------------------------|
| `getItem($url, $scopes)`  | GET    | Fetch a single item                           |
| `createItem($url, $data, $scopes)` | POST | Create an item                         |
| `partialUpdateItem($url, $data, $scopes)` | PATCH | Partial update                   |
| `updateItem($url, $data, $scopes)` | PUT | Full update                               |
| `deleteItem($url, $scopes)` | DELETE | Delete a single item                       |
| `bulkDeleteItems($url, $data, $scopes)` | DELETE | Bulk delete with a payload      |
| `listItems($url, $scopes, $filters)` | GET | Paginated list (asserts envelope)      |
| `countItems($url, $scopes, $filters)` | GET | Paginated list → totalItems only     |
| `requestApi(...)`         | any    | Last resort for cases the others don't fit    |

Each helper also accepts an `$expectedHttpCode` parameter when asserting
a specific status (e.g. `HTTP_UNPROCESSABLE_ENTITY` on invalid payloads,
`HTTP_NOT_FOUND` after a delete).

### Default test environment

`ApiTestCase::setUpBeforeClass()` already:

- Forces `PS_ADMIN_API_FORCE_DEBUG_SECURED = 0`
- Resets API clients and languages
- **Installs `fr-FR` as a second language** so every endpoint is tested
  against a multi-language environment

Because of this, test data for localized fields should include both
`en-US` and `fr-FR` values — otherwise the test won't exercise the
multi-language behaviour the module is expected to handle.

Static helpers available for additional setup (call from
`setUpBeforeClass` when needed):

- `addLanguageByLocale($locale)` — install another language
- `addShopGroup($name, $color?)` / `addShop($name, $groupId, $color?)`
  — build a multi-shop fixture
- `updateConfiguration($key, $value, $shopConstraint?)` — override a
  configuration value for the test
- `createApiClient($scopes)` — only needed in special cases;
  `getBearerToken` already creates one on the fly for each scope set

### Test structure

- Implement `getProtectedEndpoints()` listing every operation that
  requires authentication so the base class can verify scope
  enforcement via `testProtectedEndpoints`.
- Chain CRUD test methods with `@depends` so each step builds on the
  previous one's created entity.
- Always include a `testInvalid…` method covering validation errors
  (expecting `HTTP_UNPROCESSABLE_ENTITY`) and use
  `$this->assertValidationErrors([...], $response)` to check the error
  shape.
- Restore relevant DB tables via `DatabaseDump::restoreTables([...])`
  in `setUpBeforeClass` / `tearDownAfterClass`.

Run the suite with:

```bash
composer setup-local-tests   # first time only
composer run-module-tests
```

## Canonical examples

Read these before adding new endpoints — they are the source of truth
for the patterns above:

- **Full CRUD on a localized entity** —
  `src/ApiPlatform/Resources/Contact/Contact.php`
  (`CQRSGet`, `CQRSCreate`, `CQRSPartialUpdate` with `LocalizedValue`,
  `DefaultLanguage`, `QUERY_MAPPING`, `CREATE_COMMAND_MAPPING`,
  `exceptionToStatus`).
- **Grid-sourced paginated list** —
  `src/ApiPlatform/Resources/Contact/ContactList.php`
  (`PaginatedList`, `gridDataFactory`, `filtersClass`,
  `ApiResourceMapping`).
- **CQRS-sourced paginated list** —
  `src/ApiPlatform/Resources/Product/CombinationList.php`
  (`CQRSPaginate` with `CQRSQuery`, `CQRSQueryMapping`,
  `ApiResourceMapping`, `itemsField`, `countField`, and `DecimalNumber`
  properties).
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
- Core — `PrestaShopBundle/ApiPlatform` (the infrastructure this module
  plugs into):
  <https://github.com/PrestaShop/PrestaShop/tree/9.1.x/src/PrestaShopBundle/ApiPlatform>
- Core — custom operation attributes (`CQRSGet`, `CQRSCreate`,
  `CQRSPartialUpdate`, `CQRSUpdate`, `CQRSDelete`, `PaginatedList`,
  `CQRSPaginate`, `CQRSGetCollection`, …) and related metadata:
  <https://github.com/PrestaShop/PrestaShop/tree/9.1.x/src/PrestaShopBundle/ApiPlatform/Metadata>
</content>
</invoke>