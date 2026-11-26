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
 * Indexes the tags of the GetProductForEditing query result by language id, the format of every other localized
 * field of the product, so that the Product API resource reads and writes its tags in the same format (a list of
 * tags by locale, the one SetProductTagsCommand takes).
 *
 * Background:
 *   ProductBasicInformation::getLocalizedTags() returns a list of LocalizedTags objects ({languageId, tags}).
 *   Normalized as is, the list is indexed by position, which the #[LocalizedValue] conversion reads as language
 *   ids: a product with tags could not be read at all. Turning a list into a map keyed by one of its fields cannot
 *   be expressed with CQRSQueryMapping, whose "@index" placeholder only substitutes positions.
 *
 * Scope:
 *   Only the ProductBasicInformation query result is affected, its other fields are normalized unchanged.
 */
class ProductBasicInformationNormalizer implements NormalizerInterface
{
    /**
     * @param ProductBasicInformation $object
     *
     * @return array{localizedNames: array<int, string>, localizedDescriptions: array<int, string>, localizedShortDescriptions: array<int, string>, localizedTags: array<int, string[]>}
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $localizedTags = [];
        foreach ($object->getLocalizedTags() as $languageTags) {
            $localizedTags[$languageTags->getLanguageId()] = $languageTags->getTags();
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
