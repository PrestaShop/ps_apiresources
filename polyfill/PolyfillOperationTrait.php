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

namespace PrestaShopBundle\ApiPlatform\Metadata;

/**
 * An operation of a newer core on an older one: the arguments the parent operation doesn't know go to the extra
 * properties, and the operation requires the version introducing it, so the version filter removes it.
 */
trait PolyfillOperationTrait
{
    public function __construct(mixed ...$arguments)
    {
        $knownArguments = array_map(
            fn (\ReflectionParameter $parameter) => $parameter->getName(),
            (new \ReflectionMethod(parent::class, '__construct'))->getParameters()
        );
        $extraProperties = $arguments['extraProperties'] ?? [];
        foreach ($arguments as $name => $value) {
            if (is_string($name) && !in_array($name, $knownArguments, true)) {
                $extraProperties[$name] = $value;
                unset($arguments[$name]);
            }
        }
        if (version_compare($extraProperties['minVersion'] ?? '0', self::POLYFILL_MIN_VERSION, '<')) {
            $extraProperties['minVersion'] = self::POLYFILL_MIN_VERSION;
        }
        $arguments['extraProperties'] = $extraProperties;

        parent::__construct(...$arguments);
    }
}
