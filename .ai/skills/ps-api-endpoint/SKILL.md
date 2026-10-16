---
name: ps-api-endpoint
description: >
  Generates or reworks PrestaShop Admin API endpoints (ApiResource PHP class, DTO sub objects, integration test) in the ps_apiresources module, following the CQRS patterns and the module conventions. Invoke it whenever the user wants to add a REST endpoint, expose an entity via the Admin API, contribute a resource to ps_apiresources, fix or modify an existing resource (fields, mappings, errors, OpenAPI documentation, DTO migration), or asks how to wire a CQRS command/query to an endpoint in PrestaShop 9. Use it even when the user just says "I want to add X to the API", "how do I create an endpoint for Y" or "fix the Z endpoint".
---

# PrestaShop Admin API Endpoint Generator

This skill walks through creating a new endpoint in `ps_apiresources`, from discovery to generated code.

## Step 1: Gather requirements

Ask the user (all in one message if not already specified):

1. **Entity name** — e.g. `TaxRule`, `Warehouse` (PascalCase, singular)
2. **Operations needed** — GET single, POST (create), PATCH (partial update), PUT (full update), DELETE, GET list (paginated). Also ask about bulk operations (e.g. bulk delete) or custom sub-resource actions (e.g. status toggle).
3. **PrestaShop core path** — absolute path to the PS root, e.g. `/home/user/prestashop-90x`. Needed to look up CQRS classes. Offer to skip this step if the user already knows the class names.
4. **Minimum core version** — does the endpoint rely on CQRS classes or core behaviour only available (or only working) since a specific PrestaShop version? If so, the operations must declare `extraProperties: ['minVersion' => 'X.Y.Z']` and the test needs version-skip handling (see "Core version constraints" and "Version-dependent endpoints" in `CONTEXT.md`).

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
- Which fields are **structured** (an object, a list of objects) → a typed DTO class in `src/ApiPlatform/Resources/{Entity}/Dto/`, typed on the property or declared with `@var {Dto}[]` (`CONTEXT.md` → "Sub objects: typed DTOs"); a DTO holding a list or a boolean filled from a query result needs `minVersion: 9.2.1`
- Which fields are **localized** (arrays keyed by locale like `'en-US' => 'value'`) → need `#[LocalizedValue]`
- Which localized fields are required in the default language on create → need `#[DefaultLanguage(groups: ['Create'], fieldName: '...')]`
- Which localized fields are optional on update → `#[DefaultLanguage(groups: ['Update'], fieldName: '...', allowNull: true)]`

## Step 4: Generate the ApiResource class

**File location:** `src/ApiPlatform/Resources/{Entity}/{Entity}.php`

Use the template below. Adapt based on the selected operations.

```php
<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\{Entity};

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\{Entity}\Command\Add{Entity}Command;
use PrestaShop\PrestaShop\Core\Domain\{Entity}\Command\Edit{Entity}Command;
use PrestaShop\PrestaShop\Core\Domain\{Entity}\Command\Delete{Entity}Command;
use PrestaShop\PrestaShop\Core\Domain\{Entity}\Exception\{Entity}ConstraintException;
use PrestaShop\PrestaShop\Core\Domain\{Entity}\Exception\{Entity}NotFoundException;
use PrestaShop\PrestaShop\Core\Domain\{Entity}\Query\Get{Entity}ForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/{entities}/{entityId}',
            requirements: ['{entityId}' => '\d+'],
            CQRSQuery: Get{Entity}ForEditing::class,
            scopes: ['{entity}_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/{entities}',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: Add{Entity}Command::class,
            CQRSQuery: Get{Entity}ForEditing::class,
            scopes: ['{entity}_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/{entities}/{entityId}',
            requirements: ['{entityId}' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: Edit{Entity}Command::class,
            CQRSQuery: Get{Entity}ForEditing::class,
            scopes: ['{entity}_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/{entities}/{entityId}',
            requirements: ['{entityId}' => '\d+'],
            CQRSCommand: Delete{Entity}Command::class,
            scopes: ['{entity}_write'],
        ),
    ],
    exceptionToStatus: [
        // Every exception the commands, their value objects and the handlers can raise:
        // *NotFoundException => 404 (ids of the URI and of the body), constraint / value object exceptions => 422.
        // A subclass mapped to a different status than its parent is listed first.
        {Entity}ConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        {Entity}NotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class {Entity}
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int ${entityId};

    // Add other properties here with appropriate types and constraints.
    // Structured values are typed DTOs of Resources/{Entity}/Dto/ (or `@var {Dto}[]` lists), never
    // `array` + a hand-written openapiContext object schema.

    public const QUERY_MAPPING = [
        // '[sourceFieldFromQueryResult]' => '[apiFieldName]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        // '[apiFieldName]' => '[commandConstructorParam]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        // '[apiFieldName]' => '[commandConstructorParam]',
    ];
}
```

