---
name: ps-api-endpoint
description: >
  Generates new PrestaShop Admin API endpoints (ApiResource PHP class + integration test) for the ps_apiresources module, following CQRS patterns and contribution guidelines. Invoke this skill whenever the user wants to add a new REST endpoint, expose a new entity via the Admin API, contribute a new resource to ps_apiresources, or asks how to wire up a CQRS command/query to an API endpoint in PrestaShop 9. Use it even when the user just says "I want to add X to the API" or "how do I create an endpoint for Y".
---

# PrestaShop Admin API Endpoint Generator

This skill walks through creating a new endpoint in `ps_apiresources`, from discovery to generated code.

## Step 1: Gather requirements

Ask the user (all in one message if not already specified):

1. **Entity name** — e.g. `TaxRule`, `Warehouse` (PascalCase, singular)
2. **Operations needed** — GET single, POST (create), PATCH (partial update), PUT (full update), DELETE, GET list (paginated). Also ask about bulk operations (e.g. bulk delete) or custom sub-resource actions (e.g. status toggle).
3. **PrestaShop core path** — absolute path to the PS root, e.g. `/home/user/prestashop-90x`. Needed to look up CQRS classes. Offer to skip this step if the user already knows the class names.

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

Use Glob/Grep to find these files. Then **read the QueryResult class** (e.g. `{Entity}ForEditing.php`) — its constructor arguments and getters reveal the exact field names returned by the query. This is the ground truth for `QUERY_MAPPING`.

Also read the Command constructors to learn the parameter names needed for `CQRSCommandMapping`.

If some of these classes don't exist (e.g. no Add command), note it — only include operations that have backing CQRS classes.

## Step 3: Gather field information

After reading the QueryResult, confirm with the user:
- Which fields should be exposed in the API (not all internal fields need to be public)
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
        {Entity}ConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        {Entity}NotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class {Entity}
{
    #[ApiProperty(identifier: true)]
    public int ${entityId};

    // Add other properties here with appropriate types and constraints

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
- **Forbidden**: no custom normalizers, no custom processors, no Value Objects as properties (only scalar types and arrays).
- **Strict typing**: every property needs an explicit type.

### List endpoints (PaginatedList)

List operations live in a **separate class file** named `{Entity}List.php` next to the main resource (e.g. `src/ApiPlatform/Resources/Contact/ContactList.php`, `src/ApiPlatform/Resources/Attribute/AttributeGroupList.php`). Use `PaginatedList` with the Grid's data factory service and a `filtersClass`. The URI is the plural base path (e.g. `/contacts`) without an ID. Field mapping is done via `ApiResourceMapping`.

### Bulk operations

Bulk operations also live in a **separate class file** named `Bulk{Entities}.php` (or `BulkDelete{Entities}.php` / `BulkUpdateStatus{Entities}.php` when more specific) next to the main resource. Real examples in the repo:

- `src/ApiPlatform/Resources/Attribute/BulkAttributeGroups.php`
- `src/ApiPlatform/Resources/Category/BulkDeleteCategories.php`
- `src/ApiPlatform/Resources/Category/BulkUpdateStatusCategories.php`

URI uses `bulk-` prefix + plural: `/tax-rules/bulk-delete`. The single public property is the array of IDs, named with the singular entity + "Ids" (e.g. `$taxRuleIds`, `$attributeGroupIds`), typed as `array`, and annotated with `#[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]` and `#[Assert\NotBlank]`. On `CQRSDelete`, set `allowEmptyBody: false` so missing payloads are rejected.

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

        return $response['{entityId}'];
    }

    /** @depends testAdd{Entity} */
    public function testGet{Entity}(int ${entityId}): int
    {
        $response = $this->getItem('/{entities}/' . ${entityId}, ['{entity}_read']);
        $this->assertEquals(${entityId}, $response['{entityId}']);
        // assert all exposed fields are present and correct

        return ${entityId};
    }

    /** @depends testGet{Entity} */
    public function testPartialUpdate{Entity}(int ${entityId}): int
    {
        $patchData = [
            // fields to update
        ];

        $updated = $this->partialUpdateItem('/{entities}/' . ${entityId}, $patchData, ['{entity}_write']);
        // assert updated fields match

        // Verify the GET also reflects the changes
        $fetched = $this->getItem('/{entities}/' . ${entityId}, ['{entity}_read']);
        // assert same fields

        return ${entityId};
    }

    /** @depends testPartialUpdate{Entity} */
    public function testDelete{Entity}(int ${entityId}): void
    {
        $this->deleteItem('/{entities}/' . ${entityId}, ['{entity}_write']);
        $this->getItem('/{entities}/' . ${entityId}, ['{entity}_read'], Response::HTTP_NOT_FOUND);
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

- Tests must assert **complete response data** — all fields, not just the identifier.
- Chain tests using `@depends` so each test builds on the created entity.
- Always include a `testInvalid*` test that covers validation constraints.
- Include `'skip_null_values' => false` in assertions when using `assertEquals` on the full response array.
- Drop `LanguageResetter::resetLanguages()` from setUp/tearDown if the entity has no localized fields.

## Step 6: Write the files

Create both files at their correct paths. Then tell the user:

1. **Where the files were written**
2. **How to register the resource** — if the ApiResource isn't auto-discovered, a service definition may be needed (usually not required for standard resources)
3. **How to run the tests:**
   ```bash
   composer setup-local-tests  # first time only
   composer run-module-tests
   ```
4. **What to double-check**: mappings between CQRS fields and API fields — these are the most common source of errors. Ask the user to run the endpoint manually via Swagger UI to verify.

## Common pitfalls to flag

- If the `Add*Command` takes its ID from an `EntityId` value object in the result (not a raw int), the `CQRSCreate` block may need to fetch the result via a separate `CQRSQuery` after creation.
- If a command and its edit counterpart share the same constructor signature, use one `COMMAND_MAPPING` constant.
- For entities without shop association, omit `shopIds` and its mapping.
- `#[DefaultLanguage]` needs the `fieldName` argument set to the **API field name** (e.g. `fieldName: 'names'`), not the internal query field name.

## Reference files

- `references/conventions.md` — full naming and structural conventions
- `../../../CONTEXT.md` (repo root) — module-wide AI context: purpose, architecture, Do/Don't, canonical examples. This skill must stay aligned with it.
