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

/**
 * Minimal stub definitions used exclusively by PHPStan rule tests.
 *
 * This module has no runtime PHP dependencies (its dependencies come from
 * the PrestaShop installation it is installed into). These stubs provide just
 * enough class/attribute definitions to let PHPStan parse the fixture files
 * without "class not found" errors, keeping test output clean.
 */

namespace ApiPlatform\Metadata {
    if (!class_exists('ApiPlatform\\Metadata\\ApiResource')) {
        #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
        class ApiResource
        {
            public function __construct(mixed ...$args)
            {
            }
        }
    }

    if (!class_exists('ApiPlatform\\Metadata\\ApiProperty')) {
        #[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS_CONSTANT | \Attribute::TARGET_METHOD)]
        class ApiProperty
        {
            public function __construct(mixed ...$args)
            {
            }
        }
    }
}

namespace PrestaShop\Decimal {
    if (!class_exists('PrestaShop\\Decimal\\DecimalNumber')) {
        class DecimalNumber
        {
            public function __construct(string $value = '0')
            {
            }
        }
    }
}
