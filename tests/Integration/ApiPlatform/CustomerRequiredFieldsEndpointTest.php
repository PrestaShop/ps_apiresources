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

class CustomerRequiredFieldsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['required_field']);
        self::createApiClient(['customer_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['required_field']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set required fields endpoint' => [
            'PUT',
            '/customers/required-fields',
        ];
    }

    public function testSetCustomerRequiredFields(): void
    {
        $this->updateItem(
            '/customers/required-fields',
            ['requiredFields' => ['newsletter']],
            ['customer_write'],
            Response::HTTP_NO_CONTENT
        );

        $storedFields = \Db::getInstance()->executeS(
            'SELECT `field_name` FROM `' . _DB_PREFIX_ . "required_field` WHERE `object_name` = 'Customer'"
        );
        $fieldNames = array_column($storedFields ?: [], 'field_name');

        $this->assertContains('newsletter', $fieldNames);
    }

    public function testSetInvalidCustomerRequiredFieldIsRejected(): void
    {
        $this->updateItem(
            '/customers/required-fields',
            ['requiredFields' => ['not_a_valid_field']],
            ['customer_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
