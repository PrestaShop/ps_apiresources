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

class AddressRequiredFieldsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['address_write']);
    }

    public static function tearDownAfterClass(): void
    {
        \Db::getInstance()->delete('required_field', "`object_name` = 'CustomerAddress'");

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set address required fields endpoint' => ['PUT', '/addresses/required-fields'];
    }

    public function testSetAddressRequiredFields(): void
    {
        $this->updateItem(
            '/addresses/required-fields',
            ['requiredFields' => ['company', 'phone']],
            ['address_write'],
            Response::HTTP_NO_CONTENT
        );

        $storedFields = \Db::getInstance()->executeS(
            'SELECT `field_name` FROM `' . _DB_PREFIX_ . "required_field` WHERE `object_name` = 'CustomerAddress'"
        );
        $fieldNames = array_column($storedFields ?: [], 'field_name');

        $this->assertContains('company', $fieldNames);
        $this->assertContains('phone', $fieldNames);
    }

    public function testSetInvalidAddressRequiredFieldIsRejected(): void
    {
        $this->updateItem(
            '/addresses/required-fields',
            ['requiredFields' => ['not_a_valid_field']],
            ['address_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
