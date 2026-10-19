# ps_apiresources — AI Context

This file is the AI-agnostic source of truth for working in the
`ps_apiresources` module: the conventions that are always true. The
tool-specific touch points at the repo root (`CLAUDE.md`, `AGENTS.md`,
`GEMINI.md`, `.cursor/rules/*`, `.github/copilot-instructions.md`,
`.windsurf/rules/*`) all defer here. The details that only matter in some
situations live next to it and are read on demand:

| File | Read when |
|------|-----------|
| [`TESTING.md`](./TESTING.md) | writing or reviewing an integration test (`tests/Integration/**`) |
| [`OPENAPI.md`](./OPENAPI.md) | adding or changing a property, an operation, a description or an example — the generated documentation is part of the contract |
| [`LISTS.md`](./LISTS.md) | the operation is a `PaginatedList`, a `CQRSPaginate` or a `CQRSGetCollection` |
| [`ERRORS.md`](./ERRORS.md) | touching `exceptionToStatus`, a constraint or a validation group, a bulk operation, or when an endpoint answers an unexpected status |
| [`MULTISHOP.md`](./MULTISHOP.md) | the entity has a shop association (`shopIds`) or the command / query takes a shop id or a `ShopConstraint` |
| [`VERSIONS.md`](./VERSIONS.md) | a CQRS class or behaviour is missing or different on a supported core (`minVersion`, features per core version) |
| [`REVIEW.md`](./REVIEW.md) | reviewing a pull request or auditing an existing endpoint |

The core side of the Admin API (providers, processors, normalizers,
OpenAPI adapters, test environment) is documented in the PrestaShop
repository: `.ai/Component/AdminAPI/CONTEXT.md`.

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
| `UpdatePosition`     | PATCH  | A `PositionCollection` + `positionDefinition` | Reorder entities (`/x/positions`) |

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

A domain whose back office lists the entity through a grid ships its
`{Entity}List` in the same PR, or the PR description says why not.

### Choosing the operation

The method of each operation is set by ADR 0023 (§HTTP methods, §Custom
operations); the choice between them follows the semantics of the command,
not the other way round:

- `CQRSCreate` / `POST` for an `Add…Command` and for any non-idempotent
  action; a `POST` URI never carries the identifier of the entity it
  creates. A duplication is the one `POST` with an id in the URI, the id
  of the source: `POST /discounts/{discountId}/duplicate` with
  `CQRSCreate` (ADR 0023).
- `CQRSPartialUpdate` / `PATCH` only when the command really supports
  partial data (setters, nullable parameters): a command whose getters
  are non-nullable cannot be patched. `CQRSUpdate` / `PUT` when every
  parameter must be sent on each call, even on an endpoint that only
  covers part of the entity: `/carts/{cartId}/addresses` is a `PUT`
  because `UpdateCartAddressesCommand` requires both addresses.
- `CQRSDelete` / `DELETE` for a deletion and for a destructive clear-all;
  it answers 204. A bulk delete sets `allowEmptyBody: false` (the
  `CQRSDelete` default is `true`): a required body is never declared
  optional.
- Body-less actions answer 204 with `read: false`, `output: false` and
  `allowEmptyBody: true`, only when there is nothing to read back; every
  other write returns the updated state through a `CQRSQuery` (see the
  Do list).
- An operation receiving files forces `method: CQRSUpdate::METHOD_POST`
  (PHP only parses multipart on POST), with `status: Response::HTTP_OK`
  when it updates, and an explicit `openapi` summary; see `ProductImage`.
- Collections: `PaginatedList` on a grid, `CQRSPaginate` on a CQRS list
  query, `CQRSGetCollection` only for a plain array — see
  [`LISTS.md`](./LISTS.md).

## URI conventions

Set by ADR 0023 (§URI path and parameters conventions: the plural domain
word as the base, `{domain}Id` identifiers, sub parts appended after the
entity id, kebab-case actions); the module adds the rules below. The
endpoints shipped before the ADR amendments that deviate from it are
listed at the end of this section: a new endpoint follows the ADR, an
existing deviation is never a precedent.

- **Plural, lowercase, kebab-case**: `/contacts`, `/attributes/groups`,
  `/customer-groups` — never `/contact`, `/attributeGroups`, or
  `/customerGroups`.
- **Sub-resources** nest under the parent ID:
  `/products/{productId}/combinations`,
  `/categories/{categoryId}/cover`. A segment is singular only for a
  one-to-one sub-resource (`/products/{productId}/stock`,
  `/default-supplier`, `/virtual-file`), plural otherwise.
