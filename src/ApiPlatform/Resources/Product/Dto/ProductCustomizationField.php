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

use PrestaShop\PrestaShop\Core\Domain\Product\Customization\CustomizationFieldSettings;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\ValueObject\CustomizationFieldType;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One customization field of a product, an item of the customizationFields of SetProductCustomizationFieldsCommand.
 */
class ProductCustomizationField
{
    /**
     * The id of an existing field of the product to update it, none to add a new field.
     */
    #[Assert\Positive]
    public ?int $customizationFieldId = null;

    /**
     * 0 for a file field, 1 for a text field.
     */
    #[Assert\NotNull]
    #[Assert\Choice(choices: CustomizationFieldType::AVAILABLE_TYPES)]
    public int $type;

    /**
     * The label of the field, by locale.
     *
     * @var string[]
     */
    #[LocalizedValue]
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Sequentially([
            new Assert\Type('string'),
            new Assert\Length(max: CustomizationFieldSettings::MAX_NAME_LENGTH),
        ]),
    ])]
    public array $names;

    #[Assert\NotNull]
    public bool $required;

    #[Assert\NotNull]
    public bool $addedByModule;
}
