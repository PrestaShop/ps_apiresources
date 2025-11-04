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

namespace PsApiResourcesTest\Unit\ApiPlatform\Resources\Product;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\GenerateProductCombinations;

class GenerateProductCombinationsValidationTest extends TestCase
{
    public function testProductIdHasPositiveConstraint(): void
    {
        self::assertContains(
            'Symfony\Component\Validator\Constraints\Positive',
            $this->getAttributeNames('productId')
        );
    }

    public function testGroupedAttributeIdsHasNotBlankConstraint(): void
    {
        self::assertContains(
            'Symfony\Component\Validator\Constraints\NotBlank',
            $this->getAttributeNames('groupedAttributeIds')
        );
    }

    public function testGroupedAttributeIdsHasArrayTypeConstraint(): void
    {
        self::assertContains(
            'Symfony\Component\Validator\Constraints\Type',
            $this->getAttributeNames('groupedAttributeIds')
        );
    }

    /**
     * @return string[]
     */
    private function getAttributeNames(string $propertyName): array
    {
        $property = new \ReflectionProperty(GenerateProductCombinations::class, $propertyName);

        return array_map(
            static fn ($attribute): string => $attribute->getName(),
            $property->getAttributes()
        );
    }
}
