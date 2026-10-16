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
| All fields strictly typed | `public int $contactId` | `public $contactId` |
| ID field — domain name + "Id" | `$contactId` | `$id` |
| API names, not database columns | `$firstName`, `$companyName` | `$firstname`, `$company` |
| Decimals — `DecimalNumber`, never `float` | `public DecimalNumber $price` | `public float $price` |
| Flags — real booleans | `public bool $enabled` | `public int $active` |

## Sub objects

A structured value is a typed DTO class under `src/ApiPlatform/Resources/{Entity}/Dto/`, typed on the
property (`public CustomerOrdersInformation $ordersInformation;`) or as a list with a docblock
(`/** @var CustomerOrderRow[] */ public array $validOrders;`). The OpenAPI schema is generated from it.
Never document an object with a hand-written `openapiContext` (`'type' => 'object', 'properties' => [...]`);
`openapiContext` is only for lists of scalars (`'items' => ['type' => 'integer']`). A DTO holding a list or
a boolean filled from a query result needs `minVersion: 9.2.1` (nested boolean cast and `@var Foo[]` item
types). A sub object fed to a multi-parameter setter of the command is nullable when the setter accepts
`null`, with every setter parameter `required` once the object is sent. Full rule: `CONTEXT.md` →
"Sub objects: typed DTOs".

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

### Indexed collections
Use the `@index` placeholder to map each element of a list:
```php
'[categoriesInformation][categoriesInformation][@index][id]' => '[categories][@index][categoryId]'
```
One level only. The mapper resolves a single placeholder at a time, so nesting
two `@index` levels silently maps nothing, reusing the same name twice throws,
and a list cannot be flattened or grouped — mapping renames and moves fields,
it never changes depth. This is what forces the read and write formats of a
resource to be reconcilable by mapping alone; see `CONTEXT.md` → "Read and
write formats must match".

## Bulk operations

- URI prefix: `bulk-`, e.g. `/contacts/bulk-delete`
- Parameter: plural entity name + "Ids", e.g. `contactIds`, `attributeGroupIds`

## Forbidden practices (CI enforced)

1. **No custom normalizers** — use `#[LocalizedValue]`, `ApiResourceMapping`, `CQRSQueryMapping` instead. Single exception, allowlisted case by case in `ApiResourceNormalizerRule::ALLOWED_CLASSES`: a reshaping the mapping provably cannot express (see `CONTEXT.md` → "When mapping cannot express the format")
2. **No custom processors** — core processors handle standard CRUD flows, and this one has no exception
3. **No Value Objects as properties** — only scalar types (`int`, `string`, `bool`) and `array`; decimals use `PrestaShop\Decimal\DecimalNumber`, never `float`

## Exception mapping

Always map at minimum:
```php
exceptionToStatus: [
    {Entity}ConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    {Entity}NotFoundException::class => Response::HTTP_NOT_FOUND,
]
```

Then read the command constructor, its value objects and the handler, and map every exception they can
raise: the value objects of the ids in the URI and in the body (`/x/0` passes `\d+` and must answer 422),
the referenced entities (`ShopException` 422, `ShopNotFoundException` 404, `ZoneNotFoundException` 404,
`CurrencyConstraintException` 422…). Any identifier checked for existence answers 404 when unknown, in
the URI or in the body: every `*NotFoundException` → 404. A subclass mapped to a different status than
its parent is listed first (API Platform takes the first matching class). Never map to 400.

## OpenAPI documentation

- `openapi: new OpenApiOperation(summary: ..., description: ...)` on command-style operations and on
  operations whose HTTP method does not say what they do (an upload forced to POST: both operations).
- A description for every non-obvious behaviour: version gates, display-formatted values, always-empty
  fields, side effects, what a "set" replaces, multishop coverage.
- `#[ApiProperty(description:, example:)]` when a property name is not self-explanatory; identifiers with
  `openapiContext: ['type' => 'integer', 'example' => 1]`; localized examples keyed by locale.
- Class comments are PHPDoc, not published: public text goes in `description` only.
- Check with `bin/console api:openapi:export --yaml` in the core, before/after, and a real call.

## Scopes and version

- Every operation declares `scopes` (PHPStan rule).
- `extraProperties: ['minVersion' => 'X.Y.Z']` when the CQRS class or behaviour does not exist on an older
  supported core (9.0.3, 9.1.5, 9.2.x, develop are tested), never to hide a 500; pass recent named
  arguments (`defaultValues`, `minVersion`…) through `extraProperties`.

## File placement

| File type | Path |
|-----------|------|
| ApiResource class | `src/ApiPlatform/Resources/{Entity}/{Entity}.php` |
| DTO sub objects | `src/ApiPlatform/Resources/{Entity}/Dto/{Name}.php` |
| Integration test | `tests/Integration/ApiPlatform/{Entity}EndpointTest.php` |
