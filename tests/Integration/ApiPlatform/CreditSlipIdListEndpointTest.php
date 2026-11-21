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

class CreditSlipIdListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['credit_slip_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list credit slip ids endpoint' => ['GET', '/credit-slips/ids?dateTimeFrom=2000-01-01&dateTimeTo=2099-12-31'];
    }

    public function testEmptyRangeReturnsNotFound(): void
    {
        // The domain query throws CreditSlipNotFoundException on empty
        // date ranges; that maps to a 404 response.
        $this->requestApi(
            'GET',
            '/credit-slips/ids?dateTimeFrom=1900-01-01&dateTimeTo=1900-01-02',
            null,
            ['credit_slip_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
