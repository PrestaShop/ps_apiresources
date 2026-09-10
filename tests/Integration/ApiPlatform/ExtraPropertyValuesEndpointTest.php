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

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinitionRepositoryInterface;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyRegistryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

/**
 * Runtime effect of definitions created through the API on the REAL module endpoints: values are
 * exposed and written on the item endpoints (nested "extraProperties" sub-object grouped by module,
 * "_core" for definitions created here), inlined at the item root on the grid-backed and
 * CQRS-paginated lists, validated with the declared constraints, and defaulted for row-less entities.
 */
class ExtraPropertyValuesEndpointTest extends ApiTestCase
{
    private const DEFINITION_WRITE = 'extra_property_definition_write';
    private const PRODUCT_READ = 'product_read';
    private const PRODUCT_WRITE = 'product_write';
    private const DEFINITIONS_ENDPOINT = '/extra-property-definitions';

    /**
     * Existing product fixture (has combinations).
     */
    private const PRODUCT_ID = 1;

    /**
     * Definitions created here are core-owned, hence this module key in the payloads.
     */
    private const CORE_KEY = '_core';

    private const PROPERTY_PREFIX = 'apival_';
    private const NOTE = self::PROPERTY_PREFIX . 'note';
    private const LANG_NOTE = self::PROPERTY_PREFIX . 'lang_note';
    private const META = self::PROPERTY_PREFIX . 'meta';
    private const COMBO_NOTE = self::PROPERTY_PREFIX . 'combo_note';

    private const VALUE_TABLES = ['product_extra', 'product_extra_lang', 'product_attribute_extra'];

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

        // The definitions are created through the API, so they need a token: done lazily on the first
        // test rather than in the static setup.
        $this->ensureDefinitions();

