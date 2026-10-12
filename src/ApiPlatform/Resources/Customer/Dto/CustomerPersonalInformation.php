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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto;

/**
 * Personal information of a customer, as displayed in the back office (dates and birthday are formatted for display).
 */
class CustomerPersonalInformation
{
    public string $firstName;

    public string $lastName;

    public string $email;

    public bool $guest;

    public string $socialTitle;

    public string $birthday;

    public string $registrationDate;

    public string $lastUpdateDate;

    public string $lastVisitDate;

    public ?int $rankBySales;

    public string $shopName;

    public string $languageName;

    public CustomerSubscriptions $subscriptions;

    public bool $active;
}
