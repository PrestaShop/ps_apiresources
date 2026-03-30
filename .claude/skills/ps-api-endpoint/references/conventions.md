# PrestaShop API Endpoint Naming & Structural Conventions

Source: https://devdocs.prestashop-project.org/9/admin-api/contribute-to-core-api/ and ADR-0023.

## URI conventions

- **Plural paths**: `/attributes/groups` not `/attribute/group`
- **kebab-case** for compound names: `/bulk-delete`, `/assign-to-category`
- **Domain identifier in URI parameter**: `attributeGroupId` not `id`
- Sub-resources use the parent ID: `/categories/{categoryId}/cover`

## HTTP method → CQRS operation mapping

| HTTP Method | Operation         | Use case                          |
|-------------|-------------------|-----------------------------------|
| GET         | `CQRSGet`         | Fetch single entity               |
| GET         | `CQRSGetCollection` / `PaginatedList` | Fetch list           |
| POST        | `CQRSCreate`      | Create entity                     |
| PATCH       | `CQRSPartialUpdate` | Update subset of fields (unspecified fields unchanged) |
| PUT         | `CQRSUpdate`      | Full update (all fields required) |
| DELETE      | `CQRSDelete`      | Delete entity (no response body)  |

## Scope naming

Format: `{entity_snake_case}_{action}`

- Single-word entity: `contact_read`, `contact_write`
- Multi-word entity: `attribute_group_read`, `attribute_group_write`, `tax_rule_read`
- Always `_read` for GET operations, `_write` for POST/PATCH/PUT/DELETE

## Property naming

| Rule | Correct | Wrong |
|------|---------|-------|
| Localized fields — no "localized" prefix | `$names` | `$localizedNames` |
| Boolean — no "is" prefix | `$enabled`, `$ready` | `$isEnabled`, `$isReady` |
| Status — use "enabled" not "active/status" | `$enabled` | `$active`, `$status` |
| All fields strictly typed | `public int $id` | `public $id` |
| ID field — domain name + "Id" | `$contactId` | `$id` |

## Multilanguage fields

- Single-entity responses: ALL languages, indexed by locale
  ```json
  { "names": { "en-US": "Color", "fr-FR": "Couleur" } }
  ```
- List responses: single language, as plain strings
- Use `#[LocalizedValue]` attribute on the property
- Use `#[DefaultLanguage(groups: ['Create'], fieldName: 'names')]` to require default lang on create
- Use `#[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]` for optional on update

## Field mapping conventions

### QUERY_MAPPING
Maps from query result field names to API response field names.
```php
public const QUERY_MAPPING = [
    '[localisedTitles]' => '[names]',   // query result key → API key
    '[shopAssociation]' => '[shopIds]',
];
```
Read the `{Entity}ForEditing.php` QueryResult class to find source field names (getter names minus "get", lowercased first letter).

### CQRSCommandMapping
Maps from API request field names to command constructor parameter names.
```php
public const CREATE_COMMAND_MAPPING = [
    '[names]' => '[localisedTitles]',   // API key → command param name
    '[shopIds]' => '[shopAssociation]',
];
```
If Create and Edit commands share the same param names, use a single `COMMAND_MAPPING`.

### Nested fields
Use bracket notation for nested paths:
```php
'[basicInformation][localizedNames]' => '[names]'
```

## Bulk operations

- URI prefix: `bulk-`, e.g. `/contacts/bulk-delete`
- Parameter: plural entity name + "Ids", e.g. `contactIds`, `attributeGroupIds`

## Forbidden practices (CI enforced)

1. **No custom normalizers** — use `#[LocalizedValue]`, `ApiResourceMapping`, `CQRSQueryMapping` instead
2. **No custom processors** — core processors handle standard CRUD flows
3. **No Value Objects as properties** — only scalar types (`int`, `string`, `bool`, `float`) and `array`

## Exception mapping

Always map at minimum:
```php
exceptionToStatus: [
    {Entity}ConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    {Entity}NotFoundException::class => Response::HTTP_NOT_FOUND,
]
```

Add `Response::HTTP_NOT_FOUND` for any custom "not found" exceptions (e.g. parent entity missing).

## File placement

| File type | Path |
|-----------|------|
| ApiResource class | `src/ApiPlatform/Resources/{Entity}/{Entity}.php` |
| Integration test | `tests/Integration/ApiPlatform/{Entity}EndpointTest.php` |
