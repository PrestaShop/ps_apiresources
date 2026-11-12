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

namespace PrestaShop\Module\APIResources\ApiPlatform\Metadata;

use ApiPlatform\Metadata\CollectionOperationInterface;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreateCollection as CoreCQRSCreateCollection;

/*
 * This operation only exists from PrestaShop 9.2.1, and PHP fails to instantiate a resource using an operation class
 * the core lacks, which breaks the container build. The resources use this class instead: the operation of the core
 * itself when the core has it, a stand-in the version filter always removes otherwise. It is loaded by the PSR-4
 * autoloader of the module, like any of its classes: a "files" entry in composer.json would be required by the core
 * on every request, whatever the version of the module in its folder.
 *
 * Remove it, and use the operation of the core in the resources, as soon as the module requires PrestaShop >= 9.2.1.
 */
if (class_exists(CoreCQRSCreateCollection::class)) {
    class_alias(CoreCQRSCreateCollection::class, CQRSCreateCollection::class);
} else {
    #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
    class CQRSCreateCollection extends CQRSCreate implements CollectionOperationInterface
    {
        use OlderCoreOperationTrait;

        private const CORE_MIN_VERSION = '9.2.1';
    }
}
