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

use DateTimeImmutable;
use DateTimeInterface;
use PrestaShop\PrestaShop\Core\Util\DateTime\DateTime as DateTimeUtil;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Custom normalizer for DateTimeImmutable and DateTimeInterface that handles '0000-00-00 00:00:00' as NullDateTime.
 * This normalizer has higher priority than the core one (priority 100) to handle unlimited dates correctly.
 * Registered in config/admin/services.yml
 *
 * This matches the behavior in CommandBuilder::castValue() for TYPE_DATETIME which uses DateTime::buildNullableDateTime()
 */
class DateTimeImmutableNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function denormalize($data, string $type, ?string $format = null, array $context = [])
    {
        // Normalize ISO 8601 format to standard datetime format for null date detection
        // '0000-00-00T00:00:00+00:00' should be treated as '0000-00-00 00:00:00'
        if (is_string($data) && strpos($data, '0000-00-00') === 0) {
            // Extract just the date part (YYYY-MM-DD) and time part if present
            // Handle formats like: '0000-00-00T00:00:00+00:00', '0000-00-00T00:00:00Z', etc.
            if (preg_match('/^0000-00-00(?:T00:00:00.*)?$/', $data)) {
                $data = DateTimeUtil::NULL_DATETIME;
            } elseif (preg_match('/^0000-00-00$/', $data)) {
                $data = DateTimeUtil::NULL_DATE;
            }
        }

        // Use buildNullableDateTime to handle '0000-00-00 00:00:00' correctly
        // This matches the behavior in CommandBuilder::castValue() for TYPE_DATETIME
        return DateTimeUtil::buildNullableDateTime($data);
    }

    public function supportsDenormalization($data, string $type, ?string $format = null)
    {
        // Support both DateTimeImmutable and DateTimeInterface (which is the type used in command constructors)
        return \DateTimeImmutable::class === $type || \DateTimeInterface::class === $type;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        if (!($object instanceof \DateTimeImmutable)) {
            throw new InvalidArgumentException('Expected object to be a ' . \DateTimeImmutable::class);
        }

        return $object->format(DateTimeUtil::DEFAULT_DATETIME_FORMAT);
    }

    public function supportsNormalization(mixed $data, ?string $format = null)
    {
        return $data instanceof \DateTimeImmutable;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            \DateTimeImmutable::class => true,
            \DateTimeInterface::class => true,
        ];
    }
}
