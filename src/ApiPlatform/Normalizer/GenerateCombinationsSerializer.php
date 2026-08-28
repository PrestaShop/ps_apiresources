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

use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\ApiPlatform\Normalizer\ShopConstraintNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class GenerateCombinationsSerializer implements DenormalizerInterface
{
    public function __construct(
        private readonly ShopConstraintNormalizer $shopConstraintNormalizer,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        $groupedAttributes = [];
        foreach ($data['groupedAttributes'] as $attributeGroup) {
            $groupedAttributes[$attributeGroup['attributeGroupId']] = array_map(static function ($attributeId): int {
                return (int) $attributeId;
            }, $attributeGroup['attributeIds']);
        }

        return new GenerateProductCombinationsCommand(
            $data['productId'],
            $groupedAttributes,
            $this->shopConstraintNormalizer->denormalize($data['_context']['shopConstraint'], ShopConstraint::class),
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null)
    {
        return $type === GenerateProductCombinationsCommand::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            GenerateProductCombinationsCommand::class => true,
            'object' => null,
            '*' => null,
        ];
    }
}
