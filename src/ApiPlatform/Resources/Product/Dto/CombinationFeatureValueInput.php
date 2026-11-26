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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Dto;

use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One feature value to associate with a combination, an item of the featureValues of
 * SetCombinationFeatureValuesCommand: an existing value (featureValueId), or a new custom value (customValues).
 */
class CombinationFeatureValueInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public int $featureId;

    /**
     * An existing value of the feature. With customValues, the texts of this custom value are updated.
     */
    #[Assert\Positive]
    public ?int $featureValueId = null;

    /**
     * The texts of a custom value by locale: without featureValueId, a new custom value of the feature is created.
     *
     * @var string[]|null
     */
    #[LocalizedValue]
    #[Assert\All([new Assert\Type('string')])]
    public ?array $customValues = null;
}
