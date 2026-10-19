---
name: ps-api-endpoint
description: >
  Generates or reworks PrestaShop Admin API endpoints (ApiResource PHP class, DTO sub objects, integration test) in the ps_apiresources module, following the CQRS patterns and the module conventions. Invoke it whenever the user wants to add a REST endpoint, expose an entity via the Admin API, contribute a resource to ps_apiresources, fix or modify an existing resource (fields, mappings, errors, OpenAPI documentation, DTO migration), or asks how to wire a CQRS command/query to an endpoint in PrestaShop 9. Use it even when the user just says "I want to add X to the API", "how do I create an endpoint for Y" or "fix the Z endpoint".
---

# PrestaShop Admin API Endpoint Generator

This skill walks through creating a new endpoint in `ps_apiresources`, from discovery to generated code.
The conventions are written once, in `.ai/CONTEXT.md` and its on-demand files;
`references/conventions.md` maps each step below to the section to read. This skill never summarizes
them: read the section.

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

## Step 4: Generate the ApiResource class

**File location:** `src/ApiPlatform/Resources/{Domain}/{Entity}.php`

Use the template below. Adapt based on the selected operations: the method per operation and the
options to set are in `CONTEXT.md` → "Choosing the operation", the URI and scope rules in
"URI conventions" and "Scopes", the property rules in "Property naming", the mappings in
"Field mapping", the exceptions and constraints in `.ai/ERRORS.md`, the summaries and
descriptions in `.ai/OPENAPI.md`.

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
        // Every exception the commands, their value objects and the handlers can raise,
        // in the order ERRORS.md prescribes.
        {Entity}ConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        {Entity}NotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class {Entity}
{
    #[ApiProperty(identifier: true)]
    public int ${entityId};

    // Other properties: types, constraints and sub objects as CONTEXT.md prescribes.

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

### List endpoints

A list lives in its own `{Entity}List.php` next to the resource. The operation to use, the
`filtersClass` + `filtersMapping` pair, the field alignment with the grid or query and the examples:
`.ai/LISTS.md`.

### Bulk operations

A bulk operation lives in its own `Bulk{Entities}.php` (`BulkDelete{Entities}.php`,
`BulkUpdateStatus{Entities}.php` when more specific) next to the resource. The URI shape and the method
per action: `CONTEXT.md` → "URI conventions"; the id list constraints, `allowEmptyBody` and the 207
behaviour: `.ai/ERRORS.md`. Real examples: `src/ApiPlatform/Resources/Attribute/BulkAttributeGroups.php`,
`src/ApiPlatform/Resources/Category/BulkDeleteCategories.php`,
`src/ApiPlatform/Resources/Category/BulkUpdateStatusCategories.php`.

## Step 5: Generate the integration test

**File location:** `tests/Integration/ApiPlatform/{Entity}EndpointTest.php`

What the test must cover (whole-response assertions, fixtures through the API, error cases, lists,
version skips) and the helpers of `ApiTestCase` are in `.ai/TESTING.md`; the template only gives the
structure.

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

- `references/conventions.md` — which section of the context each step needs, and where the files go
- `../../CONTEXT.md` (`.ai/CONTEXT.md`) — the conventions, with the on-demand `.ai/*.md` contexts (`TESTING.md`, `OPENAPI.md`, `LISTS.md`, `ERRORS.md`, `MULTISHOP.md`, `VERSIONS.md`)
