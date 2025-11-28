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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Title;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\AddTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\DeleteTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\EditTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Title\Query\GetTitleForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/titles',
            CQRSCommand: AddTitleCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['title_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            // Form data value are all string so we disable type enforcement
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
        ),
        new CQRSDelete(
            uriTemplate: '/titles/{titleId}',
            requirements: ['titleId' => '\d+'],
            output: false,
            CQRSCommand: DeleteTitleCommand::class,
            scopes: ['title_write']
        ),
        new CQRSGet(
            uriTemplate: '/titles/{titleId}',
            requirements: ['titleId' => '\d+'],
            CQRSQuery: GetTitleForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['title_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/titles/{titleId}',
            requirements: ['titleId' => '\d+'],
            read: false,
            CQRSCommand: EditTitleCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetTitleForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['title_write']
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        TitleNotFoundException::class => Response::HTTP_NOT_FOUND,
        TitleConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Title
{
    #[ApiProperty(identifier: true)]
    public int $titleId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $names;

    public int $gender;

    public ?File $imgFile;

    public ?int $width;

    public ?int $height;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[width]' => '[imgWidth]',
        '[height]' => '[imgHeight]',
    ];

    public function setGender(string|int $gender): self
    {
        $this->gender = (int) $gender;

        return $this;
    }
}
