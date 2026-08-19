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

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\Resetter\ProductResetter;

class VirtualProductFileEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'add virtual product file endpoint' => [
            'POST',
            '/products/1/virtual-file',
        ];

        yield 'update virtual product file endpoint' => [
            'PATCH',
            '/products/1/virtual-file/1',
        ];

        yield 'delete virtual product file endpoint' => [
            'DELETE',
            '/products/virtual-file/1',
        ];
    }

    public function testAddVirtualProductFile(): array
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_VIRTUAL,
            'names' => [
                'en-US' => 'virtual product',
                'fr-FR' => 'produit virtuel',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $createdFile = $this->createItem(
            sprintf('/products/%d/virtual-file', $productId),
            [
                'filePath' => $this->prepareVirtualFile(),
                'displayName' => 'user manual',
                'accessDays' => 5,
                'downloadTimesLimit' => 10,
                'expirationDate' => '2035-01-15 00:00:00',
            ],
            ['product_write']
        );

        $this->assertArrayHasKey('virtualProductFileId', $createdFile);
        $virtualProductFileId = $createdFile['virtualProductFileId'];
        $this->assertArrayHasKey('fileName', $createdFile);
        // The stored file name is a generated unique hash
        $fileName = $createdFile['fileName'];

        $this->assertEquals(
            [
                'productId' => $productId,
                'virtualProductFileId' => $virtualProductFileId,
                'fileName' => $fileName,
                'displayName' => 'user manual',
                'accessDays' => 5,
                'downloadTimesLimit' => 10,
                'expirationDate' => '2035-01-15 00:00:00',
            ],
            $createdFile
        );

        // The product GET endpoint exposes the file as its virtualProductFile
        $product = $this->getItem(sprintf('/products/%d', $productId), ['product_read']);
        $this->assertEquals(
            [
                'id' => $virtualProductFileId,
                'fileName' => $fileName,
                'displayName' => 'user manual',
                'accessDays' => 5,
                'downloadTimesLimit' => 10,
                'expirationDate' => '2035-01-15 00:00:00',
            ],
            $product['virtualProductFile']
        );

        return [
            'productId' => $productId,
            'virtualProductFileId' => $virtualProductFileId,
            'fileName' => $fileName,
        ];
    }

    /**
     * @depends testAddVirtualProductFile
     */
    public function testUpdateVirtualProductFile(array $fixtures): array
    {
        $updatedFile = $this->partialUpdateItem(
            sprintf('/products/%d/virtual-file/%d', $fixtures['productId'], $fixtures['virtualProductFileId']),
            [
                'displayName' => 'updated manual',
                'accessDays' => 30,
            ],
            ['product_write']
        );

        // The PATCH endpoint returns the same full representation as the POST one
        $this->assertEquals(
            [
                'productId' => $fixtures['productId'],
                'virtualProductFileId' => $fixtures['virtualProductFileId'],
                'fileName' => $fixtures['fileName'],
                'displayName' => 'updated manual',
                'accessDays' => 30,
                'downloadTimesLimit' => 10,
                'expirationDate' => '2035-01-15 00:00:00',
            ],
            $updatedFile
        );

        $product = $this->getItem(sprintf('/products/%d', $fixtures['productId']), ['product_read']);
        $this->assertEquals(
            [
                'id' => $fixtures['virtualProductFileId'],
                'fileName' => $fixtures['fileName'],
                'displayName' => 'updated manual',
                'accessDays' => 30,
                'downloadTimesLimit' => 10,
                'expirationDate' => '2035-01-15 00:00:00',
            ],
            $product['virtualProductFile']
        );

        return $fixtures;
    }

    /**
     * @depends testUpdateVirtualProductFile
     */
    public function testDeleteVirtualProductFile(array $fixtures): void
    {
        $this->deleteItem(sprintf('/products/virtual-file/%d', $fixtures['virtualProductFileId']), ['product_write']);

        // The product has no virtual file anymore
        $product = $this->getItem(sprintf('/products/%d', $fixtures['productId']), ['product_read']);
        $this->assertArrayNotHasKey('virtualProductFile', $product);

        // The file cannot be deleted twice
        $this->deleteItem(
            sprintf('/products/virtual-file/%d', $fixtures['virtualProductFileId']),
            ['product_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testAddVirtualFileOnStandardProductIsRejected(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'standard product',
                'fr-FR' => 'produit standard',
            ],
        ], ['product_write']);

        $this->createItem(
            sprintf('/products/%d/virtual-file', $product['productId']),
            [
                'filePath' => $this->prepareVirtualFile(),
                'displayName' => 'user manual',
            ],
            ['product_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testInvalidVirtualProductFile(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_VIRTUAL,
            'names' => [
                'en-US' => 'virtual product without file',
                'fr-FR' => 'produit virtuel sans fichier',
            ],
        ], ['product_write']);

        // The mandatory creation fields are missing
        $validationErrorsResponse = $this->createItem(
            sprintf('/products/%d/virtual-file', $product['productId']),
            [
                'accessDays' => 5,
            ],
            ['product_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'filePath',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'displayName',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * Creates a throwaway file to attach: the API moves (and removes) the source file,
     * so each request needs a fresh copy.
     */
    private function prepareVirtualFile(): string
    {
        $filePath = rtrim(sys_get_temp_dir(), '/') . '/virtual-product-file-test.txt';
        file_put_contents($filePath, 'virtual product file test content');

        return $filePath;
    }
}
