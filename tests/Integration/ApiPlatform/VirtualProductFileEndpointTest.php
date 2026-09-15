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
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
            'multipart/form-data',
        ];

        // The update is a POST so that the file can be replaced with it
        yield 'update virtual product file endpoint' => [
            'POST',
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

        // The file is uploaded with the request, so the payload is sent as form data
        $createdFile = $this->requestApi('POST', sprintf('/products/%d/virtual-file', $productId), null, ['product_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'displayName' => 'user manual',
                    // We use strings on purpose because form data are sent like strings, thus we validate here
                    // that the denormalization still works with string values
                    'accessDays' => '5',
                    'downloadTimesLimit' => '10',
                    'expirationDate' => '2035-01-15 00:00:00',
                ],
                'files' => [
                    'file' => $this->prepareVirtualFile(),
                ],
            ],
        ]);

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
        $updateUrl = sprintf('/products/%d/virtual-file/%d', $fixtures['productId'], $fixtures['virtualProductFileId']);

        // The fields can be updated with a JSON payload when the file itself is not replaced, the update is a POST
        // (and not a PATCH) so that the file can be uploaded with it, but it is still a partial update
        $updatedFile = $this->requestApi('POST', $updateUrl, [
            'displayName' => 'updated manual',
            'accessDays' => 30,
        ], ['product_write'], Response::HTTP_OK);

        // The update returns the same full representation as the creation
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

        // The file is replaced by a multipart update, which updates the other fields of the payload at the same time
        $updatedFile = $this->requestApi('POST', $updateUrl, null, ['product_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'downloadTimesLimit' => '20',
                ],
                'files' => [
                    'file' => $this->prepareVirtualFile('replacement content'),
                ],
            ],
        ]);
        $this->assertArrayHasKey('fileName', $updatedFile);
        // The stored file name is regenerated for the new file
        $this->assertNotEquals($fixtures['fileName'], $updatedFile['fileName']);
        $fixtures['fileName'] = $updatedFile['fileName'];

        $this->assertEquals(
            [
                'productId' => $fixtures['productId'],
                'virtualProductFileId' => $fixtures['virtualProductFileId'],
                'fileName' => $fixtures['fileName'],
                'displayName' => 'updated manual',
                'accessDays' => 30,
                'downloadTimesLimit' => 20,
                'expirationDate' => '2035-01-15 00:00:00',
            ],
            $updatedFile
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

        $this->requestApi('POST', sprintf('/products/%d/virtual-file', $product['productId']), null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'displayName' => 'user manual',
                ],
                'files' => [
                    'file' => $this->prepareVirtualFile(),
                ],
            ],
        ]);
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
        $createUrl = sprintf('/products/%d/virtual-file', $product['productId']);

        // The file can only be uploaded as form data, a JSON payload is not supported
        $this->createItem($createUrl, [
            'displayName' => 'user manual',
        ], ['product_write'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);

        // The mandatory creation fields are missing
        $validationErrorsResponse = $this->requestApi('POST', $createUrl, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'accessDays' => '5',
                ],
            ],
        ]);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'file',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'displayName',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);

        // The display name refuses forbidden characters and the limits are capped
        $validationErrorsResponse = $this->requestApi('POST', $createUrl, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'displayName' => 'invalid<name',
                    'accessDays' => '10000000000',
                    'downloadTimesLimit' => '10000000000',
                ],
                'files' => [
                    'file' => $this->prepareVirtualFile(),
                ],
            ],
        ]);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'displayName',
                'message' => '"invalid<name" is invalid',
            ],
            [
                'propertyPath' => 'accessDays',
                'message' => 'This value should be less than or equal to 9999999999.',
            ],
            [
                'propertyPath' => 'downloadTimesLimit',
                'message' => 'This value should be less than or equal to 9999999999.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * Creates a throwaway file to upload: the API moves (and removes) the uploaded file,
     * so each request needs a fresh copy.
     */
    private function prepareVirtualFile(string $content = 'virtual product file test content'): UploadedFile
    {
        $filePath = rtrim(sys_get_temp_dir(), '/') . '/virtual-product-file-test.txt';
        file_put_contents($filePath, $content);

        return new UploadedFile($filePath, 'user-manual.txt');
    }
}