- **No HTTP verb in a URI**: `DELETE /categories/{categoryId}/cover`, not
  `/delete-cover`; search endpoints are `/x/search` on a `Found{Entity}`
  resource.
- **Identifiers in the URI** carry `requirements: ['entityId' => '\d+']`
  (documented as integers, non-numeric values answer 404).
- **Bulk operations** use the `bulk-` prefix: `/contacts/bulk-delete`,
  `/attributes/groups/bulk-delete`. The bulk parameter name must be
  plural domain + `Ids` (e.g. `attributeGroupIds`). The method follows
  ADR 0023 §Bulk operations: `DELETE` + `CQRSDelete` for `bulk-delete`,
  `PUT` + `CQRSUpdate` for `bulk-update-status` (the value in the body,
  never one endpoint per value), `POST` for `bulk-duplicate` and for any
  bulk action no method fits.
- **Command-style operations** that are not plain CRUD on the resource name
  the action explicitly, verb first: `/carriers/{carrierId}/set-tax-rule-group`,
  `/tax-rules-groups/{taxRulesGroupId}/set-status`,
  `/carriers/search-compatible-carriers`,
  `/employees/send-password-reset-email`. Prefer a name that says what the
  operation does for its caller over a bare noun (`search-compatible-carriers`
  over `available`). The same kind of action uses the same word in every
  domain (`set-status`, never `status` / `is-enabled` / `active`),
  `toggle-…` only when no value is sent, and a status endpoint is not
  added when `PATCH` already covers `enabled`. Any such segment must be
  listed in `ApiResourceUriTemplateRector::SKIPPED_KEYWORDS`, otherwise
  the Rector CI job pluralizes it (`set-tax-rule-group` →
  `set-tax-rule-groups`). Never a fake plural sub-collection for an
  action (`/employees/password-resets`).
- **URI parameters** use the domain identifier name, not `id`:
  `{contactId}`, `{attributeGroupId}`, `{taxRuleId}`.
- The DTO property exposed as the identifier must match the URI
  parameter and be marked `#[ApiProperty(identifier: true)]`.

### Existing endpoints that deviate from ADR 0023

Shipped before the ADR amendments of 2025-11 and 2025-12. They stay until
the team decides their migration (BC break in 0.x or deprecation until
1.0, an open question of the AI context PR); a review does not flag them,
and a new endpoint never copies them.

| Endpoint | ADR 0023 |
|----------|----------|
| `PUT /manufacturers/bulk-delete`, `PUT /taxes/bulk-delete` (`CQRSUpdate`) | `DELETE` + `CQRSDelete` |
| `PATCH /discounts/bulk-update-status` (`CQRSPartialUpdate`) | `PUT` + `CQRSUpdate` |
| `PUT /taxes/bulk-set-status`; `PUT /customers/bulk-enable` + `bulk-disable`; `PUT /suppliers/bulk-enable` + `bulk-disable` | one `PUT /x/bulk-update-status`, the value in the body |
| `PUT /modules/bulk-uninstall` | `POST` when no method fits |
| `/attributes/groups`, `/attributes/attributes` (sub-entities grouped under the domain root) | example `/attribute-groups`, older than the grouping |
| `PATCH /categories/{categoryId}/status`, `PUT /stores/{storeId}/toggle-status`, `PUT /zones/{zoneId}/toggle-status`, `PATCH /tax-rules-groups/{taxRulesGroupId}/set-status`, `/hooks/{hookId}/status` | example sub part `/hooks/{hookId}/status`; the `set-status` rule above comes from the reviews of 2026-03, the ADR is not amended yet |

## Scopes

OAuth2 scope naming (ADR 0023 §Scope names): `{entity_snake_case}_{action}`.

- Single-word entity: `contact_read`, `contact_write`
- Multi-word entity: `attribute_group_read`, `tax_rule_write`
- `_read` for GET operations, `_write` for POST / PATCH / PUT / DELETE
- Every operation must declare at least one scope (`scopes` argument or
  `extraProperties['scopes']`). There are no unscoped endpoints: the
  `ApiResourceOperationScopesRule` PHPStan rule fails the CI otherwise,
  and an unscoped operation would be reachable by any API client. The
  core UI tests (`tests/UI/campaigns/functional/API/`) request the scope
  of every endpoint they call.

## Core versions

