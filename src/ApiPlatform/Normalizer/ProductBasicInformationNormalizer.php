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

use PrestaShop\PrestaShop\Core\Domain\Product\QueryResult\ProductBasicInformation;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * GetProductForEditing returns the product tags as a list of LocalizedTags value objects
 * (each holding a languageId and its tags), unlike the other localized fields which are
 * already indexed by language id. This normalizer flattens the tags into a language-id
 * indexed map so it can go through the standard LocalizedValue handling (and be exposed
 * by locale) like the other localized product fields.
 */
class ProductBasicInformationNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $localizedTags = [];
        foreach ($object->getLocalizedTags() as $localizedTag) {
            $localizedTags[$localizedTag->getLanguageId()] = $localizedTag->getTags();
        }

        return [
            'localizedNames' => $object->getLocalizedNames(),
            'localizedDescriptions' => $object->getLocalizedDescriptions(),
            'localizedShortDescriptions' => $object->getLocalizedShortDescriptions(),
            'localizedTags' => $localizedTags,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProductBasicInformation;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProductBasicInformation::class => true,
        ];
    }
}
