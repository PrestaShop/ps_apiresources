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
 * If you did not receive a copy of the license and be unable to
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
use PsApiResourcesTest\PHPStan\ApiResourcePropertyTypeRule;

/**
 * @extends RuleTestCase<ApiResourcePropertyTypeRule>
 */
final class ApiResourcePropertyTypeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ApiResourcePropertyTypeRule();
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    /**
     * Valid class: all allowed types — no errors expected.
     */
    public function testValidClass(): void
    {
        $this->analyse([__DIR__ . '/Fixture/valid_class.php'], []);
    }

    /**
     * float and ?float properties must both be reported.
     */
    public function testFloatType(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/float_type.php'],
            [
                [
                    'ApiResource property $rate uses banned type "float". Use DecimalNumber instead.',
                    12,
                ],
                [
                    'ApiResource property $optionalRate uses banned type "float". Use DecimalNumber instead.',
                    13,
                ],
            ]
        );
    }

    /**
     * A property with no type declaration must be reported.
     */
    public function testMissingType(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/missing_type.php'],
            [
                [
                    'ApiResource property $name has no type declaration. Only scalar types (bool, int, string, array), DecimalNumber, DateTimeImmutable, and File are allowed.',
                    12,
                ],
            ]
        );
    }

    /**
     * A property typed with a forbidden class must be reported.
     */
    public function testInvalidObjectType(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/invalid_object_type.php'],
            [
                [
                    'ApiResource property $something has unsupported class type "Some\Forbidden\ValueObject". Only scalar types (bool, int, string, array), DecimalNumber, DateTimeImmutable, and File are allowed.',
                    19,
                ],
            ]
        );
    }

    /**
     * A class without #[ApiResource] must be silently ignored, even when
     * it uses forbidden types and is in the correct namespace.
     */
    public function testSkipClassWithoutAttribute(): void
    {
        $this->analyse([__DIR__ . '/Fixture/skip_no_attribute.php'], []);
    }
}
