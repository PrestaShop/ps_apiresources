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

class ProductExtendedEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Attachments and packs endpoints return 404 (CQRS queries not available in test env)
        yield 'get product packs endpoint' => ['GET', '/products/1/packs'];
        yield 'delete product pack endpoint' => ['DELETE', '/products/1/packs'];
        yield 'get product customization fields endpoint' => ['GET', '/products/1/customization-fields'];
        yield 'update product customization fields endpoint' => ['PUT', '/products/1/customization-fields'];
        yield 'delete product customization fields endpoint' => ['DELETE', '/products/1/customization-fields'];
        yield 'get product suppliers endpoint' => ['GET', '/products/1/suppliers'];
        yield 'update product suppliers endpoint' => ['PUT', '/products/1/suppliers'];
        yield 'get product supplier options endpoint' => ['GET', '/products/1/supplier-options'];
        yield 'update product default supplier endpoint' => ['PUT', '/products/1/default-suppliers'];
        yield 'get product attribute groups endpoint' => ['GET', '/products/1/attribute-groups'];
        yield 'get product feature values endpoint' => ['GET', '/products/1/feature-values'];
        yield 'get product is enabled endpoint' => ['GET', '/products/1/is-enableds'];
    }
}
