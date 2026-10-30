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

use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class ProductAttachmentsEndpointTest extends ApiTestCase
{
    private static int $productId;
    private static int $attachmentId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `product_type` = "standard" ORDER BY `id_product` ASC'
        );

        // The demo data ships no attachments, so seed one.
        $attachment = new \Attachment();
        $attachment->file = 'apitest' . substr(bin2hex(random_bytes(8)), 0, 16);
        $attachment->file_name = 'doc.pdf';
        $attachment->mime = 'application/pdf';
        foreach (\Language::getIDs(false) as $langId) {
            $attachment->name[(int) $langId] = 'API attachment';
        }
        $attachment->add();
        self::$attachmentId = (int) $attachment->id;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['product_attachment', 'attachment', 'attachment_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set attachments endpoint' => ['PUT', '/products/1/attachments'];
        yield 'remove all attachments endpoint' => ['DELETE', '/products/1/attachments'];
    }

    public function testSetProductAttachments(): int
    {
        $this->updateItem(
            '/products/' . self::$productId . '/attachments',
            ['attachmentIds' => [self::$attachmentId]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_attachment`
             WHERE `id_product` = ' . self::$productId . ' AND `id_attachment` = ' . self::$attachmentId
        );
        $this->assertSame(1, $count);

        return self::$productId;
    }

    /**
     * @depends testSetProductAttachments
     */
    public function testRemoveAllProductAttachments(int $productId): void
    {
        $this->deleteItem('/products/' . $productId . '/attachments', ['product_write']);

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_attachment` WHERE `id_product` = ' . $productId
        );
        $this->assertSame(0, $count);
    }
}
