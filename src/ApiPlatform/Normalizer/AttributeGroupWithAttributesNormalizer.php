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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Attribute\AttributeGroupWithAttributes;
use PrestaShopBundle\ApiPlatform\LocalizedValueUpdater;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Rewrites the nested `attributes[*].localizedNames` keys from language IDs to
 * language locales for the AttributeGroupWithAttributes API resource.
 *
 * Background:
 *   CQRSApiSerializer::normalizeLocalizedValues() converts id_lang → locale for
 *   properties carrying #[LocalizedValue] on the top-level API resource. It does
 *   NOT recurse into nested arrays of scalar payloads, so the per-attribute
 *   `localizedNames` returned inside `$attributes[]` stayed indexed by id_lang.
 *   Every other localized value in the public API is indexed by locale, and
 *   consumers must not care about internal language IDs — see
 *   PR https://github.com/PrestaShop/ps_apiresources/pull/390 review discussion.
 *
 * Scope:
 *   This normalizer is intentionally narrow: it triggers only for
 *   AttributeGroupWithAttributes and only rewrites the `attributes[].localizedNames`
 *   sub-arrays. The `names` / `publicNames` top-level fields keep going through
 *   the standard #[LocalizedValue] path.
 */
class AttributeGroupWithAttributesNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'ATTRIBUTE_GROUP_WITH_ATTRIBUTES_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly LocalizedValueUpdater $localizedValueUpdater,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || empty($data['attributes']) || !is_array($data['attributes'])) {
            return $data;
        }

        foreach ($data['attributes'] as &$attribute) {
            if (!is_array($attribute) || empty($attribute['localizedNames']) || !is_array($attribute['localizedNames'])) {
                continue;
            }

            // We replace the ID index with locale index using the core service
            $attribute['names'] = $this->localizedValueUpdater->denormalizeLocalizedValue(
                $attribute['localizedNames'],
                'localizedNames',
                [LocalizedValue::LOCALIZED_VALUE_PARAMETERS => ['localizedNames' => true]]
            );

            // We also change the field name to match the convention (no localized prefix), localizedName becomes names
            unset($attribute['localizedNames']);
        }
        unset($attribute);

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AttributeGroupWithAttributes && empty($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AttributeGroupWithAttributes::class => false,
        ];
    }
}