The module runs on PrestaShop 9.0.3, 9.1.5, 9.2.x and develop. An
endpoint relying on a CQRS class or a behaviour that only exists from a
given core declares `extraProperties: ['minVersion' => 'X.Y.Z']`; the
operation is then filtered out (404, absent from the documentation) on
older cores. `minVersion` documents a real dependency, never hides a 500
on an older core. The named operation arguments a core does not know
(`minVersion`, `defaultValues`…) are passed through `extraProperties`.
Details, limits and the features available per core version:
[`VERSIONS.md`](./VERSIONS.md).

## Property naming

Set by ADR 0023 (§API resources properties: typed fields, no `localized`
or `is` prefix, `enabled` for a status, the mappings instead of
`SerializedName`); the module adds the rows that follow.

| Rule                                          | Correct                  | Wrong                          |
|-----------------------------------------------|--------------------------|--------------------------------|
| Identifier — domain name + `Id`, with `#[ApiProperty(identifier: true)]` | `$contactId` | `$id` |
| Boolean — no `is` prefix                      | `$enabled`, `$ready`     | `$isEnabled`, `$isReady`       |
| Status uses `enabled`, not `active` / `status`| `$enabled`               | `$active`, `$status`           |
| Localized fields — no `localized` prefix      | `$names`                 | `$localizedNames`              |
| All public properties strictly typed          | `public int $contactId`  | `public $contactId`            |
| Decimals — use `DecimalNumber`, never `float` | `public DecimalNumber $price` | `public float $price`     |
| Dates — `DateTimeImmutable` is allowed        | `public DateTimeImmutable $dateAdd` | `public string $dateAdd`  |
| API names, never database columns             | `$customerId`, `$firstName`, `$companyName`, `$defaultGroupId` | `$id_customer`, `$firstname`, `$company` |
| Flags are real booleans                       | `public bool $enabled`   | `public int $active` (a `0`/`1` tiny int) |
| Legacy rows flattened in an array — rebuilt in camelCase with `@index` mappings | `'[rows][@index][id_order]' => '[orders][@index][orderId]'` | `id_order` leaking in the payload |
| Response keys are the create payload keys     | `names` in, `names` out  | `localizedNames` in, `names` out |

A nullable property is nullable only when the core can return `null`
for it (check the handler, not just the query result signature). A
resource with nullable fields returns them as `null` rather than
omitting them: `normalizationContext: ['skip_null_values' => false]`.

Lists of scalars are typed with a docblock, `@var int[]` / `@var string[]`:
the core reads the item type from it (from 9.2.1; older cores document
a plain array). `#[ApiProperty(openapiContext: [...])]` is only added for
an `example`. Anything structured is a typed DTO (next section), never a
hand-written object schema. Every field the query or the grid returns is
exposed, or its omission is a conscious decision: integrators need the
identifiers (`{entity}Id`, nested ids, `shopIds`).

Localized properties must be marked with `#[LocalizedValue]` — this
triggers automatic locale↔languageId conversion on both read and write.
Single-entity endpoints return localized fields as locale-indexed arrays
(e.g. `{"names": {"en-US": "Size", "fr-FR": "Taille"}}`); list endpoints
return a single language string (default shop language, or via `langId`
query param). Never return language IDs as keys — always locale strings
like `en-US`.

To require the default language on create / update, add
`#[DefaultLanguage]` with the appropriate validation `groups`. Set the
`fieldName` argument to the **API field name** so the validation error
message includes a meaningful field reference (the auto-detection
fallback only works in Form contexts, not on ApiResource attributes).
Use `allowNull: true` on the Update group so the field is optional on
partial update. See `src/ApiPlatform/Resources/Contact/Contact.php` for
the current usage pattern.

## Sub objects: typed DTOs

A property that holds an object or a list of objects is typed with a
DTO class of the domain, under `src/ApiPlatform/Resources/{Domain}/Dto/`:

```php
public CustomerOrdersInformation $ordersInformation;

/**
 * @var CustomerOrderRow[]
 */
public array $validOrders;
```

- The DTO is a plain class with public typed properties: scalars,
  `DecimalNumber`, `DateTimeImmutable`, nested DTOs, `@var Foo[]` lists.
  No attribute is needed for the schema, a class PHPDoc is welcome.
- The schema is generated from the DTO (types, nullability, nested
  schemas), goes through the same OpenAPI adapters as the resource, and
  stays in sync with the payload. The `ApiResourcePropertyTypeRule`
  PHPStan rule accepts DTO types from a `Resources\{Domain}\Dto`
  namespace.
