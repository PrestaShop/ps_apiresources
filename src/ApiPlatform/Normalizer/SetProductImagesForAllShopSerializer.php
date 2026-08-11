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

namespace PrestaShop\Module\APIResources\ApiPlatform\Normalizer;

use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\ProductImageSetting;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\SetProductImagesForAllShopCommand;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * SetProductImagesForAllShopCommand collects its ProductImageSetting value objects through
 * the addProductSetting() adder, which the generic serializer cannot drive from a JSON
 * body, so the command is built manually here (same approach as GenerateCombinationsSerializer).
 */
class SetProductImagesForAllShopSerializer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        $command = new SetProductImagesForAllShopCommand((int) $data['productId']);
        foreach ($data['shopImages'] as $productImageSetting) {
            $command->addProductSetting(new ProductImageSetting(
                (int) $productImageSetting['imageId'],
                array_map('intval', $productImageSetting['shopIds'])
            ));
        }

        return $command;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null)
    {
        return $type === SetProductImagesForAllShopCommand::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            SetProductImagesForAllShopCommand::class => true,
            'object' => null,
            '*' => null,
        ];
    }
}
