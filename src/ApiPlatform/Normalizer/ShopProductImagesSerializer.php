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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\ShopProductImages;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\ProductImageSetting;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\SetProductImagesForAllShopCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\QueryResult\Shop\ShopImageAssociation;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\QueryResult\Shop\ShopProductImages as ShopProductImagesResult;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\QueryResult\Shop\ShopProductImagesCollection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Handles the formats of the ShopProductImages resource, which neither CQRSQueryMapping nor the
 * generic serializer can express:
 *  - the request body and the response are the list of associations itself, not the resource object
 *  - the query result holds {imageId, cover} pairs per shop, exposed as imageIds and coverImageId
 *  - the command takes one setting per image with its shops, through an addProductSetting() adder
 */
class ShopProductImagesSerializer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param ShopProductImagesCollection|ShopProductImages $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if ($object instanceof ShopProductImages) {
            return $object->shopImages;
        }

        $shopImages = [];
        /** @var ShopProductImagesResult $shopProductImages */
        foreach ($object as $shopProductImages) {
            $imageIds = [];
            $coverImageId = null;
            /** @var ShopImageAssociation $image */
            foreach ($shopProductImages->getProductImages() as $image) {
                $imageIds[] = $image->getImageId();
                if ($image->isCover()) {
                    $coverImageId = $image->getImageId();
                }
            }
            $shopImages[] = [
                'shopId' => $shopProductImages->getShopId(),
                'imageIds' => $imageIds,
                'coverImageId' => $coverImageId,
            ];
        }

        return ['shopImages' => $shopImages];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ShopProductImagesCollection
            || ($data instanceof ShopProductImages && $this->isHttpContext($context));
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        if ($type === ShopProductImages::class) {
            // CQRSApiSerializer merged the URI variables and context parameters into the decoded list,
            // the associations are the integer keys
            $shopProductImages = new ShopProductImages();
            $shopProductImages->productId = (int) ($data['productId'] ?? 0);
            $shopProductImages->shopImages = array_values(array_filter($data, 'is_int', ARRAY_FILTER_USE_KEY));

            return $shopProductImages;
        }

        $shopIdsByImageId = [];
        foreach ($data['shopImages'] ?? [] as $shopImages) {
            $shopId = (int) ($shopImages['shopId'] ?? 0);
            foreach ($shopImages['imageIds'] ?? [] as $imageId) {
                if (!in_array($shopId, $shopIdsByImageId[(int) $imageId] ?? [], true)) {
                    $shopIdsByImageId[(int) $imageId][] = $shopId;
                }
            }
        }

        $command = new SetProductImagesForAllShopCommand((int) $data['productId']);
        foreach ($shopIdsByImageId as $imageId => $shopIds) {
            $command->addProductSetting(new ProductImageSetting($imageId, $shopIds));
        }

        return $command;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === SetProductImagesForAllShopCommand::class
            || ($type === ShopProductImages::class && is_array($data) && $this->isHttpContext($context));
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            SetProductImagesForAllShopCommand::class => true,
            ShopProductImages::class => true,
            ShopProductImagesCollection::class => true,
        ];
    }

    /**
     * API Platform sets the resource class in the context of the HTTP (de)serialization, the CQRS
     * processor and provider (de)normalize the resource without it and keep using its shopImages property.
     */
    private function isHttpContext(array $context): bool
    {
        return ($context['resource_class'] ?? null) === ShopProductImages::class;
    }
}
