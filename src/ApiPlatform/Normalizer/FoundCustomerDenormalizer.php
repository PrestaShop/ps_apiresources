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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\FoundCustomer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Turns the groups of a SearchCustomers result, indexed by group id with database names, into the list of
 * FoundCustomerGroup the resource exposes. The mapping can't do it: @index only targets positions, not the ids.
 * The enabled flag is cast too, the database returns it as a tiny integer.
 */
class FoundCustomerDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'found_customer_denormalizer_already_called';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $data['groups'] = array_values(array_map(static fn (array $group): array => [
            'groupId' => (int) $group['id_group'],
            'name' => $group['name'],
            'default' => (bool) $group['default'],
        ], $data['groups']));
        if (isset($data['enabled'])) {
            $data['enabled'] = (bool) $data['enabled'];
        }

        return $this->denormalizer->denormalize($data, $type, $format, [self::ALREADY_CALLED => true] + $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return FoundCustomer::class === $type && empty($context[self::ALREADY_CALLED]) && is_array($data['groups'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable, the support depends on the context
        return [
            FoundCustomer::class => false,
        ];
    }
}