### Key rules while filling in the template

See `references/conventions.md` for the full ruleset. The most important ones:

- **URI**: plural, lowercase, kebab-case. `featureId` in URI, not `id`. E.g. `/tax-rules/{taxRuleId}`.
- **Scopes**: `{entity_snake_case}_read` and `{entity_snake_case}_write`. Multi-word entities: `tax_rule_read`.
- **Boolean properties**: no `is` prefix. Use `$enabled`, not `$isEnabled`.
- **Localized properties**: no "localized" prefix. Use `$names`, not `$localizedNames`. Mark with `#[LocalizedValue]`.
- **Mapping**: `QUERY_MAPPING` maps `[queryResultFieldName] => [apiFieldName]`. `CREATE_COMMAND_MAPPING` maps `[apiFieldName] => [commandParamName]`. When Create and Update commands share the same param names, use a single `COMMAND_MAPPING` constant.
- **One format for read and write**: whatever a read operation returns for a piece of data, the write operation must accept in that exact shape — a response body must be copy-pasteable into the next request. Reconcile the command and query shapes with the mappings, never by exposing two properties for one concept. Beware the `@index` limit: mapping renames and moves fields but cannot flatten or nest. Full rule, the exception for delta-style writes, and the normalizer escape hatch: `CONTEXT.md` → "Read and write formats must match".
- **BO form parity**: constraints must reproduce the rules of the entity's BO form type, including the ones the CQRS command doesn't enforce (non-empty collections, ranges, URL formats, PS constraints like `CleanHtml`). Default values applied by the form when fields are untouched are declared with the `defaultValues` extra property on the create operation (PS ≥ 9.2 — never as resource property defaults, they don't reach the command; never on updates). Full rule: `CONTEXT.md` → "Do" list.
- **Command-style operations**: name the action in the URI, verb first (`/carriers/{carrierId}/set-tax-rule-group`), add the segment to `ApiResourceUriTemplateRector::SKIPPED_KEYWORDS` or Rector CI will pluralize it, host it in the resource it modifies, and return the updated entity via a `CQRSQuery` rather than a `204`.
- **Explicit OpenAPI summary when the method lies**: the generated summary is derived from the HTTP method (POST ⇒ "Creates a … resource"), so any operation whose method doesn't match its semantics (e.g. an update forced to POST because it uploads a file) must declare `openapi: new OpenApiOperation(summary: …, description: …)` on **both** operations sharing the method, so the doc doesn't show two identical create entries.
- **Errors**: map every exception the operations can raise, `*NotFoundException` → 404 for any id checked for existence (URI or body), value object and constraint exceptions → 422, subclass before parent; a documented payload must never answer 500 (`NotNull` / `NotBlank` on required command parameters, `Assert\All` + `Collection` on nested arrays). Full rule: `CONTEXT.md` → "Do" list.
- **OpenAPI**: `openapi: new OpenApiOperation(summary:, description:)` on command-style operations and whenever the HTTP method does not say what the operation does; a description for every non-obvious behaviour (version gate, formatted values, side effects); class comments are PHPDoc, not published. Full rule: `CONTEXT.md` → "OpenAPI documentation". Verify with `bin/console api:openapi:export --yaml` in the core.
- **Forbidden**: no custom normalizers, no custom processors, no Value Objects as properties (scalars, arrays, `DecimalNumber`, `DateTimeImmutable`, `File`, DTOs of `Resources\{Entity}\Dto`).
- **Strict typing**: every property needs an explicit type.
- **Version constraint**: if the endpoint requires a minimum core version, add `extraProperties: ['minVersion' => 'X.Y.Z']` to the operation — it is then filtered out (404) on older cores. Never to hide a 500 on an older core. Full details in `CONTEXT.md` → "Core version constraints".

### List endpoints (PaginatedList)

List operations live in a **separate class file** named `{Entity}List.php` next to the main resource (e.g. `src/ApiPlatform/Resources/Contact/ContactList.php`, `src/ApiPlatform/Resources/Attribute/AttributeGroupList.php`). Use `PaginatedList` with the Grid's data factory service, its `filtersClass` **and** a `filtersMapping` (API field → grid field, for `orderBy` and `filters[...]`): without them `orderBy` answers 500 and the filters are ignored. The URI is the plural base path (e.g. `/contacts`) without an ID. Field mapping is done via `ApiResourceMapping`; every DTO property must match a `SELECT` column of the grid query builder, and only the filters the query builder supports may be mapped. Example: `src/ApiPlatform/Resources/Customer/CustomerGroupList.php`.

### Bulk operations

Bulk operations also live in a **separate class file** named `Bulk{Entities}.php` (or `BulkDelete{Entities}.php` / `BulkUpdateStatus{Entities}.php` when more specific) next to the main resource. Real examples in the repo:

- `src/ApiPlatform/Resources/Attribute/BulkAttributeGroups.php`
- `src/ApiPlatform/Resources/Category/BulkDeleteCategories.php`
- `src/ApiPlatform/Resources/Category/BulkUpdateStatusCategories.php`

URI uses `bulk-` prefix + plural: `/tax-rules/bulk-delete`. The single public property is the array of IDs, named with the singular entity + "Ids" (e.g. `$taxRuleIds`, `$attributeGroupIds`), typed as `array` with a `@var int[]` docblock (the core documents the item type from it; add `#[ApiProperty(openapiContext: ['example' => [1, 3]])]` only for an example), and annotated with `#[Assert\NotBlank]` and `#[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]` (the command casts each element with `(int)`: without the element constraints `["3abc"]` silently acts on entity 3). A handler extending the core `AbstractBulkCommandHandler` answers 207 with one error per failed id (core ≥ 9.1.2): say it in the operation description and test it with `bulkCommandItemsWithExpectedErrors()`.

## Step 5: Generate the integration test

**File location:** `tests/Integration/ApiPlatform/{Entity}EndpointTest.php`

```php
<?php
/**
 * [AFL 3.0 license header — same as ApiResource file]
 */

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class {Entity}EndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['{entity}_read', '{entity}_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            '{db_table}',        // add all related tables
            '{db_table}_lang',   // if entity has localized fields
            '{db_table}_shop',   // if entity has shop association
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        // List every endpoint that should require authentication
        yield 'get endpoint' => ['GET', '/{entities}/1'];
        yield 'create endpoint' => ['POST', '/{entities}'];
        yield 'patch endpoint' => ['PATCH', '/{entities}/1'];
        yield 'delete endpoint' => ['DELETE', '/{entities}/1'];
        yield 'list endpoint' => ['GET', '/{entities}'];
    }

    public function testAdd{Entity}(): int
    {
        $postData = [
            // all required fields with valid values
            // for localized fields: ['en-US' => 'value', 'fr-FR' => 'valeur']
        ];

        $response = $this->createItem('/{entities}', $postData, ['{entity}_write']);
        $this->assertArrayHasKey('{entityId}', $response);
        // The whole response is asserted, with the id read from it
        $this->assertEquals(array_merge(['{entityId}' => $response['{entityId}']], $postData, [
            // fields the API fills by default
        ]), $response);

        return $response['{entityId}'];
    }

    /** @depends testAdd{Entity} */
    public function testGet{Entity}(int ${entityId}): int
    {
        $response = $this->getItem('/{entities}/' . ${entityId}, ['{entity}_read']);
        $this->assertEquals([
            '{entityId}' => ${entityId},
            // every exposed field with its expected value, nested objects and lists included
        ], $response);

        return ${entityId};
    }

    /** @depends testGet{Entity} */
    public function testPartialUpdate{Entity}(int ${entityId}): int
    {
        $patchData = [
            // fields to update
        ];

        $updated = $this->partialUpdateItem('/{entities}/' . ${entityId}, $patchData, ['{entity}_write']);
        // The whole updated entity: the patched fields, and the others untouched
        $this->assertEquals([
            '{entityId}' => ${entityId},
            // ...
        ], $updated);

        // Verify the GET also reflects the changes
        $fetched = $this->getItem('/{entities}/' . ${entityId}, ['{entity}_read']);
        $this->assertEquals($updated, $fetched);

        return ${entityId};
    }

    /** @depends testPartialUpdate{Entity} */
    public function testDelete{Entity}(int ${entityId}): void
    {
        $this->deleteItem('/{entities}/' . ${entityId}, ['{entity}_write']);
        $this->getItem('/{entities}/' . ${entityId}, ['{entity}_read'], Response::HTTP_NOT_FOUND);
    }

    public function testNotFound{Entity}(): void
    {
        // Every operation with a mapped NotFoundException answers 404 on an unknown id
        $this->getItem('/{entities}/99999', ['{entity}_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/{entities}/99999', [/* valid data */], ['{entity}_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/{entities}/99999', ['{entity}_write'], Response::HTTP_NOT_FOUND);
        // An invalid id format is a 422 (value object exception), not a 500
        $this->getItem('/{entities}/0', ['{entity}_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalid{Entity}(): void
    {
        $invalidData = [
            // invalid values to trigger validation errors
        ];

        $response = $this->createItem(
            '/{entities}',
            $invalidData,
            ['{entity}_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            ['propertyPath' => 'fieldName', 'message' => 'expected error message'],
        ], $response);
    }
}
```

### Test rules

- Tests assert the **whole response** with one `assertEquals` of the full expected array — every field, nested objects, list items; never key-presence or field-by-field checks (they miss removed fields and hide new ones).
- Chain tests using `@depends` so each test builds on the created entity; ids come from the responses.
- Fixtures are created through the API (the endpoints under test or of the same domain), not with `ObjectModel` or SQL.
- Always include a `testInvalid*` test that covers validation constraints (`assertValidationErrors` for the constraint 422; a 422 produced by a mapped CQRS exception has no field list, assert the status only), and a 404 test for every mapped `*NotFoundException` on every operation, plus `/x/0` → 422.
- The resource declares `normalizationContext: ['skip_null_values' => false]` when it has nullable fields, so `null` values are returned and asserted.
- A list test calls `listItems()` with `filters[...]` and `orderBy` on the API field names and asserts the filtered items and the envelope.
- Drop `LanguageResetter::resetLanguages()` from setUp/tearDown if the entity has no localized fields.
- **Version-dependent endpoints**: if the resource declares a `minVersion`, use the `ApiTestCase` helpers (`markTestSkippedByMinVersion`, `isVersionAtLeast`, `isVersionUnder`) instead of hand-rolled `version_compare` checks. When every operation is constrained, skip the whole class in `setUpBeforeClass()` before calling the parent, and keep `getProtectedEndpoints()` yielding unconditionally (an empty data provider is a PHPUnit error). The exact patterns and examples are in `CONTEXT.md` → "Version-dependent endpoints".

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

## Common pitfalls to flag

- If the `Add*Command` takes its ID from an `EntityId` value object in the result (not a raw int), the `CQRSCreate` block may need to fetch the result via a separate `CQRSQuery` after creation.
- If a command and its edit counterpart share the same constructor signature, use one `COMMAND_MAPPING` constant.
- For entities without shop association, omit `shopIds` and its mapping. With one, the context shops are the **default** of `shopIds` on creation (`extraProperties: ['defaultValues' => ['shopIds' => '[_context][shopIds]']]`), never a `[_context]` command mapping that would overwrite an explicit value.
- `#[DefaultLanguage]` needs the `fieldName` argument set to the **API field name** (e.g. `fieldName: 'names'`), not the internal query field name.
- A write property that is not a constructor parameter or a setter of the command is silently dropped; a read property that no query-result getter fills is always `null`.
- `exceptionToStatus` is evaluated in order with `instanceof`: a subclass mapped to 404 must precede its parent mapped to 422.

## Reference files

- `references/conventions.md` — full naming and structural conventions
- `../../CONTEXT.md` (`.ai/CONTEXT.md`) — module-wide AI context: purpose, architecture, Do/Don't, canonical examples, and the on-demand `.ai/*.md` contexts (`TESTING.md`, `OPENAPI.md`, `LISTS.md`, `MULTISHOP.md`, `VERSIONS.md`). This skill must stay aligned with them.
