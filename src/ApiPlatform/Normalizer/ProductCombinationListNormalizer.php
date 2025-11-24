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

namespace PrestaShop\Module\APIResources\ApiPlatform\Normalizer;

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\ProductCombinationList;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Custom normalizer for ProductCombinationList to return only the combinationId
 */
class ProductCombinationListNormalizer implements NormalizerInterface
{
    /**
     * @param ProductCombinationList $object
     * @param string|null $format
     * @param array $context
     *
     * @return int
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): int
    {
        return $object->combinationId;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof ProductCombinationList;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProductCombinationList::class => true,
        ];
    }
}
