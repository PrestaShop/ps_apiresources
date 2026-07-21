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

class AddressCreationCustomerInfoEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['customer_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get customer address-creation info endpoint' => ['GET', '/customers/address-creation-infos?customerEmail=foo@example.com'];
    }

    public function testGetCustomerForAddressCreation(): void
    {
        $row = \Db::getInstance()->getRow(
            'SELECT `id_customer`, `firstname`, `lastname`, `email`
             FROM `' . _DB_PREFIX_ . 'customer` WHERE `active` = 1 ORDER BY `id_customer` ASC'
        );

        $result = $this->getItem(
            '/customers/address-creation-infos?customerEmail=' . urlencode((string) $row['email']),
            ['customer_read']
        );

        $this->assertArrayHasKey('customerId', $result);
        $this->assertSame((int) $row['id_customer'], $result['customerId']);
        $this->assertSame((string) $row['firstname'], $result['firstName']);
        $this->assertSame((string) $row['lastname'], $result['lastName']);
        $this->assertArrayHasKey('company', $result);
    }

    public function testGetForUnknownEmailReturnsNotFound(): void
    {
        $this->requestApi(
            'GET',
            '/customers/address-creation-infos?customerEmail=' . urlencode('nobody-' . uniqid() . '@example.invalid'),
            null,
            ['customer_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
