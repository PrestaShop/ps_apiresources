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

namespace PrestaShop\Module\APIResources\ApiPlatform\Normalizer;

use PrestaShop\PrestaShop\Core\Domain\Customer\Command\BulkDeleteCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerDeleteMethod;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Custom denormalizer for BulkDeleteCustomerCommand that injects default deleteMethod if not provided
 */
class BulkDeleteCustomerCommandDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly DenormalizerInterface $denormalizer,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // If deleteMethod is not provided, add default value
        if (is_array($data) && (!isset($data['deleteMethod']) || $data['deleteMethod'] === null || $data['deleteMethod'] === '')) {
            $data['deleteMethod'] = CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION;
        }

        // Delegate to the next denormalizer
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === BulkDeleteCustomerCommand::class
            && is_array($data)
            && isset($data['customerIds']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            BulkDeleteCustomerCommand::class => false,
        ];
    }
}