        // Each test starts from row-less entities so the exact-content assertions are deterministic.
        foreach (self::VALUE_TABLES as $table) {
            if ($this->tableExists($table)) {
                \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '`');
            }
        }
    }

    private static bool $definitionsCreated = false;

    private function ensureDefinitions(): void
    {
        if (self::$definitionsCreated) {
            return;
        }

        // COMMON string on product: exposed on the item endpoints AND the grid-backed list, with a
        // constraint and a default value.
        $this->createItem(self::DEFINITIONS_ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::NOTE,
            'type' => 'string',
            'scope' => 'common',
            'defaultValue' => 'n/a',
            'constraints' => 'Length(max: 10)',
            'labelWording' => 'API note',
            'associatedGrids' => ['product'],
            'associatedApis' => ['/products', '/products/{productId}'],
        ], [self::DEFINITION_WRITE]);

        // LANG string on product: locale-keyed object on the item, single locale value on the list.
        $this->createItem(self::DEFINITIONS_ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::LANG_NOTE,
            'type' => 'string',
            'scope' => 'lang',
            'labelWording' => 'API localized note',
            'associatedApis' => ['/products', '/products/{productId}'],
        ], [self::DEFINITION_WRITE]);

        // JSON, API-only (no grid placement): decoded object, served by the batch reader on lists.
        $this->createItem(self::DEFINITIONS_ENDPOINT, [
            'entityName' => 'product',
            'propertyName' => self::META,
            'type' => 'json',
            'scope' => 'common',
            'defaultValue' => '{"a":1}',
            'associatedApis' => ['/products', '/products/{productId}'],
        ], [self::DEFINITION_WRITE]);

        // COMMON string on the combination entity (product_attribute table): CQRS-paginated list.
        $this->createItem(self::DEFINITIONS_ENDPOINT, [
            'entityName' => 'combination',
            'propertyName' => self::COMBO_NOTE,
            'type' => 'string',
            'scope' => 'common',
            'defaultValue' => 'combo-default',
            'associatedApis' => ['/products/{productId}/combinations'],
        ], [self::DEFINITION_WRITE]);

        self::$definitionsCreated = true;
    }

    private static function cleanDefinitions(): void
    {
        $registry = self::getContainer()->get(ExtraPropertyRegistryInterface::class);
        $repository = self::getContainer()->get(ExtraPropertyDefinitionRepositoryInterface::class);
        foreach ($repository->getAllDefinitions() as $definition) {
            if (str_starts_with($definition->getPropertyName(), self::PROPERTY_PREFIX)) {
                $registry->unregister($definition, true);
            }
        }
        self::$definitionsCreated = false;

        DatabaseDump::restoreTables(['extra_property_definition', 'extra_property_definition_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get product endpoint' => ['GET', '/products/' . self::PRODUCT_ID];
        yield 'update product endpoint' => ['PATCH', '/products/' . self::PRODUCT_ID];
        yield 'list products endpoint' => ['GET', '/products'];
        yield 'list combinations endpoint' => ['GET', '/products/' . self::PRODUCT_ID . '/combinations'];
    }

    /**
     * A row-less entity reads back the declared defaults, typed: the string default as a string,
     * the JSON default decoded as an object. The localized property has no stored value in any
     * language yet, and the combination-only property never leaks onto the product.
     */
    public function testDefaultsAreServedForARowlessProduct(): void
    {
        $product = $this->getItem('/products/' . self::PRODUCT_ID, [self::PRODUCT_READ]);

        $this->assertArrayHasKey(self::CORE_KEY, $product['extraProperties']);
        $core = $product['extraProperties'][self::CORE_KEY];
        $this->assertSame('n/a', $core[self::NOTE]);
        $this->assertSame(['a' => 1], $core[self::META]);
        $this->assertSame([], $core[self::LANG_NOTE]);
        $this->assertArrayNotHasKey(self::COMBO_NOTE, $core);
    }

    public function testWriteAndReadBackProductExtraProperties(): void
    {
        $written = [
            self::NOTE => 'hello',
            self::LANG_NOTE => ['en-US' => 'hello', 'fr-FR' => 'bonjour'],
            self::META => ['b' => 2, 'list' => [1, 2]],
        ];
        $patched = $this->partialUpdateItem(
            '/products/' . self::PRODUCT_ID,
            ['extraProperties' => [self::CORE_KEY => $written]],
            [self::PRODUCT_WRITE]
        );
        $this->assertEquals($written, $patched['extraProperties'][self::CORE_KEY]);

        $fetched = $this->getItem('/products/' . self::PRODUCT_ID, [self::PRODUCT_READ]);
        $this->assertEquals($written, $fetched['extraProperties'][self::CORE_KEY]);

        // A partial write touches only the given property: the localized values are kept.
        $patched = $this->partialUpdateItem(
            '/products/' . self::PRODUCT_ID,
            ['extraProperties' => [self::CORE_KEY => [self::NOTE => 'again']]],
            [self::PRODUCT_WRITE]
        );
        $this->assertSame('again', $patched['extraProperties'][self::CORE_KEY][self::NOTE]);
        $this->assertEquals(['en-US' => 'hello', 'fr-FR' => 'bonjour'], $patched['extraProperties'][self::CORE_KEY][self::LANG_NOTE]);
        $this->assertEquals(['b' => 2, 'list' => [1, 2]], $patched['extraProperties'][self::CORE_KEY][self::META]);
    }

    /**
     * The constraints declared on the definition (here Length(max: 10)) are run on every write, and
     * violations are reported under the merged path of the property.
     */
    public function testDeclaredConstraintsAreEnforced(): void
    {
        $response = $this->partialUpdateItem(
            '/products/' . self::PRODUCT_ID,
            ['extraProperties' => [self::CORE_KEY => [self::NOTE => 'way too long value']]],
            [self::PRODUCT_WRITE],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            [
                'propertyPath' => 'extraProperties.' . self::CORE_KEY . '.' . self::NOTE,
                'message' => 'This value is too long. It should have 10 characters or less.',
            ],
        ], $response);

        // Nothing was written.
        $product = $this->getItem('/products/' . self::PRODUCT_ID, [self::PRODUCT_READ]);
        $this->assertSame('n/a', $product['extraProperties'][self::CORE_KEY][self::NOTE]);
    }

    /**
     * The grid-backed product list inlines the values at the item root under extra_<module>_<property>
     * (never a nested extraProperties object): the grid-associated property comes from the grid query,
     * the API-only one from the batch reader.
     */
    public function testGridBackedListInlinesValues(): void
    {
        $this->partialUpdateItem(
            '/products/' . self::PRODUCT_ID,
            ['extraProperties' => [self::CORE_KEY => [
                self::NOTE => 'listed',
                self::LANG_NOTE => ['en-US' => 'listed-en', 'fr-FR' => 'listed-fr'],
                self::META => ['c' => 3],
            ]]],
            [self::PRODUCT_WRITE]
        );

        $list = $this->listItems('/products?orderBy=productId&sortOrder=asc', [self::PRODUCT_READ]);
        $item = $this->findListItem($list['items'], 'productId', self::PRODUCT_ID);
        $this->assertNotNull($item, 'The product written to was not present in the list');

        $this->assertArrayNotHasKey('extraProperties', $item);
        $this->assertSame('listed', $item['extra_' . self::CORE_KEY . '_' . self::NOTE]);
        $this->assertSame('listed-en', $item['extra_' . self::CORE_KEY . '_' . self::LANG_NOTE]);
        $this->assertEquals(['c' => 3], $item['extra_' . self::CORE_KEY . '_' . self::META]);
        $this->assertArrayNotHasKey('extra_' . self::CORE_KEY . '_' . self::COMBO_NOTE, $item);
    }

    /**
     * A CQRS-paginated list (no grid behind it) is enriched too, by a batched read; row-less
     * combinations get the declared default.
     */
    public function testCqrsPaginatedCombinationListIsEnriched(): void
    {
        $list = $this->listItems('/products/' . self::PRODUCT_ID . '/combinations', [self::PRODUCT_READ]);
        $this->assertNotEmpty($list['items'], 'The product fixture should have combinations');

        $key = 'extra_' . self::CORE_KEY . '_' . self::COMBO_NOTE;
        foreach ($list['items'] as $item) {
            $this->assertArrayNotHasKey('extraProperties', $item);
            $this->assertSame('combo-default', $item[$key]);
            $this->assertArrayNotHasKey('extra_' . self::CORE_KEY . '_' . self::NOTE, $item);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    private function findListItem(array $items, string $idField, int $id): ?array
    {
        foreach ($items as $item) {
            if (isset($item[$idField]) && (int) $item[$idField] === $id) {
                return $item;
            }
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        return (int) \Db::getInstance()->getValue(sprintf(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "%s"',
            pSQL(_DB_PREFIX_ . $table)
        )) > 0;
    }
}
