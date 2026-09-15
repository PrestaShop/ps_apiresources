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
 *
 * The payload uses the same shape as the GET response, grouped by shop ([{shopId, images: [{imageId, cover}]}]),
 * while the command expects one setting per image with the list of its shops, so the groups are inverted here.
 * The cover flag is read-only and ignored.
 */
class SetProductImagesForAllShopSerializer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        $shopIdsByImageId = [];
        foreach ($data['shopImages'] ?? [] as $shopImages) {
            $shopId = (int) ($shopImages['shopId'] ?? 0);
            foreach ($shopImages['images'] ?? [] as $image) {
                $imageId = (int) ($image['imageId'] ?? 0);
                if (!in_array($shopId, $shopIdsByImageId[$imageId] ?? [], true)) {
                    $shopIdsByImageId[$imageId][] = $shopId;
                }
            }
        }

        $command = new SetProductImagesForAllShopCommand((int) $data['productId']);
        foreach ($shopIdsByImageId as $imageId => $shopIds) {
            $command->addProductSetting(new ProductImageSetting($imageId, $shopIds));
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