- Mapping and casting apply inside DTOs: `CQRSQueryMapping` renames
  nested fields (`[ordersInformation][validOrders][@index][id_order]` →
  `[...][orderId]`), the core casts the booleans of nested objects and
  of `@var Foo[]` lists from 9.2.1 — a DTO holding a list or a boolean
  filled from a query result needs `minVersion: 9.2.1` (the `@var Foo[]`
  item type is also read from 9.2.1 only).
- Hand-written `openapiContext` object schemas (`'type' => 'object',
  'properties' => [...]`) are the legacy form: existing resources are
  migrated to DTOs domain by domain, new resources never use them.
- A sub object fed to a **multi-parameter setter** of the command
  (`setMinimumAmount(?DecimalNumber $amount, int $currencyId, bool $taxIncluded, ...)`)
  is one object `{amount, currencyId, taxIncluded}`. When the setter
  accepts `null` (unset), the property is nullable and documented as
  `['object', 'null']`; once the object is sent, every parameter without
  a default is `required` — `currencyId` included when `0` throws.
  `minimumAmount: null` unsets the amount, `{"amount": null}` is invalid.

Real example: `src/ApiPlatform/Resources/Customer/CustomerDetails.php`
with `src/ApiPlatform/Resources/Customer/Dto/`.

## Field mapping

- Mappings are resolved **by name**: a query-result getter `getX()` /
  `isX()` fills the property `x`, a payload field `x` fills the
  constructor parameter or setter `x`. Only the fields whose names
  differ need an entry; identity entries (`'[name]' => '[name]'`),
  entries for fields that no longer exist, and options written at their
  framework default on a `CQRS*` operation (`read`, `output`, `provider`,
  `processor`, `experimentalOperation`; `allowEmptyBody` defaults to
  `true` on `CQRSDelete` and to `false` on the other write operations)
  are noise to remove — copy the block of `Contact` / `Title`, not an
  older resource.
- A write property must be a constructor parameter or a setter of the
  command (through the mapping when the name differs): a property the
  command cannot receive is silently dropped; a value only reachable
  through a named constructor or a private setter needs a core change.
  A read property must come from the query result: a property without
  source is always `null`. Path and query parameters must really reach
  the query constructor; a handler reading `$request->query` cannot be
  exposed as is. Multi-parameter setters get a nested mapping and a
  partial update sends their parameters together.
- A mapping bridges a name the core cannot change any more. When the
  CQRS class names a field differently from the rest of the core (grid
  column, entity, BO form) and that CQRS layer is not in a tagged release
  yet, ask for the rename in the related core PR instead of mapping it.
- `CQRSQueryMapping` (often defined as a `QUERY_MAPPING` constant) is
  applied twice by the core: when the query is built from the request
  (API / URI fields and `[_context]` values → query constructor
  parameters, e.g. `'[_context][shopConstraint]' => '[shopConstraint]'`)
  and when the query result is normalized (**query result field → API
  field**, e.g. `'[localisedTitles]' => '[names]'`). A common mistake is
  inverting the second direction (using the API field name as key
  instead of the QueryResult field name). Read the `Get{Entity}ForEditing`
  query result class in PrestaShop Core to find source field names.
- `CQRSCommandMapping` (often `CREATE_COMMAND_MAPPING` /
  `UPDATE_COMMAND_MAPPING`, or a shared `COMMAND_MAPPING` when both
  commands accept the same parameters) maps **API field → command
  constructor parameter**.
- `ApiResourceMapping` is applied when the normalized data is turned
  into the resource object: the items of a list and the result of a
  command (see [`LISTS.md`](./LISTS.md) for the grid data factory, the
  `filtersClass` + `filtersMapping` pair and the field alignment).
- Do NOT use `SerializedName` — always use `CQRSQueryMapping`,
  `CQRSCommandMapping`, or `ApiResourceMapping`.
- Nested fields use bracket notation:
  `'[basicInformation][localizedNames]' => '[names]'`.
- The shop context (`[_context][shopId]`, `[_context][shopIds]`,
  `[_context][shopConstraint]`) is mapped only when the CQRS class takes
  that argument, with its exact type — see [`MULTISHOP.md`](./MULTISHOP.md).

## Read and write formats must match

When a resource exposes the same data for reading and for writing, both
directions must use **one single format**: the payload returned by the read
operation is the payload the write operation accepts, so a response can be
copied as-is to build the next request body. Concretely that means one DTO
property per concept, not a write-shaped property next to a read-shaped one.

The CQRS side rarely lines up on its own — a command and the query that reads
the same data often disagree on field names and structure. Reconciling them is
the job of `CQRSCommandMapping` and `CQRSQueryMapping`; the API format is the
one the resource decides, not the one the Core happens to return.

