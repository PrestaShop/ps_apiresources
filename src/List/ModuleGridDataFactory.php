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

namespace PrestaShop\Module\APIResources\List;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\DoctrineGridDataFactory;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Query\QueryParserInterface;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShop\PrestaShop\Core\Module\ModuleRepository;

/**
 * Custom factory to enrich the data received from database.
 */
class ModuleGridDataFactory extends DoctrineGridDataFactory
{
    public function __construct(
        DoctrineQueryBuilderInterface $gridQueryBuilder,
        HookDispatcherInterface $hookDispatcher,
        QueryParserInterface $queryParser,
        string $gridId,
        protected ModuleRepository $moduleRepository,
    ) {
        parent::__construct($gridQueryBuilder, $hookDispatcher, $queryParser, $gridId);
    }

    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $gridData = parent::getData($searchCriteria);
        $newModules = [];
        foreach ($gridData->getRecords() as $moduleRecord) {
            $module = $this->moduleRepository->getModule($moduleRecord['technicalName']);
            $moduleRecord['moduleVersion'] = $module->disk->get('version');
            $newModules[] = $moduleRecord;
        }

        return new GridData(new RecordCollection($newModules), $gridData->getRecordsTotal(), $gridData->getQuery());
    }
}
