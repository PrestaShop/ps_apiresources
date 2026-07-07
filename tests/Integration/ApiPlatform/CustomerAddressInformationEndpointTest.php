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

class CustomerAddressInformationEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['customer', 'customer_group']);
        self::createApiClient(['customer_read', 'customer_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['customer', 'customer_group']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get address information endpoint' => [
            'GET',
            '/customers/address-informations?email=customer.address@example.com',
        ];
    }

    public function testGetCustomerAddressInformation(): void
    {
        $created = $this->createItem(
            '/customers',
            [
                'firstName' => 'Alice',
                'lastName' => 'Martin',
                'email' => 'customer.address@example.com',
                'password' => 'TestPassword123!',
                'defaultGroupId' => 3,
                'groupIds' => [3],
                'enabled' => true,
                'partnerOffersSubscribed' => false,
                'guest' => false,
            ],
            ['customer_write']
        );
        $customerId = $created['customerId'];

        $response = $this->getItem(
            '/customers/address-informations?email=customer.address@example.com',
            ['customer_read']
        );

        $this->assertEquals(
            [
                'customerId' => $customerId,
                'firstName' => 'Alice',
                'lastName' => 'Martin',
                'company' => null,
            ],
            $response
        );
    }

    public function testGetAddressInformationForUnknownEmailReturnsNotFound(): void
    {
        $this->getItem(
            '/customers/address-informations?email=does.not.exist@example.com',
            ['customer_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
