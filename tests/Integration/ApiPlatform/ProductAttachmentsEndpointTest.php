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

use PrestaShop\PrestaShop\Core\Context\LanguageContextBuilder;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\AddAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\ValueObject\AttachmentId;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\DummyFileUploader;
use Tests\Resources\Resetter\ProductResetter;

class ProductAttachmentsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'attachment',
            'attachment_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get product attachments endpoint' => ['GET', '/products/1/attachments'];
        yield 'set product attachments endpoint' => ['PUT', '/products/1/attachments'];

        // A CQRSDelete answers the resource read back from core 9.2.2, the operation is filtered out on older cores
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'remove all product attachments endpoint' => ['DELETE', '/products/1/attachments'];
        }
    }

    public function testGetAttachments(): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with attachments',
                'fr-FR' => 'produit avec documents',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $this->assertEquals(['productId' => $productId, 'attachmentIds' => []], $this->getItem('/products/' . $productId . '/attachments', ['product_read']));

        $this->getItem('/products/999999/attachments', ['product_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/products/0/attachments', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);

        return $productId;
    }

    /**
     * @depends testGetAttachments
     */
    public function testSetAttachments(int $productId): int
    {
        // @todo create the attachments through POST /attachments once the Attachment domain exposes it
        $firstAttachmentId = $this->createAttachment('First product document');
        $secondAttachmentId = $this->createAttachment('Second product document');

        $expected = ['productId' => $productId, 'attachmentIds' => [$firstAttachmentId, $secondAttachmentId]];
        $this->assertEquals($expected, $this->updateItem('/products/' . $productId . '/attachments', [
            'attachmentIds' => [$firstAttachmentId, $secondAttachmentId],
        ], ['product_write']));
        $this->assertEquals($expected, $this->getItem('/products/' . $productId . '/attachments', ['product_read']));

        // The attachments are replaced
        $expected = ['productId' => $productId, 'attachmentIds' => [$secondAttachmentId]];
        $this->assertEquals($expected, $this->updateItem('/products/' . $productId . '/attachments', [
            'attachmentIds' => [$secondAttachmentId],
        ], ['product_write']));
        $this->assertEquals($expected, $this->getItem('/products/' . $productId . '/attachments', ['product_read']));

        return $productId;
    }

    /**
     * @depends testSetAttachments
     */
    public function testSetInvalidAttachments(int $productId): void
    {
        $uri = '/products/' . $productId . '/attachments';
        $expected = $this->getItem($uri, ['product_read']);
        $attachmentId = $expected['attachmentIds'][0];

        // A missing list, an explicit null and an empty list give the same violation
        foreach ([[], ['attachmentIds' => null], ['attachmentIds' => []]] as $body) {
            $response = $this->requestApi('PUT', $uri, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'body' => json_encode((object) $body),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $this->assertValidationErrors([
                ['propertyPath' => 'attachmentIds', 'message' => 'This value should not be blank.'],
            ], $response);
        }
        $response = $this->updateItem($uri, ['attachmentIds' => ['3abc', 0]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'attachmentIds[0]', 'message' => 'This value should be of type integer.'],
            ['propertyPath' => 'attachmentIds[1]', 'message' => 'This value should be positive.'],
        ], $response);

        // Unknown attachments and products
        $this->updateItem($uri, ['attachmentIds' => [$attachmentId, 999999]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/products/999999/attachments', ['attachmentIds' => [$attachmentId]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/products/0/attachments', ['attachmentIds' => [$attachmentId]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($expected, $this->getItem($uri, ['product_read']));
    }

    public function testRemoveAllAttachmentsIsFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.2')) {
            $this->markTestSkipped('The DELETE operation exists from PrestaShop 9.2.2');
        }

        // The resource still loads: only the DELETE operation is missing on the URI
        $this->deleteItem('/products/1/attachments', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @depends testSetAttachments
     */
    public function testRemoveAllAttachments(int $productId): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');
        $uri = '/products/' . $productId . '/attachments';
        $this->assertNotEmpty($this->getItem($uri, ['product_read'])['attachmentIds']);

        $expected = ['productId' => $productId, 'attachmentIds' => []];
        $this->assertEquals($expected, $this->deleteItem($uri, ['product_write'], Response::HTTP_OK));
        $this->assertEquals($expected, $this->getItem($uri, ['product_read']));

        $this->deleteItem('/products/999999/attachments', ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/0/attachments', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function createAttachment(string $name): int
    {
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $container = static::createClient()->getContainer();

        // The AddAttachmentCommand validates the localized names against the default language, which requires the
        // language context to be built. Outside of an HTTP request it must be defined explicitly.
        /** @var LanguageContextBuilder $languageContextBuilder */
        $languageContextBuilder = $container->get('test_language_context_builder');
        $languageContextBuilder->setLanguageId($defaultLangId);
        $languageContextBuilder->setDefaultLanguageId($defaultLangId);

        $dummyFileName = 'test_text_file.txt';
        $sourceFile = DummyFileUploader::upload($dummyFileName);
        $command = new AddAttachmentCommand(
            [$defaultLangId => $name],
            [$defaultLangId => 'Description of ' . $name]
        );
        $command->setFileInformation(
            $sourceFile,
            (int) filesize($sourceFile),
            (string) mime_content_type($sourceFile),
            $dummyFileName
        );

        /** @var AttachmentId $attachmentId */
        $attachmentId = $container->get('prestashop.core.command_bus')->handle($command);

        return $attachmentId->getValue();
    }
}
