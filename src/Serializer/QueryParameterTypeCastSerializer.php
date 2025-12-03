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

namespace PrestaShop\Module\APIResources\Serializer;

use PrestaShopBundle\ApiPlatform\Serializer\CQRSApiSerializer;

/**
 * Extends CQRSApiSerializer to add automatic type casting for query parameters.
 */
class QueryParameterTypeCastSerializer extends CQRSApiSerializer
{
    /**
     * {@inheritdoc}
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (is_array($data) && str_starts_with($type, 'PrestaShop\\PrestaShop\\Core\\Domain\\')) {
            $data = $this->castQueryParametersToExpectedTypes($data, $type);
        }

        return parent::denormalize($data, $type, $format, $context);
    }

    /**
     * Cast string query parameter values to their expected types based on the CQRS query constructor.
     */
    private function castQueryParametersToExpectedTypes(array $data, string $queryClass): array
    {
        try {
            $reflection = new \ReflectionClass($queryClass);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                return $data;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $paramName = $parameter->getName();

                if (!array_key_exists($paramName, $data) || !is_string($data[$paramName])) {
                    continue;
                }

                $type = $parameter->getType();

                if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
                    continue;
                }

                $data[$paramName] = match ($type->getName()) {
                    'int' => (int) $data[$paramName],
                    'float' => (float) $data[$paramName],
                    'bool' => filter_var($data[$paramName], FILTER_VALIDATE_BOOLEAN),
                    default => $data[$paramName],
                };
            }
        } catch (\ReflectionException $e) {
            return $data;
        }

        return $data;
    }
}
