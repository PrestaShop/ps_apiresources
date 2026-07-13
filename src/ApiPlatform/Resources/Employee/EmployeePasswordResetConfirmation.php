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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Employee;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\ResetEmployeePasswordCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\InvalidResetPasswordTokenException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/employees/password-reset-confirmations',
            CQRSCommand: ResetEmployeePasswordCommand::class,
            scopes: ['employee_write'],
        ),
    ],
    exceptionToStatus: [
        InvalidResetPasswordTokenException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class EmployeePasswordResetConfirmation
{
    #[Assert\NotBlank]
    public string $resetToken;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password;
}
