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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Address\OrderAddress;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OrderAddressNormalizer implements ContextAwareNormalizerInterface
{
    private const NORMALIZED_FLAG = 'order_address_normalized';

    public function __construct(
        private readonly NormalizerInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!($data instanceof OrderAddress)) {
            return false;
        }

        return empty($context[self::NORMALIZED_FLAG]);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): mixed
    {
        if ($object instanceof OrderAddress) {
            $request = $this->requestStack->getCurrentRequest();
            if (null !== $request) {
                $orderId = (int) $request->attributes->get('orderId', 0);
                if ($orderId > 0) {
                    $object->orderId = $orderId;
                }
            }
        }

        $context[self::NORMALIZED_FLAG] = true;

        return $this->decorated->normalize($object, $format, $context);
    }
}
