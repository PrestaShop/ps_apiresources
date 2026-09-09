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

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinitionRepositoryInterface;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyRegistryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

/**
 * Contract of the extra property DEFINITION endpoints (the registry): payload shape, mappings,
 * exception statuses, list filters. The effect of a definition on the entity endpoints is covered
 * by ExtraPropertyValuesEndpointTest; the CQRS error matrix (every refused constraint DSL, every
 * registry refusal) is covered by the core Behat suite, so only one error per HTTP mapping is
 * asserted here.
 */
class ExtraPropertyDefinitionEndpointTest extends ApiTestCase
{
    private const READ = 'extra_property_definition_read';
    private const WRITE = 'extra_property_definition_write';
    private const ENDPOINT = '/extra-property-definitions';

    /**
     * Every property created by this class starts with this prefix so the teardown can drop them
     * (storage column included) whatever the state a failing test left behind.
     */
    private const PROPERTY_PREFIX = 'apidef_';

    private const MODULE_OWNED_PROPERTY = self::PROPERTY_PREFIX . 'module_owned';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::isVersionAtLeast('9.2.0')) {
            return;
        }

        self::cleanDefinitions();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::isVersionAtLeast('9.2.0')) {
            self::cleanDefinitions();
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkippedByMinVersion('9.2.0');
    }

    /**
     * Unregisters (column dropped) every definition created by these tests and restores the
     * registry tables from the dump.
     */
    private static function cleanDefinitions(): void
    {
        $registry = self::getContainer()->get(ExtraPropertyRegistryInterface::class);
        $repository = self::getContainer()->get(ExtraPropertyDefinitionRepositoryInterface::class);
        foreach ($repository->getAllDefinitions() as $definition) {
            if (str_starts_with($definition->getPropertyName(), self::PROPERTY_PREFIX)) {
                $registry->unregister($definition, true);
            }
        }

        DatabaseDump::restoreTables(['extra_property_definition', 'extra_property_definition_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', self::ENDPOINT . '/1'];
        yield 'create endpoint' => ['POST', self::ENDPOINT];
        yield 'patch endpoint' => ['PATCH', self::ENDPOINT . '/1'];
        yield 'delete endpoint' => ['DELETE', self::ENDPOINT . '/1'];
        yield 'list endpoint' => ['GET', self::ENDPOINT];
        yield 'bulk delete endpoint' => ['DELETE', self::ENDPOINT . '/bulk-delete'];
    }

    public function testAddExtraPropertyDefinition(): int
    {
        $postData = [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'string',
            'type' => 'string',
            'scope' => 'common',
            'sqlIndex' => 'key',
            'displayFront' => true,
            'required' => false,
            'nullable' => true,
            'size' => 64,
            'defaultValue' => 'n/a',
            'labelWording' => 'API string',
            'labelDomain' => 'Modules.Apitest.Admin',
            'descriptionWording' => 'Created through the Admin API',
            'descriptionDomain' => 'Modules.Apitest.Admin',
            // Constraints travel as the DSL string, read back in its canonical form (one per line,
            // named options sorted).
            'constraints' => "NotBlank\nLength(min: 2, max: 64)",
            'formOptions' => ['attr' => ['placeholder' => 'n/a']],
            'associatedForms' => ['product'],
            'associatedGrids' => ['product'],
            'associatedApis' => ['/products', '/products/{productId}'],
        ];

        $definition = $this->createItem(self::ENDPOINT, $postData, [self::WRITE]);
        $this->assertArrayHasKey('extraPropertyDefinitionId', $definition);
        $definitionId = $definition['extraPropertyDefinitionId'];
        $this->assertIsInt($definitionId);

        $this->assertEquals([
            'extraPropertyDefinitionId' => $definitionId,
            'entityName' => 'product',
            'moduleName' => null,
            'propertyName' => self::PROPERTY_PREFIX . 'string',
            'type' => 'string',
            'scope' => 'common',
            'sqlIndex' => 'key',
            'displayFront' => true,
            'required' => false,
            'nullable' => true,
            'size' => 64,
            'defaultValue' => 'n/a',
            'enumValues' => null,
            'labelWording' => 'API string',
            'labelDomain' => 'Modules.Apitest.Admin',
            'descriptionWording' => 'Created through the Admin API',
            'descriptionDomain' => 'Modules.Apitest.Admin',
            'constraints' => "NotBlank\nLength(max: 64, min: 2)",
            'formType' => null,
            'formOptions' => ['attr' => ['placeholder' => 'n/a']],
            'associatedForms' => ['product'],
            'associatedGrids' => ['product'],
            'associatedApis' => ['/products', '/products/{productId}'],
            'shopIds' => null,
        ], $definition);

        return $definitionId;
    }

    /**
     * The default value keeps the type of the property: a float default is a JSON number, a bool
     * default a JSON boolean, a choice default one of the enum values.
     */
    public function testTypedDefaultValues(): void
    {
        $float = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'float',
            'type' => 'float',
            'defaultValue' => 1.5,
        ], [self::WRITE]);
        $this->assertSame(1.5, $float['defaultValue']);
        $this->assertNull($float['constraints']);

        $int = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'int',
            'type' => 'int',
            'defaultValue' => 5,
        ], [self::WRITE]);
        $this->assertSame(5, $int['defaultValue']);

        $bool = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'bool',
            'type' => 'bool',
            'nullable' => false,
            'defaultValue' => false,
        ], [self::WRITE]);
        $this->assertFalse($bool['defaultValue']);
        $this->assertFalse($bool['nullable']);

        $choice = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'choice',
            'type' => 'choice',
            'enumValues' => ['small', 'large'],
            'defaultValue' => 'small',
        ], [self::WRITE]);
        $this->assertSame(['small', 'large'], $choice['enumValues']);
        $this->assertSame('small', $choice['defaultValue']);
    }

    /**
     * @depends testAddExtraPropertyDefinition
     */
    public function testGetExtraPropertyDefinition(int $definitionId): int
    {
        $definition = $this->getItem(self::ENDPOINT . '/' . $definitionId, [self::READ]);
        $this->assertSame($definitionId, $definition['extraPropertyDefinitionId']);
        $this->assertSame(self::PROPERTY_PREFIX . 'string', $definition['propertyName']);
        $this->assertSame("NotBlank\nLength(max: 64, min: 2)", $definition['constraints']);

        return $definitionId;
    }

    /**
     * @depends testGetExtraPropertyDefinition
     */
    public function testPartialUpdateExtraPropertyDefinition(int $definitionId): int
    {
        $patchData = [
            'labelWording' => 'API string (edited)',
            'displayFront' => false,
            'required' => true,
            // Non-destructive storage change: a larger size.
            'size' => 128,
            'constraints' => 'Email',
            'associatedApis' => ['/products/{productId}'],
        ];
        $updated = $this->partialUpdateItem(self::ENDPOINT . '/' . $definitionId, $patchData, [self::WRITE]);
        $this->assertSame('API string (edited)', $updated['labelWording']);
        $this->assertFalse($updated['displayFront']);
        $this->assertTrue($updated['required']);
        $this->assertSame(128, $updated['size']);
        $this->assertSame('Email', $updated['constraints']);
        $this->assertSame(['/products/{productId}'], $updated['associatedApis']);
        // Untouched fields keep their value.
        $this->assertSame('Created through the Admin API', $updated['descriptionWording']);
        $this->assertSame(['product'], $updated['associatedGrids']);

        $fetched = $this->getItem(self::ENDPOINT . '/' . $definitionId, [self::READ]);
        $this->assertSame($updated, $fetched);

        // An empty constraints string removes every constraint (null would leave them untouched).
        $cleared = $this->partialUpdateItem(self::ENDPOINT . '/' . $definitionId, ['constraints' => ''], [self::WRITE]);
        $this->assertNull($cleared['constraints']);
        $this->assertSame('API string (edited)', $cleared['labelWording']);

        // Structural fields are not editable: they are simply not part of the update command.
        $unchanged = $this->partialUpdateItem(self::ENDPOINT . '/' . $definitionId, ['type' => 'int', 'scope' => 'lang'], [self::WRITE]);
        $this->assertSame('string', $unchanged['type']);
        $this->assertSame('common', $unchanged['scope']);

        return $definitionId;
    }

    /**
     * @depends testPartialUpdateExtraPropertyDefinition
     */
    public function testListExtraPropertyDefinitions(int $definitionId): int
    {
        $list = $this->listItems(self::ENDPOINT . '?orderBy=extraPropertyDefinitionId&sortOrder=desc', [self::READ]);
        $this->assertGreaterThanOrEqual(1, $list['totalItems']);
        $this->assertSame('extraPropertyDefinitionId', $list['orderBy']);

        $filtered = $this->listItems(self::ENDPOINT, [self::READ], ['propertyName' => self::PROPERTY_PREFIX . 'string']);
        $this->assertSame(1, $filtered['totalItems']);
        $this->assertEquals([
            'extraPropertyDefinitionId' => $definitionId,
            'entityName' => 'product',
            'moduleName' => null,
            'propertyName' => self::PROPERTY_PREFIX . 'string',
            'type' => 'string',
            'scope' => 'common',
            'sqlIndex' => 'key',
            'displayFront' => false,
        ], $filtered['items'][0]);

        // Exact-match filters on the enum columns, LIKE filter on the names.
        $floats = $this->listItems(self::ENDPOINT, [self::READ], ['type' => 'float', 'propertyName' => self::PROPERTY_PREFIX]);
        $this->assertSame(1, $floats['totalItems']);
        $this->assertSame(self::PROPERTY_PREFIX . 'float', $floats['items'][0]['propertyName']);

        $prefixed = $this->listItems(self::ENDPOINT, [self::READ], ['entityName' => 'product', 'propertyName' => self::PROPERTY_PREFIX]);
        $this->assertSame(5, $prefixed['totalItems']);

        return $definitionId;
    }

    /**
     * @depends testListExtraPropertyDefinitions
     */
    public function testDeleteExtraPropertyDefinition(int $definitionId): void
    {
        $propertyName = self::PROPERTY_PREFIX . 'string';

        // Without a body the storage column is kept (like the back office "Delete" action)...
        $this->assertNull($this->deleteItem(self::ENDPOINT . '/' . $definitionId, [self::WRITE]));
        $this->getItem(self::ENDPOINT . '/' . $definitionId, [self::READ], Response::HTTP_NOT_FOUND);
        $this->assertTrue($this->storageColumnExists('product_extra', $propertyName));

        // ...so the property can be registered again on the existing column...
        $recreated = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => $propertyName,
            'type' => 'string',
            'size' => 128,
        ], [self::WRITE]);

        // ...and {"dropColumn": true} removes the column and its data.
        $this->requestApi(
            'DELETE',
            self::ENDPOINT . '/' . $recreated['extraPropertyDefinitionId'],
            ['dropColumn' => true],
            [self::WRITE],
            Response::HTTP_NO_CONTENT
        );
        $this->getItem(self::ENDPOINT . '/' . $recreated['extraPropertyDefinitionId'], [self::READ], Response::HTTP_NOT_FOUND);
        $this->assertFalse($this->storageColumnExists('product_extra', $propertyName));
    }

    public function testBulkDeleteExtraPropertyDefinitions(): void
    {
        $first = $this->createItem(self::ENDPOINT, ['entityName' => 'product', 'propertyName' => self::PROPERTY_PREFIX . 'bulk_1'], [self::WRITE]);
        $second = $this->createItem(self::ENDPOINT, ['entityName' => 'product', 'propertyName' => self::PROPERTY_PREFIX . 'bulk_2'], [self::WRITE]);
        $third = $this->createItem(self::ENDPOINT, ['entityName' => 'product', 'propertyName' => self::PROPERTY_PREFIX . 'bulk_3'], [self::WRITE]);

        $this->bulkDeleteItems(self::ENDPOINT . '/bulk-delete', [
            'extraPropertyDefinitionIds' => [$first['extraPropertyDefinitionId'], $second['extraPropertyDefinitionId']],
            'dropColumn' => true,
        ], [self::WRITE]);
        $this->getItem(self::ENDPOINT . '/' . $first['extraPropertyDefinitionId'], [self::READ], Response::HTTP_NOT_FOUND);
        $this->getItem(self::ENDPOINT . '/' . $second['extraPropertyDefinitionId'], [self::READ], Response::HTTP_NOT_FOUND);
        $this->assertFalse($this->storageColumnExists('product_extra', self::PROPERTY_PREFIX . 'bulk_1'));

        // An unknown id does not stop the batch: the valid one is deleted and the failure reported.
        $this->bulkCommandItemsWithExpectedErrors('DELETE', self::ENDPOINT . '/bulk-delete', [
            'extraPropertyDefinitionIds' => [$third['extraPropertyDefinitionId'], 999999999],
            'dropColumn' => true,
        ], null, [self::WRITE]);
        $this->getItem(self::ENDPOINT . '/' . $third['extraPropertyDefinitionId'], [self::READ], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidExtraPropertyDefinition(): void
    {
        // Resource validation.
        $response = $this->createItem(self::ENDPOINT, ['type' => 'string'], [self::WRITE], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'entityName', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'propertyName', 'message' => 'This value should not be blank.'],
        ], $response);

        $response = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'bad_type',
            'type' => 'bogus',
        ], [self::WRITE], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'type', 'message' => 'The value you selected is not a valid choice.'],
        ], $response);

        // The command refuses a constraints DSL it cannot parse.
        $response = $this->createItem(self::ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::PROPERTY_PREFIX . 'bad_dsl',
            'constraints' => 'Nope',
        ], [self::WRITE], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertStringContainsString('Unknown extra property constraint "Nope"', $response['detail']);

        // The registry refuses an entity without a table.
        $response = $this->createItem(self::ENDPOINT, [
            'entityName' => 'no_such_entity',
            'propertyName' => self::PROPERTY_PREFIX . 'orphan',
        ], [self::WRITE], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('detail', $response);

        $this->getItem(self::ENDPOINT . '/999999999', [self::READ], Response::HTTP_NOT_FOUND);
    }

    /**
     * A module-owned definition is listed and readable, but the module stays the source of truth:
     * only its shop association can be changed, and it cannot be deleted from the API.
     */
    public function testModuleOwnedDefinitionIsProtected(): void
    {
        $registry = self::getContainer()->get(ExtraPropertyRegistryInterface::class);
        $definitionId = $registry->register(new ExtraPropertyDefinition(
            entityName: 'product',
            propertyName: self::MODULE_OWNED_PROPERTY,
            moduleName: 'ps_apiresources',
        ));

        $definition = $this->getItem(self::ENDPOINT . '/' . $definitionId, [self::READ]);
        $this->assertSame('ps_apiresources', $definition['moduleName']);

        $listed = $this->listItems(self::ENDPOINT, [self::READ], ['moduleName' => 'ps_apiresources']);
        $this->assertSame(1, $listed['totalItems']);
        $this->assertSame($definitionId, $listed['items'][0]['extraPropertyDefinitionId']);

        $this->partialUpdateItem(self::ENDPOINT . '/' . $definitionId, ['labelWording' => 'Hijacked'], [self::WRITE], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem(self::ENDPOINT . '/' . $definitionId, [self::WRITE], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The shop association is the one field the merchant owns on a module definition.
        $updated = $this->partialUpdateItem(self::ENDPOINT . '/' . $definitionId, ['shopIds' => [1]], [self::WRITE]);
        $this->assertSame([1], $updated['shopIds']);
        $reverted = $this->partialUpdateItem(self::ENDPOINT . '/' . $definitionId, ['shopIds' => []], [self::WRITE]);
        $this->assertNull($reverted['shopIds']);
    }

    private function storageColumnExists(string $table, string $column): bool
    {
        $count = \Db::getInstance()->getValue(sprintf(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "%s" AND COLUMN_NAME = "%s"',
            pSQL(_DB_PREFIX_ . $table),
            pSQL($column)
        ));

        return (int) $count > 0;
    }
}