Legitimate exception: when the write is not the same concept as the read, for
example a *delta* to apply versus the resulting absolute value. See
`ProductStock` (`deltaQuantity` in, `quantity` out). Type such one-way
properties as nullable so `skip_null_values` drops them from the payloads
where they are meaningless.

### The `@index` mapping limit

`NormalizationMapper` (Core, `PrestaShopBundle/ApiPlatform`) expands one
`@index` placeholder at a time: it walks the path against the data, counts that
array level and substitutes the placeholder with each concrete index. Each
placeholder is substituted **independently**, which has two consequences:

- Two nested levels cannot be expanded. Resolving a second placeholder walks a
  path whose first placeholder is still the literal `@index`, so nothing
  matches and the mapping silently does nothing. Reusing the same placeholder
  name twice throws `You cannot use the same index twice`.
- Depths cannot be changed. A flat list cannot be mapped to a grouped
  structure, nor the reverse, because there is no way to compute one target
  index out of a pair of source indexes.

So mapping can rename and move fields, but it cannot flatten or nest.

### When mapping cannot express the format

A narrow normalizer on the **CQRS query result** is the accepted escape hatch,
because `QueryResultSerializerTrait::denormalizeQueryResult()` normalizes that
result before the mapping is applied — reshaping it there keeps the resource
and the write path untouched. It is the only reason to add a normalizer to a
format problem, and it comes with obligations:

1. Register the class in `config/admin/services.yml` with
   `autoconfigure: true` (it joins the `CQRSApiSerializer` chain).
2. Add it to `ApiResourceNormalizerRule::ALLOWED_CLASSES` in
   `tests/PHPStan/`, with a comment explaining why mapping was not enough —
   CI fails otherwise.
3. Keep it narrow: support one query result class, and reshape nothing else.

Real example: `CarrierRangesCollectionNormalizer` flattens the zone-grouped
`GetCarrierRanges` result into the flat range list that
`SetCarrierRangesCommand` accepts, so `/carriers/{carrierId}/ranges` reads and
writes the same `ranges` array. A denormalizer targeting the resource class is
acceptable under the same conditions (`FoundCustomerDenormalizer` turns groups
indexed by id into the `FoundCustomerGroup[]` list, which `@index` cannot do).
The classes already in `ALLOWED_CLASSES` are legacy exceptions, not precedents:
a PR touching their resource is not asked to remove them unless it brings the
mechanism that makes them useless — and a workaround made obsolete by a core
feature or by the PR itself (identifier in a POST URI, extra resource class,
manual cast, disabled test) goes away in that PR.

## OpenAPI documentation

The documentation is generated from the resource (property types and
attributes) and from the core command (request bodies); it is part of
the contract. Class and property PHPDoc are never published: public text
goes in `description` (`#[ApiProperty(description:)]`, `openapi: new
OpenApiOperation(summary:, description:)`), required on every
command-style operation and for every non-obvious behaviour. Rules,
limits of the generator and the export command: [`OPENAPI.md`](./OPENAPI.md).

## Multi-shop

Entities with a shop association expose `shopIds` (mapped both ways,
defaulted from the request context through `defaultValues` on creation,
never through a `[_context]` command mapping); the shop context is
passed to the CQRS class only when it takes it. Feature flag, context
parameters, mappings and examples: [`MULTISHOP.md`](./MULTISHOP.md).

## Do / Don't

### Do

- Map every exception the operations can raise in `exceptionToStatus`
  (`…ConstraintException` → 422, `…NotFoundException` → 404, the value
  object and referenced-entity exceptions included): **any identifier
  checked for existence answers 404 when unknown, in the URI or in the
  body**, an invalid identifier format answers 422, a documented payload
  never answers 500 or 400. Validate at the resource level what the
  command does not (`NotNull` / `NotBlank`, nested arrays, `Choice` from
  the core constants, the BO form rules, bulk id lists typed and
  positive). The complete rules, the mapping order, the bulk 207 and the
  status code table: [`ERRORS.md`](./ERRORS.md).
- Use a `CQRSQuery` on `CQRSCreate` and `CQRSPartialUpdate` when the
  endpoint should return the full updated state, not just an identifier.
  This includes command-style operations: prefer returning the updated
  resource over answering `204` with `output: false`, so the caller does not
  need a follow-up GET. When the operation only touches one association of a
  bigger entity, host it in that entity's resource class and return the whole
  entity — `Carrier::set-tax-rule-group` returns the carrier, not the
  association alone.
