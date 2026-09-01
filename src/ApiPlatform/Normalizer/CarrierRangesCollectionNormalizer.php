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

use PrestaShop\PrestaShop\Core\Domain\Carrier\QueryResult\CarrierRangesCollection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Flattens the GetCarrierRanges query result into the flat list of ranges used as input by the
 * SetCarrierRangesCommand, so that the CarrierRanges API resource exposes one single format for
 * both its read and write operations.
 *
 * Background:
 *   CarrierRangesCollection groups the ranges by zone (zones[].ranges[]), while the command takes
 *   a flat list where each range carries its own zone (ranges[].zoneId). Going from one to the
 *   other means merging or splitting two levels of indexes into one, which the CQRSQueryMapping
 *   cannot express: NormalizationMapper resolves a single "@index" placeholder at a time against
 *   the data, and it substitutes each placeholder independently, so it can neither resolve a
 *   second nested placeholder nor compute a flat position out of a (zone, range) pair.
 *
 * Scope:
 *   Only the CarrierRangesCollection query result is affected. The write path keeps going through
 *   the standard CQRSCommandMapping, and no other resource reads that query result.
 */
class CarrierRangesCollectionNormalizer implements NormalizerInterface
{
    /**
     * @param CarrierRangesCollection $object
     *
     * @return array{ranges: array<array{zoneId: int, rangeFrom: float, rangeTo: float, rangePrice: float}>}
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $ranges = [];
        foreach ($object->getZones() as $zone) {
            foreach ($zone->getRanges() as $range) {
                $ranges[] = [
                    'zoneId' => $zone->getZoneId(),
                    // The boundaries and the price are DecimalNumber instances. They are cast the
                    // same way as the core DecimalNumberNormalizer does, so the decimals of this
                    // endpoint are exposed as numbers like every other decimal of the API.
                    'rangeFrom' => (float) (string) $range->getFrom(),
                    'rangeTo' => (float) (string) $range->getTo(),
                    'rangePrice' => (float) (string) $range->getPrice(),
                ];
            }
        }

        return ['ranges' => $ranges];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CarrierRangesCollection;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            CarrierRangesCollection::class => true,
        ];
    }
}
