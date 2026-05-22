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
use PsApiResourcesTest\PHPStan\ApiResourceProcessorProviderRule;

/**
 * @extends RuleTestCase<ApiResourceProcessorProviderRule>
 */
final class ApiResourceProcessorProviderRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ApiResourceProcessorProviderRule();
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    /**
     * A class implementing ProcessorInterface must always be reported.
     */
    public function testProcessorIsDisallowed(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/processor_disallowed.php'],
            [
                [
                    'Class "PrestaShop\Module\APIResources\ApiPlatform\Processor\CustomProcessor" implements "ApiPlatform\State\ProcessorInterface". Custom API Platform Processors and Providers are not allowed in this module. Use the CommandProcessor or QueryProvider from PrestaShopBundle instead.',
                    28,
                ],
            ]
        );
    }

    /**
     * A class implementing ProviderInterface must always be reported.
     */
    public function testProviderIsDisallowed(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/provider_disallowed.php'],
            [
                [
                    'Class "PrestaShop\Module\APIResources\ApiPlatform\Provider\CustomProvider" implements "ApiPlatform\State\ProviderInterface". Custom API Platform Processors and Providers are not allowed in this module. Use the CommandProcessor or QueryProvider from PrestaShopBundle instead.',
                    28,
                ],
            ]
        );
    }

    /**
     * A class implementing an unrelated interface must be silently ignored.
     */
    public function testOtherInterfaceIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/Fixture/processor_other_interface.php'], []);
    }
}
