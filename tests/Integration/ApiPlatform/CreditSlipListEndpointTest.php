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

class CreditSlipListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_slip']);
        self::createApiClient(['credit_slip_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_slip']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list credit slips endpoint' => ['GET', '/credit-slips'];
    }

    public function testListCreditSlips(): void
    {
        $this->assertSame(0, $this->countItems('/credit-slips', ['credit_slip_read']));

        $firstSlipId = $this->createCreditSlip(1);
        $secondSlipId = $this->createCreditSlip(2);

        $paginatedCreditSlips = $this->listItems('/credit-slips', ['credit_slip_read']);
        $this->assertSame(2, $paginatedCreditSlips['totalItems']);

        $creditSlip = $paginatedCreditSlips['items'][0];
        $this->assertIsString($creditSlip['dateAdd']);
        unset($creditSlip['dateAdd']);
        $this->assertSame([
            'creditSlipId' => $firstSlipId,
            'orderId' => 1,
        ], $creditSlip);
        $this->assertSame($secondSlipId, $paginatedCreditSlips['items'][1]['creditSlipId']);

        $listedIds = function (array $filters): array {
            return array_column($this->listItems('/credit-slips', ['credit_slip_read'], $filters)['items'], 'creditSlipId');
        };
        $this->assertSame([$secondSlipId, $firstSlipId], array_column($this->listItems('/credit-slips?orderBy=orderId&sortOrder=desc', ['credit_slip_read'])['items'], 'creditSlipId'));
        $this->assertSame([$secondSlipId], $listedIds(['orderId' => 2]));
        $this->assertSame([$firstSlipId], $listedIds(['creditSlipId' => $firstSlipId]));
        $this->assertSame([$firstSlipId, $secondSlipId], $listedIds(['dateAdd' => ['from' => date('Y-m-d'), 'to' => date('Y-m-d')]]));
    }

    private function createCreditSlip(int $orderId): int
    {
        $creditSlip = new \OrderSlip();
        $creditSlip->id_order = $orderId;
        $creditSlip->id_customer = 1;
        $creditSlip->conversion_rate = 1;
        $creditSlip->total_products_tax_excl = 10;
        $creditSlip->total_products_tax_incl = 12;
        $creditSlip->total_shipping_tax_excl = 0;
        $creditSlip->total_shipping_tax_incl = 0;
        $creditSlip->add();

        return (int) $creditSlip->id;
    }
}
