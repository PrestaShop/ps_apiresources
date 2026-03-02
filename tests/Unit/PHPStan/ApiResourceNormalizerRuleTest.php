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
use PsApiResourcesTest\PHPStan\ApiResourceNormalizerRule;

/**
 * @extends RuleTestCase<ApiResourceNormalizerRule>
 */
final class ApiResourceNormalizerRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ApiResourceNormalizerRule();
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    /**
     * A normalizer class in the allowed list must not produce any error.
     */
    public function testAllowedNormalizerIsSkipped(): void
    {
        $this->analyse([__DIR__ . '/Fixture/normalizer_allowed.php'], []);
    }

    /**
     * A class implementing DenormalizerInterface that is NOT in the allowed list must be reported.
     */
    public function testNotAllowedNormalizerIsReported(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/normalizer_not_allowed.php'],
            [
                [
                    'Class "PrestaShop\Module\APIResources\ApiPlatform\Normalizer\NewUnapprovedNormalizer" implements a Normalizer/Denormalizer interface. Custom normalizers are not allowed without explicit approval. Fix the ApiResource property type to use scalar types instead, or add this class to the allowed list in ApiResourceNormalizerRule::ALLOWED_CLASSES.',
                    27,
                ],
            ]
        );
    }

    /**
     * A class in the Normalizer namespace that does not implement a normalizer interface
     * must be silently ignored.
     */
    public function testClassWithoutNormalizerInterfaceIsSkipped(): void
    {
        $this->analyse([__DIR__ . '/Fixture/normalizer_no_interface.php'], []);
    }
}
