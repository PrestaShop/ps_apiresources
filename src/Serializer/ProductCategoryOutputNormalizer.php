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

namespace PrestaShop\Module\APIResources\Serializer;

use PrestaShop\PrestaShop\Core\Domain\Product\QueryResult\ProductForEditing;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProductCategoryOutputNormalizer implements NormalizerInterface
{
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProductForEditing && !empty($context['normalization_mapping']['use_product_category_normalizer']);
    }

    public function normalize($object, ?string $format = null, array $context = [])
    {
        /** @var ProductForEditing $object */
        $categoriesInformation = $object->getCategoriesInformation();

        return [
            'productId' => $object->getProductId(),
            'defaultCategoryId' => $categoriesInformation->getDefaultCategoryId(),
            'categories' => array_map(
                static fn ($cat) => [
                    'id' => $cat->getId(),
                    'name' => $cat->getName(),
                    'displayName' => $cat->getDisplayName(),
                ],
                $categoriesInformation->getCategoriesInformation()
            ),
        ];
    }
}
