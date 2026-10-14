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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerOrderRow;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Casts the valid flag of the customer details order rows, the database returns it as a tiny integer.
 */
class CustomerOrderRowDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'customer_order_row_denormalizer_already_called';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $data['valid'] = (bool) $data['valid'];

        return $this->denormalizer->denormalize($data, $type, $format, [self::ALREADY_CALLED => true] + $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return CustomerOrderRow::class === $type && empty($context[self::ALREADY_CALLED]) && isset($data['valid']);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable, the support depends on the context
        return [
            CustomerOrderRow::class => false,
        ];
    }
}