- Use `#[LocalizedValue]` for any field stored as `array<locale, value>`.
- Split single / list / bulk operations into separate classes inside the
  same domain folder.
- Add an integration test for every new endpoint (see
  [`TESTING.md`](./TESTING.md)).
- Use `validationContext: ['groups' => ['Default', 'Create']]` on
  `CQRSCreate` and `validationContext: ['groups' => ['Default', 'Update']]`
  on `CQRSPartialUpdate`; constraints match the rules of the associated
  BO form type, including the ones the CQRS command does not enforce
  itself (see [`ERRORS.md`](./ERRORS.md) → "Validation").
- Declare the default values a creation endpoint applies (matching the
  BO form's `default_empty_data` / untouched fields) with the
  `defaultValues` extra property (PrestaShop ≥ 9.2.0 only — the API
  injects them into the payload before validation and command
  denormalization, drops them from the documented `required` list and
  exposes them as `default` in the schema; older cores ignore the extra
  property, so the fields simply stay required there):

  ```php
  extraProperties: [
      'defaultValues' => self::CREATE_DEFAULT_VALUES,
  ],
  ```

  Defaults **cannot** be declared as API resource property defaults
  (`public bool $free = false;`): the payload is denormalized into the
  CQRS command, never into the resource class, so such a default is
  decorative. Use the extra property (not the `defaultValues` named
  argument, which older cores would reject as an unknown parameter).
  The values are copied from the command signature (nullability
  included), never guessed. Never declare `defaultValues` on an update
  operation — a partial update must only touch the provided fields.
- Stay consistent with the reference resources (`Contact`, `Title`) and
  with the sibling resources of the same domain: same validation groups,
  same helpers, same wording.

### Don't

- Don't add business logic in a resource class. Delegate to a Core CQRS
  command or query — always.
- Don't write custom normalizers or custom processors. Use
  `#[LocalizedValue]`, `ApiResourceMapping`, `CQRSQueryMapping`, and
  `CQRSCommandMapping` instead. CI enforces this. Custom processors and
  providers have no exception at all; a normalizer is only tolerated for a
  reshaping the mapping provably cannot express, under the conditions listed
  in "When mapping cannot express the format" above.
- Don't expose Value Objects as DTO properties. Public properties must
  be scalar (`int`, `string`, `bool`), `array`, or one of:
  - `PrestaShop\Decimal\DecimalNumber` — which **must** be used instead
    of `float` for any decimal / monetary value (never `float`);
  - `DateTimeImmutable` / `DateImmutable` — for date / datetime properties;
  - `Symfony\Component\HttpFoundation\File\File` — for file uploads;
  - a DTO class of the `Resources\{Domain}\Dto` namespace — for sub
    objects (see "Sub objects: typed DTOs").

  This is enforced by the `ApiResourcePropertyTypeRule` PHPStan rule in
  CI.
- Don't add an endpoint exposing data another endpoint already exposes,
  a back-office-only workflow (e-mail token relays, confirmation steps,
  quick-access links — the Admin API is server-to-server), a deprecated
  command, or a domain whose core model is not settled; a CQRS class
  deliberately left out of the API goes to
  `GenerateApiTrackingTableCommand::EXCLUDED_CQRS_CLASSES` with the reason.
- Don't expose a raw legacy parameter that can corrupt data (`way` /
  `foundFirst` on positions) or a back-office feature that would leak
  secrets once served as JSON (SQL execution results over `client_secret`):
  remove the operation and fix the core first. A bulk operation able to
  change every order or cart needs the opinion of the core team.
- Don't advertise inputs the command cannot honour: an id-only command
  (toggle) exposes no body schema; sibling commands with different
  setters do not share one input schema that silently drops fields.
