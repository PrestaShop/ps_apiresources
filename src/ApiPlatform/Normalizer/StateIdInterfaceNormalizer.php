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

use PrestaShop\PrestaShop\Core\Domain\State\ValueObject\NoStateId;
use PrestaShop\PrestaShop\Core\Domain\State\ValueObject\StateId;
use PrestaShop\PrestaShop\Core\Domain\State\ValueObject\StateIdInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Custom normalizer for StateIdInterface to handle the interface serialization and deserialization
 */
class StateIdInterfaceNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): int
    {
        if (!$object instanceof StateIdInterface) {
            throw new \InvalidArgumentException('Expected object to be a StateIdInterface');
        }

        return $object->getValue();
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof StateIdInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            StateIdInterface::class => true,
        ];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): StateIdInterface
    {
        if (!is_int($data) && !is_null($data)) {
            throw new \InvalidArgumentException('Expected data to be an integer or null');
        }

        // Handle null or 0 as NoStateId
        if ($data === null || $data === 0) {
            return new NoStateId();
        }

        return new StateId($data);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        return $type === StateIdInterface::class && (is_int($data) || is_null($data));
    }
}
