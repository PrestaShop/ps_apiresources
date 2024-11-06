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

namespace PrestaShop\Module\APIResources\List;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

class ModuleQueryBuilder extends AbstractDoctrineQueryBuilder
{
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        private readonly DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator,
    ) {
        parent::__construct($connection, $dbPrefix);
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $builder = $this->getModuleQueryBuilder($searchCriteria)
            ->select('m.id_module AS moduleId, m.name AS technicalName, m.active AS enabled, m.version AS installedVersion');

        $this->searchCriteriaApplicator
            ->applySorting($searchCriteria, $builder)
            ->applyPagination($searchCriteria, $builder);

        return $builder;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        return $this->getModuleQueryBuilder($searchCriteria)->select('COUNT(id_module)');
    }

    private function getModuleQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->connection
            ->createQueryBuilder()
            ->from($this->dbPrefix . 'module', 'm')
        ;

        $allowedFilters = [
            'moduleId' => 'id_module',
            'technicalName' => 'name',
            'enabled' => 'active',
            'version' => 'version',
        ];

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if (!array_key_exists($filterName, $allowedFilters)) {
                continue;
            }

            $columnName = $allowedFilters[$filterName];
            if (in_array($filterName, ['moduleId', 'enabled'])) {
                $qb->andWhere($columnName . ' = :' . $filterName);
                $qb->setParameter($filterName, $filterValue);

                continue;
            }

            $qb->andWhere($columnName . ' LIKE :' . $filterName);
            $qb->setParameter($filterName, '%' . $filterValue . '%');
        }

        return $qb;
    }
}
