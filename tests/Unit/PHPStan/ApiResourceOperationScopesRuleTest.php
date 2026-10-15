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

namespace PsApiResourcesTest\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PsApiResourcesTest\PHPStan\ApiResourceOperationScopesRule;

/**
 * @extends RuleTestCase<ApiResourceOperationScopesRule>
 */
final class ApiResourceOperationScopesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ApiResourceOperationScopesRule();
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function testOperationsWithoutScopeAreReported(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/operation_scopes.php'],
            [
                [
                    'API operation CQRSDelete /stubs/{stubId} has no scope. Every endpoint must declare at least one scope (e.g. scopes: [\'product_write\']).',
                    38,
                ],
                [
                    'API operation CQRSDelete /stubs/bulk-delete has no scope. Every endpoint must declare at least one scope (e.g. scopes: [\'product_write\']).',
                    41,
                ],
                [
                    'API operation PaginatedList /stubs/archived has no scope. Every endpoint must declare at least one scope (e.g. scopes: [\'product_write\']).',
                    49,
                ],
            ]
        );
    }

    public function testClassWithoutApiResourceIsIgnored(): void
    {
        $this->analyse([__DIR__ . '/Fixture/skip_no_attribute.php'], []);
    }
}
