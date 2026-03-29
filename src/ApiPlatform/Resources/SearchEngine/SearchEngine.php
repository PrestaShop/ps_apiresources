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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SearchEngine;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Command\AddSearchEngineCommand;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Command\DeleteSearchEngineCommand;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Command\EditSearchEngineCommand;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Exception\DeleteSearchEngineException;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Exception\SearchEngineException;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Exception\SearchEngineNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\SearchEngine\Query\GetSearchEngineForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/search-engines/{searchEngineId}',
            requirements: ['searchEngineId' => '\d+'],
            CQRSQuery: GetSearchEngineForEditing::class,
            scopes: ['search_engine_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/search-engines',
            CQRSCommand: AddSearchEngineCommand::class,
            CQRSQuery: GetSearchEngineForEditing::class,
            scopes: ['search_engine_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/search-engines/{searchEngineId}',
            requirements: ['searchEngineId' => '\d+'],
            CQRSCommand: EditSearchEngineCommand::class,
            CQRSQuery: GetSearchEngineForEditing::class,
            scopes: ['search_engine_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/search-engines/{searchEngineId}',
            requirements: ['searchEngineId' => '\d+'],
            CQRSCommand: DeleteSearchEngineCommand::class,
            scopes: ['search_engine_write'],
        ),
    ],
    exceptionToStatus: [
        SearchEngineNotFoundException::class => Response::HTTP_NOT_FOUND,
        SearchEngineException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DeleteSearchEngineException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SearchEngine
{
    #[ApiProperty(identifier: true)]
    public int $searchEngineId;

    #[Assert\NotBlank]
    public string $server;

    #[Assert\NotBlank]
    public string $queryKey;
}
