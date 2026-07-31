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
use PrestaShopBundle\Entity\Repository\LangRepository;
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

    /**
     * @var array<int, string>|null
     */
    private ?array $localesByID = null;

    public function __construct(
        private readonly LangRepository $langRepository,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || empty($data['attributes']) || !is_array($data['attributes'])) {
            return $data;
        }

        $localesByID = $this->getLocalesByID();
        foreach ($data['attributes'] as &$attribute) {
            if (!is_array($attribute) || empty($attribute['localizedNames']) || !is_array($attribute['localizedNames'])) {
                continue;
            }
            $attribute['localizedNames'] = $this->rekeyByLocale($attribute['localizedNames'], $localesByID);
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

    /**
     * @param array<int|string, string> $localizedValue
     * @param array<int, string>        $localesByID
     *
     * @return array<int|string, string>
     */
    private function rekeyByLocale(array $localizedValue, array $localesByID): array
    {
        $result = [];
        foreach ($localizedValue as $key => $value) {
            if (is_int($key) || ctype_digit((string) $key)) {
                $id = (int) $key;
                // Fall back to the original key if the ID is unknown (defensive: the
                // upstream mapping is complete in practice, but we never want to drop
                // a translation silently).
                $result[$localesByID[$id] ?? $key] = $value;
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function getLocalesByID(): array
    {
        if ($this->localesByID !== null) {
            return $this->localesByID;
        }

        $localesByID = [];
        foreach ($this->langRepository->getMapping() as $langId => $language) {
            $localesByID[(int) $langId] = $language['locale'];
        }

        return $this->localesByID = $localesByID;
    }
}
