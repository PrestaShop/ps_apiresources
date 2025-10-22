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

use Tests\Resources\DatabaseDump;

class ShowcaseCardEndpointTest extends ApiTestCase
{
    protected const TEST_SHOWCASECARD = 'monitoring_card';
    protected const TEST_EMPLOYEE_ID = 1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['showcase_card_read', 'showcase_card_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['configuration']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/showcase-cards/' . self::TEST_SHOWCASECARD . '/' . self::TEST_EMPLOYEE_ID,
        ];
        yield 'put endpoint' => [
            'PUT',
            '/showcase-cards/' . self::TEST_SHOWCASECARD . '/' . self::TEST_EMPLOYEE_ID . '/close',
        ];
    }

    public function testGetShowcard(): void
    {
        $showcaseCard = $this->getItem('/showcase-cards/' . self::TEST_SHOWCASECARD . '/' . self::TEST_EMPLOYEE_ID, ['showcase_card_read']);

        $this->assertEquals(self::TEST_SHOWCASECARD, $showcaseCard['showcaseCardName']);
        $this->assertEquals(self::TEST_EMPLOYEE_ID, $showcaseCard['employeeId']);
        $this->assertEquals(false, $showcaseCard['closed']);
    }

    /**
     * @depends testGetShowcard
     */
    public function testCloseShowcard(): void
    {
        $showcaseCard = $this->updateItem('/showcase-cards/' . self::TEST_SHOWCASECARD . '/' . self::TEST_EMPLOYEE_ID . '/close', null, ['showcase_card_write']);

        $this->assertEquals(self::TEST_SHOWCASECARD, $showcaseCard['showcaseCardName']);
        $this->assertEquals(self::TEST_EMPLOYEE_ID, $showcaseCard['employeeId']);
        $this->assertEquals(true, $showcaseCard['closed']);
    }
}
