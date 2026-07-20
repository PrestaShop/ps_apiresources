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

class TaxRuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['tax_rule']);
        self::createApiClient(['tax_rules_group_write', 'tax_rules_group_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['tax_rule']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/tax-rules-groups/1/tax-rules'];
        yield 'get endpoint' => ['GET', '/tax-rules-groups/1/tax-rules/1'];
        yield 'update endpoint' => ['PATCH', '/tax-rules-groups/1/tax-rules/1'];
        yield 'delete endpoint' => ['DELETE', '/tax-rules-groups/1/tax-rules/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/tax-rules-groups/1/tax-rules/bulk-delete'];
    }

    public function testCreateTaxRule(): int
    {
        // Assume tax group #1 (default). Use FR (id_country=8) + tax #1.
        $groupId = (int) \Db::getInstance()->getValue(
            'SELECT `id_tax_rules_group` FROM `' . _DB_PREFIX_ . 'tax_rules_group` ORDER BY `id_tax_rules_group` ASC'
        );
        $countryId = (int) \Db::getInstance()->getValue(
            'SELECT `id_country` FROM `' . _DB_PREFIX_ . 'country` WHERE `active` = 1 ORDER BY `id_country` ASC'
        );
        $taxId = (int) \Db::getInstance()->getValue(
            'SELECT `id_tax` FROM `' . _DB_PREFIX_ . 'tax` WHERE `active` = 1 AND `deleted` = 0 ORDER BY `id_tax` ASC'
        );

        $result = $this->requestApi(
            'POST',
            '/tax-rules-groups/' . $groupId . '/tax-rules',
            ['countryId' => $countryId, 'taxId' => $taxId],
            ['tax_rules_group_write'],
            Response::HTTP_CREATED
        );

        $this->assertArrayHasKey('taxRuleId', $result);
        $this->assertIsInt($result['taxRuleId']);
        $this->assertGreaterThan(0, $result['taxRuleId']);

        return $result['taxRuleId'];
    }

    /**
     * @depends testCreateTaxRule
     */
    public function testDeleteTaxRule(int $taxRuleId): void
    {
        $groupId = (int) \Db::getInstance()->getValue(
            'SELECT `id_tax_rules_group` FROM `' . _DB_PREFIX_ . 'tax_rule` WHERE `id_tax_rule` = ' . $taxRuleId
        );

        $this->requestApi(
            'DELETE',
            '/tax-rules-groups/' . $groupId . '/tax-rules/' . $taxRuleId,
            null,
            ['tax_rules_group_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'tax_rule` WHERE `id_tax_rule` = ' . $taxRuleId
        );
        $this->assertSame(0, $stillThere);
    }
}
