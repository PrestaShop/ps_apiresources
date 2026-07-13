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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\MailTemplate;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\MailTemplate\Command\EditEmailBodyTemplateCommand;
use PrestaShop\PrestaShop\Core\Domain\MailTemplate\Exception\FileNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\MailTemplate\Exception\InvalidArgumentException;
use PrestaShop\PrestaShop\Core\Domain\MailTemplate\Query\GetEmailBodyTemplateForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/mail-templates/{templateName}',
            requirements: ['templateName' => '[a-zA-Z0-9_-]+'],
            CQRSQuery: GetEmailBodyTemplateForEditing::class,
            scopes: ['mail_template_read'],
            openapiContext: [
                'parameters' => [
                    ['name' => 'locale', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'source', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['core', 'module']]],
                    ['name' => 'moduleName', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'default' => '']],
                ],
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/mail-templates/{templateName}',
            requirements: ['templateName' => '[a-zA-Z0-9_-]+'],
            read: false,
            CQRSCommand: EditEmailBodyTemplateCommand::class,
            scopes: ['mail_template_write'],
        ),
    ],
    exceptionToStatus: [
        FileNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidArgumentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class MailTemplate
{
    #[ApiProperty(identifier: true)]
    public string $templateName;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $locale;

    /** 'core' or 'module' */
    #[Assert\Choice(choices: ['core', 'module'], groups: ['Create'])]
    public string $source;

    public string $moduleName = '';

    public string $htmlContent = '';

    public string $txtContent = '';
}