- Don't work around a core limitation in the module (a query result made
  of raw database rows, a handler without guard that answers 500, a
  command without setter, a value object without validation): fix the
  core on the lowest applicable branch and pair the PRs (see "Working
  with the core").
- Don't return raw command results. If the endpoint should return data,
  define proper DTO properties and wire a `CQRSQuery`.
- Don't declare an operation without a scope.
- Don't extend a core resource for the fields of a module, and don't add
  a module-specific property to a core entity endpoint: custom fields on
  core entities are the Extra Properties feature (core 9.2.0, component
  `ExtraProperty`). A module registers typed definitions, their values
  ride on the entity endpoints under `extraProperties`
  (`ExtraPropertyApiSubscriber`, validated and documented by the core),
  and the definitions are managed through `/extra-property-definitions`
  (`minVersion` 9.2.0).
- Don't use plural names for boolean / status fields, or `id` as a
  property name — see Property naming.

## Testing

Every endpoint has an integration test in
`tests/Integration/ApiPlatform/{Entity}EndpointTest.php` extending
`ApiTestCase`: whole-response assertions, fixtures created through the
API, the error cases of every mapped exception, version gates with the
`ApiTestCase` helpers. Helpers, environment, structure and patterns:
[`TESTING.md`](./TESTING.md).

## Working with the core

- **One PR per domain**, not per endpoint. The description lists the
  endpoints (`METHOD /uri`), the test scenarios, the `minVersion` gates
  with their reason, the BC breaks on released endpoints, and the
  related core PR.
- **A core bug or limitation is fixed in the core**, on the lowest
  applicable branch (`9.2.x` for a bug, `develop` for a feature), in a
  PR referenced in the module PR description ("Related PR:
  PrestaShop/PrestaShop#NNNN"). The core PR pins the module branch in
  its `composer.json` (core skill `link-apiresources-fork`), so **its CI
  is the reference**: it must be green with the module changes, while
  the module PR's CI stays red on the branches that lack the core
  change until the core PR is merged. Reviews of the module PR compare
  the resources with the core PR branch, not with the release branch.
  Release order: core PR, then module PR, then the core `composer.json`
  is reverted to upstream `dev-dev`.
- **Which core to read.** The module must work on every supported core,
  but its reference is one branch: the related core PR when the PR names
  one; otherwise the **most recent maintained stable branch** of
  PrestaShop/PrestaShop (the highest `X.Y.x` branch — `9.2.x` today, then
  `9.3.x`, `10.0.x`…). `develop` carries everything the maintained branch
  has plus the evolutions too big for a patch release (new CQRS classes,
  reworked commands): an endpoint relying on one of them is gated with
  `minVersion` on the next minor, and `develop` is the reference for
  that endpoint — one PR can have several reference branches, one per
  operation. The behaviour of the older supported cores is checked on
  top: a guard, cast or null check younger than their tag is absent
  there (`git log` / `git blame` on the core file).
- Behaviour bugs of the core (missing guard, `TypeError`, tiny int for a
  boolean, scalar-only query result) block the module PR until fixed;
  documentation-only issues of the core do not.
- The CI is green on every job before the review continues, except the
  jobs a related core PR makes red; php-cs-fixer, PHPStan (one
  `tests/PHPStan/phpstan-<version>.neon` per leg of the workflow matrix,
  ignores scoped to the resource directory), the Rector dry run and the
  license headers are part of it. No merge commits (rebase on `dev`),
  review fixups squashed, no unrelated files.
- The author stands behind the code, AI-generated or not: review answers
  come from the author, and an automated review is triaged explicitly —
  real points fixed, false positives discarded with the reason, in a
  short table (item, verdict, why) the next review can read.
- The tracking table (`GenerateApiTrackingTableCommand`) lists the CQRS
  classes not exposed yet; it is an inventory, not a mandate.

## Canonical examples

Read these before adding new endpoints — they are the source of truth
for the patterns above:

- **Full CRUD on a localized entity** —
  `src/ApiPlatform/Resources/Contact/Contact.php`
  (`CQRSGet`, `CQRSCreate`, `CQRSPartialUpdate` with `LocalizedValue`,
  `DefaultLanguage`, `QUERY_MAPPING`, `CREATE_COMMAND_MAPPING`,
  `exceptionToStatus`).
- **Lists** — see [`LISTS.md`](./LISTS.md) (`ContactList`,
  `CustomerGroupList`, `CombinationList`, `FoundCustomer`).
- **Sub-resource** —
  `src/ApiPlatform/Resources/Product/ProductCombination.php`.
- **Bulk operation** —
  `src/ApiPlatform/Resources/Attribute/BulkAttributeGroups.php`
  (URI `/attributes/groups/bulk-delete`, `attributeGroupIds` input).
- **Command-style operation returning the full entity** —
  `src/ApiPlatform/Resources/Carrier/Carrier.php`
  (URI `/carriers/{carrierId}/set-tax-rule-group`, dedicated validation
  group, `CQRSQuery` reused from the CRUD operations).
- **Shared read / write format, with a normalizer as last resort** —
  `src/ApiPlatform/Resources/Carrier/CarrierRanges.php` and
  `src/ApiPlatform/Normalizer/CarrierRangesCollectionNormalizer.php`.
- **Typed DTO sub objects, `@var Foo[]` lists, `minVersion` 9.2.1,
  explicit summaries and descriptions, write-only input** —
  `src/ApiPlatform/Resources/Customer/CustomerDetails.php` and
  `src/ApiPlatform/Resources/Customer/Dto/`.
- **Nullable objects of multi-parameter setters** (`minimumAmount`,
  `reductionAmount` with their `required` lists) —
  `src/ApiPlatform/Resources/Discount/Discount.php`.
- **Exception mapping order, 404 for an id checked in the body** —
  `src/ApiPlatform/Resources/OrderReturn/OrderReturn.php`.
- **Shop association defaulting to the context shops** —
  `src/ApiPlatform/Resources/Customer/CustomerGroup.php`.
- **Matching tests** —
  `tests/Integration/ApiPlatform/ContactEndpointTest.php`,
  `tests/Integration/ApiPlatform/CustomerGroupEndpointTest.php`
  (version-dependent expectations, 404 / 422 on body ids).

## Coding standards

- Coding style is enforced by `php-cs-fixer` (`.php-cs-fixer.dist.php`).
- Every PHP file declares `strict_types=1` right after the license
  header (resources, DTOs, tests). Classes are referenced with `use`
  imports and `::class`, never inline FQCN strings — except the service
  ids of `config/admin/services.yml`.
- All resource classes live in the namespace
  `PrestaShop\Module\APIResources\ApiPlatform\Resources\{Domain}`.
- Every file must carry the AFL 3.0 license header used across the
  module (run `composer header-stamp-fix` if missing).
- Static analysis: `composer phpstan` (requires `_PS_ROOT_DIR_` and
  `_PS_BRANCH_` env vars).
- Automated refactor / upgrade rules: `composer rector`.

## Reviews

[`REVIEW.md`](./REVIEW.md) is the review checklist: how to compare a
resource with the core classes it names, the OpenAPI contract checks,
the error and test expectations, the known false positives. CodeRabbit
reads it as a guideline (`.coderabbit.yaml`), the `review-api-pr` skill
runs it locally, and it audits existing endpoints the same way.

## Related skills

Skills live in `.ai/skills/` and are symlinked from `.claude/skills/`:

- `skills/ps-api-endpoint/` — creates or reworks an endpoint
  end-to-end (resource class, DTOs, integration test) following the
  conventions above. See `skills/ps-api-endpoint/SKILL.md` and
  `skills/ps-api-endpoint/references/conventions.md`.
- `skills/review-api-pr/` — reviews a PR or audits an endpoint with
  `REVIEW.md`.
- Core skills (PrestaShop repository, `.ai/Component/AdminAPI/skills/`):
  `setup-apiresources-dev` (local checkout and test DB),
  `link-apiresources-fork` (pair a core PR with a module branch).

## Authoritative external references

- Module repository: <https://github.com/PrestaShop/ps_apiresources>
- Core context of the Admin API (providers, normalizers, OpenAPI
  adapters, features per core version, test environment):
  `.ai/Component/AdminAPI/CONTEXT.md` in the PrestaShop repository.
- Devdocs — Admin API (context, OAuth2, resource server, how to use,
  contribute, Swagger): <https://devdocs.prestashop-project.org/9/admin-api/>
- Devdocs — Contribute to the Core API:
  <https://devdocs.prestashop-project.org/9/admin-api/contribute-to-core-api/>
- Devdocs — API Platform & CQRS integration:
  <https://devdocs.prestashop-project.org/9/admin-api/resource_server/api-platform/>
- ADR 0023 — CQRS API guidelines, the decision record of the contract
  (URIs, methods, bulk operations, scopes, resource properties; amended
  2025-11 and 2025-12). The sections above name the ADR section behind
  each rule; a convention that contradicts the ADR needs an ADR amendment
  first:
  <https://github.com/PrestaShop/ADR/blob/master/0023-cqrs-api-guidelines.md>
- Core — `PrestaShopBundle/ApiPlatform` (the infrastructure this module
  plugs into):
  <https://github.com/PrestaShop/PrestaShop/tree/9.2.x/src/PrestaShopBundle/ApiPlatform>
- Core — custom operation attributes (`CQRSGet`, `CQRSCreate`,
  `CQRSPartialUpdate`, `CQRSUpdate`, `CQRSDelete`, `PaginatedList`,
  `CQRSPaginate`, `CQRSGetCollection`, `UpdatePosition`) and related
  metadata:
  <https://github.com/PrestaShop/PrestaShop/tree/9.2.x/src/PrestaShopBundle/ApiPlatform/Metadata>
