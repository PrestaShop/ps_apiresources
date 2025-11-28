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
use Symfony\Component\Validator\Validation;

class GenerateProductCombinationsValidationTest extends TestCase
{
    public function testValidPayloadPassesValidation(): void
    {
        $dto = new GenerateProductCombinations();
        $dto->productId = 20;
        $dto->groupedAttributeIds = [
            1 => [2, 3],
            2 => [10, 14],
        ];

        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();

        $violations = $validator->validate($dto);
        self::assertCount(0, $violations);
    }

    public function testZeroProductIdFailsPositiveConstraint(): void
    {
        $dto = new GenerateProductCombinations();
        $dto->productId = 0; // Not positive
        $dto->groupedAttributeIds = [1 => [2]];

        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();

        $violations = $validator->validate($dto);
        self::assertGreaterThanOrEqual(1, count($violations));
        $messages = array_map(static fn ($v) => $v->getPropertyPath() . ':' . $v->getMessage(), iterator_to_array($violations));
        self::assertTrue($this->containsMessageForPath($messages, 'productId'));
    }

    public function testEmptyGroupedAttributeIdsFailsNotBlank(): void
    {
        $dto = new GenerateProductCombinations();
        $dto->productId = 42;
        $dto->groupedAttributeIds = [];

        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();

        $violations = $validator->validate($dto);
        self::assertGreaterThanOrEqual(1, count($violations));
        $messages = array_map(static fn ($v) => $v->getPropertyPath() . ':' . $v->getMessage(), iterator_to_array($violations));
        self::assertTrue($this->containsMessageForPath($messages, 'groupedAttributeIds'));
    }

    private function containsMessageForPath(array $messages, string $path): bool
    {
        foreach ($messages as $message) {
            if (str_starts_with($message, $path . ':')) {
                return true;
            }
        }

        return false;
    }
}
