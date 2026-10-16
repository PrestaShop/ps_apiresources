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

namespace PrestaShop\Module\APIResources\Command;

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class GenerateApiTrackingTableCommand extends Command
{
    private const REASON_BACK_OFFICE_UI = 'Back office UI feature, out of API scope';
    private const USELESS_DUPLICATE = 'Duplicate of another endpoint, not relevant for the Admin API';

    /**
     * CQRS commands and queries that are intentionally NOT meant to be exposed as Admin API
     * endpoints: admin-UI-only features, internal helpers used by back office forms, or flows
     * handled outside of the API. They are filtered out of the tracking table and of every
     * metric (totals, percentages, per-domain progress).
     */
    private const EXCLUDED_CQRS_CLASSES = [
        // Already covered by other endpoints, not relevant for the Admin API
        'GetCustomerForAddressCreation' => self::USELESS_DUPLICATE,
        'ResetEmployeePasswordCommand' => self::USELESS_DUPLICATE,
        'GetEmployeeEmailById' => self::USELESS_DUPLICATE,
        // GET /products/{productId} already returns the enabled status (and much more)
        'GetProductIsEnabled' => self::USELESS_DUPLICATE,
        // GET /products/{productId}/suppliers returns the associated supplier ids along with the full options
        'GetAssociatedSuppliers' => self::USELESS_DUPLICATE,
        // Back office association pickers; GET /products/search covers product/combination lookup
        'SearchProductsForAssociation' => self::USELESS_DUPLICATE,
        'SearchCombinationsForAssociation' => self::USELESS_DUPLICATE,
        // GET /products/{productId}/combinations already lists a product combinations with filters
        'SearchProductCombinations' => self::USELESS_DUPLICATE,
        // Quick access links are a back office UI customization feature.
        'AddQuickAccessCommand' => self::REASON_BACK_OFFICE_UI,
        'EditQuickAccessCommand' => self::REASON_BACK_OFFICE_UI,
        'DeleteQuickAccessCommand' => self::REASON_BACK_OFFICE_UI,
        'BulkDeleteQuickAccessCommand' => self::REASON_BACK_OFFICE_UI,
        'ToggleQuickAccessNewWindowCommand' => self::REASON_BACK_OFFICE_UI,
        'GetQuickAccessForEditing' => self::REASON_BACK_OFFICE_UI,
        // Two CMS page category back office view helpers that cannot be addressed over HTTP:
        // GetCmsPageCategoryNameForListing takes no id at all and reads id_cms_category off the
        // current request, falling back to the root category, and GetCmsPageCategoriesForBreadcrumb
        // returns an iterator-only Breadcrumb whose items expose a CmsPageCategoryId object.
        'GetCmsPageCategoryNameForListing' => self::REASON_BACK_OFFICE_UI,
        'GetCmsPageCategoriesForBreadcrumb' => self::REASON_BACK_OFFICE_UI,
        // The parent of a category is already served by GET /cms-page-categories/{id}, which
        // returns parentId. Exposing it a second time under its back office redirection name
        // would also collide: the query returns a CmsPageCategoryId, which normalizes to the
        // very key the URI identifier already occupies.
        'GetCmsPageParentCategoryIdForRedirection' => self::USELESS_DUPLICATE,
    ];

    private const GRID_DEFINITION_NAMESPACE = 'PrestaShop\\PrestaShop\\Core\\Grid\\Definition\\Factory\\';
    private const REASON_STOCK_MONITORING = 'Back office monitoring view, data already reachable through the product and category lists';
    private const REASON_CUSTOMER_TAB = 'Tab of the customer page, covered by filtering the main lists';

    /**
     * Same purpose as self::EXCLUDED_CQRS_CLASSES for the listing grids, keyed by the grid
     * definition factory class relative to self::GRID_DEFINITION_NAMESPACE.
     */
    private const EXCLUDED_GRIDS = [
        'BackupDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'EmailBodyTemplateDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'EmailLogsDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'LogGridDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        // GET /products already covers the light and per-shop product lists
        'ProductLightGridDefinitionFactory' => self::USELESS_DUPLICATE,
        'ProductShopsGridDefinitionFactory' => self::USELESS_DUPLICATE,
        'QuickAccessGridDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'RequestSqlGridDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'Security\\Session\\CustomerGridDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'Security\\Session\\EmployeeGridDefinitionFactory' => self::REASON_BACK_OFFICE_UI,
        'Monitoring\\DisabledProductGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'Monitoring\\EmptyCategoryGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'Monitoring\\NoQtyProductWithCombinationGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'Monitoring\\NoQtyProductWithoutCombinationGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'Monitoring\\ProductWithoutDescriptionGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'Monitoring\\ProductWithoutImageGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'Monitoring\\ProductWithoutPriceGridDefinitionFactory' => self::REASON_STOCK_MONITORING,
        'CustomerAddressGridDefinitionFactory' => self::REASON_CUSTOMER_TAB,
        'CustomerBoughtProductGridDefinitionFactory' => self::REASON_CUSTOMER_TAB,
        'CustomerCartGridDefinitionFactory' => self::REASON_CUSTOMER_TAB,
        'CustomerDiscountGridDefinitionFactory' => self::REASON_CUSTOMER_TAB,
        'CustomerOrderGridDefinitionFactory' => self::REASON_CUSTOMER_TAB,
        'CustomerViewedProductGridDefinitionFactory' => self::REASON_CUSTOMER_TAB,
    ];

    /**
     * Grids whose domain can't be guessed from their name (the longest CQRS domain prefixing it).
     */
    private const GRID_DOMAINS = [
        'AttributeGridDefinitionFactory' => 'AttributeGroup',
        'CustomerThreadGridDefinitionFactory' => 'CustomerService',
        'EmailBodyTemplateDefinitionFactory' => 'MailTemplate',
        'ImageTypeGridDefinitionFactory' => 'ImageSettings',
        'Monitoring\\DisabledProductGridDefinitionFactory' => 'Product',
        'Monitoring\\EmptyCategoryGridDefinitionFactory' => 'Category',
        'Monitoring\\NoQtyProductWithCombinationGridDefinitionFactory' => 'Product',
        'Monitoring\\NoQtyProductWithoutCombinationGridDefinitionFactory' => 'Product',
        'OutstandingGridDefinitionFactory' => 'Order',
        'RequestSqlGridDefinitionFactory' => 'SqlManagement',
        'TaxRuleGridDefinitionFactory' => 'TaxRulesGroup',
    ];

    private const TYPE_ORDER = ['Command' => 0, 'Query' => 1, 'List' => 2];

    // The table is pasted in https://github.com/PrestaShop/PrestaShop/issues/39630
    private const GITHUB_ISSUE_BODY_LIMIT = 65536;

    private array $cqrsEndpoints = [];
    private array $cqrsLookup = [];
    private ?array $gridEndpoints = null;
    private ?array $serviceDefinitions = null;
    private array $serviceParameters = [];

    protected function configure(): void
    {
        $this
            ->setName('prestashop:api:generate-tracking-table')
            ->setAliases(['prestashop:generate-api-tracking'])
            ->setDescription('Generate API tracking table for Admin API endpoints')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file', 'api-endpoints-tracking.md')
            ->addOption('github-token', 'g', InputOption::VALUE_OPTIONAL, 'GitHub API token for PR status detection')
            ->addOption('skip-github', null, InputOption::VALUE_NONE, 'Skip GitHub PR analysis for faster execution')
            ->addOption('include-excluded', null, InputOption::VALUE_NONE, 'Count the endpoints flagged as irrelevant for the Admin API in the table and metrics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputFile = $input->getOption('output');

        try {
            $io->title('🔍 PrestaShop API Tracking Table Generator');

            // Step 1: Get all CQRS endpoints from the core
            $io->section('🔍 Scanning CQRS endpoints from the core...');
            $cqrsEndpoints = $this->getAllCqrsEndpoints();
            $io->info(sprintf('Found %d CQRS endpoints', count($cqrsEndpoints)));

            $io->section('🔍 Scanning listing grids from the core...');
            $gridEndpoints = $this->getAllGridEndpoints();
            $io->info(sprintf('Found %d listing grids', count($gridEndpoints)));
            $cqrsEndpoints = array_merge($cqrsEndpoints, array_values($gridEndpoints));

            // Step 1bis: Discard the commands/queries/grids that are not meant to become API endpoints
            $excludedEndpoints = [];
            if (!$input->getOption('include-excluded')) {
                [$cqrsEndpoints, $excludedEndpoints] = $this->splitExcludedEndpoints($cqrsEndpoints);
                $io->info(sprintf(
                    'Excluded %d endpoints not relevant for the Admin API (%d remaining)',
                    count($excludedEndpoints),
                    count($cqrsEndpoints)
                ));
            }

            // Step 2: Scan API Platform resources directly
            $io->section('📄 Scanning API Platform resources...');
            $apiEndpoints = $this->scanApiPlatformResources();
            $io->info(sprintf('Found %d API endpoints with CQRS or grid mappings', count($apiEndpoints)));

            // Listings without a core grid (data factory declared by the module) are tracked on their own
            foreach ($apiEndpoints as $key => $apiEndpoint) {
                if (isset($apiEndpoint['resource'])) {
                    $cqrsEndpoints[] = [
                        'class' => $key,
                        'type' => 'List',
                        'domain' => $this->guessGridDomain($apiEndpoint['resource']),
                        'action' => $apiEndpoint['resource'],
                    ];
                }
            }

            // Step 3: Analyze GitHub PRs for status detection (if enabled)
            $prStatusMap = [];
            if (!$input->getOption('skip-github')) {
                $io->section('🐙 Analyzing GitHub PRs for status detection...');
                $githubToken = $input->getOption('github-token');
                $prStatusMap = $this->analyzeGitHubPullRequests($githubToken, $io);
                if (null === $prStatusMap) {
                    return Command::FAILURE;
                }
                $io->info(sprintf('Found status information for %d endpoints from GitHub PRs', count($prStatusMap)));
            }

            // Step 4: Compare and match
            $io->section('🔍 Comparing CQRS endpoints with API implementations...');
            $matchedEndpoints = $this->compareCqrsWithApi($cqrsEndpoints, $apiEndpoints, $prStatusMap);

            $apiCount = count(array_filter($matchedEndpoints, fn ($e) => $e['has_api']));
            $io->info(sprintf('Matched %d CQRS endpoints and grids with API implementations', $apiCount));

            // Step 4: Generate markdown table
            $io->section('📝 Generating markdown table...');
            $domainGroups = $this->processDomainGroups($matchedEndpoints);
            $markdown = $this->generateMarkdownTable($domainGroups, $excludedEndpoints);
            file_put_contents($outputFile, $markdown);

            $markdownLength = mb_strlen($markdown);
            if ($markdownLength > self::GITHUB_ISSUE_BODY_LIMIT) {
                $io->error(sprintf(
                    'The generated table is %d characters long, over the %d characters limit of a GitHub issue body, so it cannot be published in the tracking issue anymore. The output format needs to be refactored to make it shorter (or split it). File saved anyway to: %s',
                    $markdownLength,
                    self::GITHUB_ISSUE_BODY_LIMIT,
                    $outputFile
                ));

                return Command::FAILURE;
            }

            $totalEndpoints = count($matchedEndpoints);
            $implementedCount = $this->countImplementedEndpoints($domainGroups);
            $inProgressCount = $this->countInProgressEndpoints($domainGroups);
            $percentage = $totalEndpoints > 0 ? round(($implementedCount / $totalEndpoints) * 100, 1) : 0;
            $projectedPercentage = $totalEndpoints > 0 ? round((($implementedCount + $inProgressCount) / $totalEndpoints) * 100, 1) : 0;

            $summary = [
                'API tracking table generated successfully!',
                sprintf('📁 Saved to: %s (%d/%d characters)', $outputFile, $markdownLength, self::GITHUB_ISSUE_BODY_LIMIT),
                sprintf(
                    '📊 Summary: %d implemented, %d in progress, %d missing (%s%% complete)',
                    $implementedCount,
                    $inProgressCount,
                    $totalEndpoints - $implementedCount - $inProgressCount,
                    $percentage
                ),
                sprintf(
                    '🔮 Projected after merging open PRs: %s%% (+%s%%)',
                    $projectedPercentage,
                    round($projectedPercentage - $percentage, 1)
                ),
            ];

            if (!empty($excludedEndpoints)) {
                $summary[] = sprintf('🚫 %d endpoints excluded from the metrics (not relevant for the Admin API)', count($excludedEndpoints));
            }

            $io->success($summary);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to generate API tracking table: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getCqrsEndpointsFromCoreCommand(): array
    {
        $application = $this->getApplication();
        $command = $application->find('prestashop:list:commands-and-queries');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $command->run($input, $output);

        return $this->parseCqrsCommandOutput($output->fetch());
    }

    private function parseCqrsCommandOutput(string $output): array
    {
        $endpoints = [];
        $lines = explode("\n", trim($output));
        $currentEndpoint = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (preg_match('/^\d+\.$/', $line)) {
                if ($currentEndpoint) {
                    $endpoints[] = $currentEndpoint;
                }
                $currentEndpoint = ['class' => '', 'type' => '', 'domain' => '', 'action' => ''];
                continue;
            }

            if (0 === strpos($line, 'Class: ')) {
                $class = substr($line, 7);
                $currentEndpoint['class'] = $class;

                if (preg_match('/PrestaShop\\\\PrestaShop\\\\Core\\\\Domain\\\\([^\\\\]+)\\\\(?:.*\\\\)?(Command|Query)\\\\(.+)/', $class, $matches)) {
                    $currentEndpoint['domain'] = $matches[1];
                    $currentEndpoint['action'] = $matches[3];
                }
                continue;
            }

            if (0 === strpos($line, 'Type: ')) {
                $currentEndpoint['type'] = substr($line, 6);
                continue;
            }
        }

        if ($currentEndpoint) {
            $endpoints[] = $currentEndpoint;
        }

        return $endpoints;
    }

    private function scanApiPlatformResources(): array
    {
        $endpoints = [];
        // Core ships a few native resources (e.g. /languages), module ones come last so they take precedence
        $resourcesPaths = [
            _PS_ROOT_DIR_ . '/src/PrestaShopBundle/ApiPlatform/Resources',
            _PS_MODULE_DIR_ . 'ps_apiresources/src/ApiPlatform/Resources',
        ];

        foreach (array_filter($resourcesPaths, 'is_dir') as $resourcesPath) {
            $finder = new Finder();
            $finder->files()->name('*.php')->in($resourcesPath);

            foreach ($finder as $file) {
                $content = file_get_contents($file->getRealPath());
                $mappings = $this->extractCqrsMappingsFromResourceFile($content);
                $endpoints = array_merge($endpoints, $mappings, $this->extractGridMappingsFromResourceFile($content));
            }
        }

        return $endpoints;
    }

    /**
     * Listing operations are not CQRS based, they are matched to a core grid through their gridDataFactory,
     * or keyed by that data factory along with the resource name when no core grid uses it.
     */
    private function extractGridMappingsFromResourceFile(string $content): array
    {
        $mappings = [];

        preg_match_all('/new\s+\w+\s*(\((?:[^()]++|(?1))*\))/', $content, $matches);
        foreach ($matches[1] as $operationContent) {
            if (!preg_match('/uriTemplate:\s*[\'"]([^\'"]+)[\'"]/', $operationContent, $uriMatch)) {
                continue;
            }

            $dataFactory = $this->extractGridDataFactory($operationContent, $content);
            if (null === $dataFactory) {
                continue;
            }

            $gridKey = $this->findGridForDataFactory($dataFactory);
            if (isset($mappings[$gridKey ?? $dataFactory])) {
                continue;
            }

            $mappings[$gridKey ?? $dataFactory] = [
                'uri' => $uriMatch[1],
                'method' => 'GET',
                'grid_data_factory' => $dataFactory,
            ];
            if (null === $gridKey && preg_match('/^(?:final\s+)?class\s+(\w+)/m', $content, $classMatch)) {
                $mappings[$dataFactory]['resource'] = $classMatch[1];
            }
        }

        return $mappings;
    }

    /**
     * Return the service ID of the gridDataFactory used in the code, `X::class` references being
     * resolved through the `use` statements of $fileContent.
     */
    private function extractGridDataFactory(string $code, string $fileContent): ?string
    {
        if (!preg_match('/gridDataFactory:\s*(?:[\'"]([^\'"]+)[\'"]|([\\\\\w]+)::class)/', $code, $match)) {
            return null;
        }

        if (!empty($match[1])) {
            return $match[1];
        }

        $class = ltrim($match[2], '\\');
        if (preg_match('/^[+ ]?use\s+([\\\\\w]+\\\\' . preg_quote($class, '/') . ');/m', $fileContent, $useMatch)) {
            return $useMatch[1];
        }

        // A PR patch may not contain the use statement, the service ID is then guessed from the short name
        foreach (array_keys($this->getServiceDefinitions()) as $serviceId) {
            if (str_ends_with($serviceId, '\\' . $class)) {
                return $serviceId;
            }
        }

        return $class;
    }

    /**
     * One resource matches one grid, even when decorators are involved, best match first:
     * the BO grid data factory itself, then a decorator of it, then a service it decorates.
     */
    private function findGridForDataFactory(string $dataFactory): ?string
    {
        $grids = $this->getAllGridEndpoints();

        foreach ($grids as $key => $grid) {
            if ($grid['data_factory'] === $dataFactory) {
                return $key;
            }
        }

        $dataFactoryChain = $this->getServiceChain($dataFactory);
        foreach ($grids as $key => $grid) {
            if (in_array($grid['data_factory'], $dataFactoryChain, true)) {
                return $key;
            }
        }

        foreach ($grids as $key => $grid) {
            if (in_array($dataFactory, $this->getServiceChain($grid['data_factory']), true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Core grids, keyed by their grid factory service ID, read from the core service definitions
     * since the compiled container no longer exposes the services arguments.
     */
    private function getAllGridEndpoints(): array
    {
        if (null !== $this->gridEndpoints) {
            return $this->gridEndpoints;
        }

        $this->gridEndpoints = [];
        $definitionFactories = [];
        foreach ($this->getServiceDefinitions(true) as $serviceId => $definition) {
            if (!is_a($this->resolveServiceClass($serviceId), GridFactoryInterface::class, true)) {
                continue;
            }

            $arguments = $definition['arguments'] ?? [];
            $definitionFactory = $this->getServiceReference($arguments[0] ?? $arguments['$definitionFactory'] ?? null);
            $dataFactory = $this->getServiceReference($arguments[1] ?? $arguments['$dataFactory'] ?? null);
            if (null === $definitionFactory || null === $dataFactory) {
                continue;
            }

            // Grid factory decorators wrap another grid factory instead of a definition factory
            $definitionClass = $this->resolveServiceClass($definitionFactory);
            if (!is_a($definitionClass, GridDefinitionFactoryInterface::class, true) || isset($definitionFactories[$definitionClass])) {
                continue;
            }
            $definitionFactories[$definitionClass] = true;

            $gridName = str_starts_with($definitionClass, self::GRID_DEFINITION_NAMESPACE)
                ? substr($definitionClass, strlen(self::GRID_DEFINITION_NAMESPACE))
                : $definitionClass;

            $this->gridEndpoints[$serviceId] = [
                'class' => $serviceId,
                'type' => 'List',
                'domain' => $this->guessGridDomain($gridName),
                'action' => $gridName,
                'data_factory' => $dataFactory,
            ];
        }

        return $this->gridEndpoints;
    }

    private function guessGridDomain(string $gridName): string
    {
        if (isset(self::GRID_DOMAINS[$gridName])) {
            return self::GRID_DOMAINS[$gridName];
        }

        $domains = array_unique(array_filter(array_column($this->getAllCqrsEndpoints(), 'domain')));
        usort($domains, fn ($a, $b) => strlen($b) <=> strlen($a));

        $baseName = preg_replace('/(Grid)?DefinitionFactory$/', '', basename(str_replace('\\', '/', $gridName)));
        foreach ($domains as $domain) {
            if (str_starts_with($baseName, $domain)) {
                return $domain;
            }
        }

        return 'Unknown';
    }

    /**
     * Service definitions from the core config, plus the module config (for the data factories
     * it declares itself) unless $coreOnly.
     */
    private function getServiceDefinitions(bool $coreOnly = false): array
    {
        if (null === $this->serviceDefinitions) {
            $this->serviceDefinitions = [
                'core' => $this->parseServiceDefinitions(_PS_ROOT_DIR_ . '/src/PrestaShopBundle/Resources/config/services'),
                'module' => $this->parseServiceDefinitions(_PS_MODULE_DIR_ . 'ps_apiresources/config'),
            ];
        }

        return $coreOnly ? $this->serviceDefinitions['core'] : $this->serviceDefinitions['core'] + $this->serviceDefinitions['module'];
    }

    private function parseServiceDefinitions(string $path): array
    {
        $services = [];
        if (!is_dir($path)) {
            return $services;
        }

        $finder = new Finder();
        $finder->files()->name('*.yml')->in($path);
        foreach ($finder as $file) {
            $config = Yaml::parseFile($file->getRealPath(), Yaml::PARSE_CUSTOM_TAGS);
            $this->serviceParameters += $config['parameters'] ?? [];

            foreach ($config['services'] ?? [] as $serviceId => $definition) {
                // Skip _defaults, _instanceof and resource prototypes
                if (str_starts_with($serviceId, '_') || str_ends_with($serviceId, '\\')) {
                    continue;
                }
                if (is_string($definition)) {
                    $definition = ['alias' => $definition];
                }
                $services[$serviceId] = is_array($definition) ? $definition : [];
            }
        }

        return $services;
    }

    private function resolveServiceClass(string $serviceId): string
    {
        $services = $this->getServiceDefinitions();
        $seen = [];
        while (isset($services[$serviceId]['alias']) && !isset($seen[$serviceId])) {
            $seen[$serviceId] = true;
            $serviceId = $this->getServiceReference($services[$serviceId]['alias']) ?? $serviceId;
        }

        $class = $services[$serviceId]['class'] ?? $serviceId;
        if (is_string($class) && preg_match('/^%(.+)%$/', $class, $parameter)) {
            $class = $this->serviceParameters[$parameter[1]] ?? $class;
        }

        return is_string($class) ? ltrim($class, '\\') : $serviceId;
    }

    /**
     * Turn a YAML reference ('@id', '@?id', alias target) into a service ID.
     */
    private function getServiceReference(mixed $value): ?string
    {
        if (!is_string($value) || '' === $value || str_starts_with($value, '@=')) {
            return null;
        }

        return ltrim($value, '@?');
    }

    /**
     * The service and every service it references directly or not (decorated factories included).
     */
    private function getServiceChain(string $serviceId): array
    {
        $services = $this->getServiceDefinitions();
        $chain = [];
        $queue = [$serviceId];

        while (null !== ($currentId = array_shift($queue))) {
            if (isset($chain[$currentId])) {
                continue;
            }
            $chain[$currentId] = true;

            $definition = $services[$currentId] ?? [];
            $references = [$definition['alias'] ?? null, $definition['decorates'] ?? null];
            $arguments = $definition['arguments'] ?? [];
            array_walk_recursive($arguments, function ($argument) use (&$references) {
                if (is_string($argument) && str_starts_with($argument, '@')) {
                    $references[] = $argument;
                }
            });

            foreach ($references as $reference) {
                if (null !== ($referencedId = $this->getServiceReference($reference))) {
                    $queue[] = $referencedId;
                }
            }
        }

        return array_keys($chain);
    }

    private function extractCqrsMappingsFromResourceFile(string $content): array
    {
        $mappings = [];

        // Map CQRS operation types to HTTP methods
        $operationPatterns = [
            'CQRSCreate' => 'POST',
            'CQRSUpdate' => 'PUT',
            'CQRSPartialUpdate' => 'PATCH',
            'CQRSDelete' => 'DELETE',
            'CQRSGet' => 'GET',
        ];

        foreach ($operationPatterns as $operationType => $httpMethod) {
            preg_match_all('/new\s+' . $operationType . '\s*\((.*?)\)/s', $content, $matches);

            foreach ($matches[1] as $operationContent) {
                $mapping = $this->parseCqrsOperationContent($operationContent, $httpMethod);
                if ($mapping) {
                    $mappings[$mapping['cqrs_class']] = $mapping;
                }
            }
        }

        return $mappings;
    }

    private function parseCqrsOperationContent(string $operationContent, string $httpMethod): ?array
    {
        // Extract uriTemplate
        if (!preg_match('/uriTemplate:\s*[\'"]([^\'"]+)[\'"]/', $operationContent, $uriMatch)) {
            return null;
        }
        $uriTemplate = $uriMatch[1];

        // Extract CQRSCommand or CQRSQuery class
        $cqrsClass = null;
        if (preg_match('/CQRSCommand:\s*([^:,\s]+)::class/', $operationContent, $commandMatch)) {
            $cqrsClass = trim($commandMatch[1]);
        } elseif (preg_match('/CQRSQuery:\s*([^:,\s]+)::class/', $operationContent, $queryMatch)) {
            $cqrsClass = trim($queryMatch[1]);
        }

        if (!$cqrsClass) {
            return null;
        }

        // Convert short class name to full class name
        $fullCqrsClass = $this->findFullCqrsClassName($cqrsClass);
        if (!$fullCqrsClass) {
            return null;
        }

        return [
            'uri' => $uriTemplate,
            'method' => $httpMethod,
            'cqrs_class' => $fullCqrsClass,
            'operation' => strtolower($httpMethod) . '_' . str_replace(['/', '{', '}'], ['_', '', ''], $uriTemplate),
            'summary' => '',
        ];
    }

    private function findFullCqrsClassName(string $shortClassName): ?string
    {
        if (empty($this->cqrsLookup)) {
            foreach ($this->getAllCqrsEndpoints() as $endpoint) {
                $shortName = basename(str_replace('\\', '/', $endpoint['class']));
                $this->cqrsLookup[$shortName] = $endpoint['class'];
            }
        }

        return $this->cqrsLookup[$shortClassName] ?? null;
    }

    private function getAllCqrsEndpoints(): array
    {
        if (empty($this->cqrsEndpoints)) {
            $this->cqrsEndpoints = $this->getCqrsEndpointsFromCoreCommand();
        }

        return $this->cqrsEndpoints;
    }

    /**
     * Split the CQRS endpoints and grids into the ones that are trackable as Admin API endpoints and
     * the ones flagged in self::EXCLUDED_CQRS_CLASSES or self::EXCLUDED_GRIDS. Excluded endpoints keep
     * their exclusion reason so it can be reported in the generated markdown.
     *
     * @return array{0: array, 1: array} [$trackable, $excluded]
     */
    private function splitExcludedEndpoints(array $cqrsEndpoints): array
    {
        $trackable = [];
        $excluded = [];

        foreach ($cqrsEndpoints as $endpoint) {
            $reason = 'List' === $endpoint['type']
                ? self::EXCLUDED_GRIDS[$endpoint['action']] ?? null
                : $this->getExclusionReason($endpoint['class']);

            if (null === $reason) {
                $trackable[] = $endpoint;
                continue;
            }

            $endpoint['exclusion_reason'] = $reason;
            $excluded[] = $endpoint;
        }

        return [$trackable, $excluded];
    }

    /**
     * Return why a CQRS class is not relevant as an Admin API endpoint, or null when it is.
     * Both the short class name and the fully qualified class name are accepted as keys.
     */
    private function getExclusionReason(string $cqrsClass): ?string
    {
        $shortName = basename(str_replace('\\', '/', $cqrsClass));

        return self::EXCLUDED_CQRS_CLASSES[$cqrsClass] ?? self::EXCLUDED_CQRS_CLASSES[$shortName] ?? null;
    }

    private function compareCqrsWithApi(array $cqrsEndpoints, array $apiEndpoints, array $prStatusMap = []): array
    {
        $matched = [];

        foreach ($cqrsEndpoints as $cqrs) {
            $hasApi = isset($apiEndpoints[$cqrs['class']]);
            $apiInfo = $hasApi ? $apiEndpoints[$cqrs['class']] : null;

            // Determine status from PR analysis
            $prStatus = $prStatusMap[$cqrs['class']] ?? null;

            $matched[] = [
                'class' => $cqrs['class'],
                'type' => $cqrs['type'],
                'domain' => $cqrs['domain'],
                'action' => $cqrs['action'],
                'has_api' => $hasApi,
                'api' => $hasApi ? $apiInfo['method'] . ' ' . $apiInfo['uri'] : '',
                'api_info' => $apiInfo,
                'pr_status' => $prStatus,
                // The grid data factory to integrate, the one the API actually uses once implemented
                'service' => $apiInfo['grid_data_factory'] ?? $cqrs['data_factory'] ?? '',
            ];
        }

        return $matched;
    }

    /**
     * Returns null when the analysis failed, a table without the PR status would show every in progress endpoint as missing.
     */
    private function analyzeGitHubPullRequests(?string $githubToken, SymfonyStyle $io): ?array
    {
        $statusMap = [];
        $repoOwner = 'PrestaShop';
        $repoName = 'ps_apiresources';

        try {
            // Fetch open PRs only
            $openPRs = $this->fetchGitHubPRs($repoOwner, $repoName, 'open', $githubToken);

            $io->text(sprintf('  Found %d open PRs', count($openPRs)));

            // Analyze open PRs for "In Progress" status
            foreach ($openPRs as $pr) {
                $changedEndpoints = $this->analyzePRChanges($pr, $githubToken, $io);
                if (null === $changedEndpoints) {
                    return null;
                }
                foreach ($changedEndpoints as $endpoint) {
                    $statusMap[$endpoint] = [
                        'status' => '🚧 In Progress',
                        'pr_url' => $pr['html_url'],
                        'pr_number' => $pr['number'],
                        'pr_title' => $pr['title'],
                        'author' => $pr['user']['login'] ?? 'Unknown',
                    ];
                }
            }
        } catch (\Exception $e) {
            $io->error([
                'GitHub API analysis failed: ' . $e->getMessage(),
                'Use --skip-github to generate the table without the PR status.',
            ]);

            return null;
        }

        return $statusMap;
    }

    private function processDomainGroups(array $allEndpoints): array
    {
        $domainGroups = [];
        foreach ($allEndpoints as $endpoint) {
            $domain = $endpoint['domain'] ?: 'Unknown';

            if (!isset($domainGroups[$domain])) {
                $domainGroups[$domain] = [];
            }

            $hasApi = $endpoint['has_api'];
            $prStatus = $endpoint['pr_status'] ?? null;

            // Determine final status based on API implementation and PR status
            $finalStatus = '❌ Missing';
            $authorInfo = '';

            if ($hasApi) {
                $finalStatus = '✅ Implemented';
            } elseif ($prStatus) {
                $finalStatus = $prStatus['status'];
                $authorInfo = $prStatus['author'];
            }

            $domainGroups[$domain][] = [
                'action' => $endpoint['action'] ?: basename(str_replace('\\', '/', $endpoint['class'])),
                'type' => $endpoint['type'],
                'service' => $endpoint['service'],
                'hasApi' => $hasApi,
                'api' => $endpoint['api'],
                'status' => $finalStatus,
                'author' => $authorInfo,
                'pr_info' => $prStatus,
            ];
        }

        ksort($domainGroups);

        foreach ($domainGroups as $domain => &$endpoints) {
            usort($endpoints, function ($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return (self::TYPE_ORDER[$a['type']] ?? 3) <=> (self::TYPE_ORDER[$b['type']] ?? 3);
                }

                return strcasecmp($a['action'], $b['action']);
            });
        }

        return $domainGroups;
    }

    private function countImplementedEndpoints(array $domainGroups): int
    {
        $count = 0;
        foreach ($domainGroups as $endpoints) {
            foreach ($endpoints as $endpoint) {
                if ($endpoint['hasApi']) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    private function countInProgressEndpoints(array $domainGroups): int
    {
        $count = 0;
        foreach ($domainGroups as $endpoints) {
            foreach ($endpoints as $endpoint) {
                if (str_contains($endpoint['status'], '🚧 In Progress')) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    private function statusEmoji(string $status): string
    {
        if (str_contains($status, 'Implemented')) {
            return '✅';
        }

        if (str_contains($status, 'In Progress')) {
            return '🚧';
        }

        return '❌';
    }

    /**
     * Rows are rendered without cell padding and PR links as reference links (defined once at the end)
     * to stay under self::GITHUB_ISSUE_BODY_LIMIT.
     */
    private function generateMarkdownTable(array $domainGroups, array $excludedEndpoints): string
    {
        $prLinks = [];
        $totalEndpoints = array_sum(array_map('count', $domainGroups));
        $implementedCount = $this->countImplementedEndpoints($domainGroups);
        $inProgressCount = $this->countInProgressEndpoints($domainGroups);
        $missingCount = $totalEndpoints - $implementedCount - $inProgressCount;
        $percentage = $totalEndpoints > 0 ? round(($implementedCount / $totalEndpoints) * 100, 1) : 0;
        $projectedPercentage = $totalEndpoints > 0 ? round((($implementedCount + $inProgressCount) / $totalEndpoints) * 100, 1) : 0;

        $markdown = "# PrestaShop API Endpoints - Tracking\n\n";
        $markdown .= "This table tracks the progress of API endpoint implementations for PrestaShop CQRS commands and queries, and for the listing grids.\n\n";
        $markdown .= "## 📊 Overall Progress\n\n";
        $markdown .= "- **Total Endpoints**: $totalEndpoints\n";
        $markdown .= "- **Implemented**: $implementedCount ✅\n";
        $markdown .= "- **In Progress**: $inProgressCount 🚧\n";
        $markdown .= "- **Missing**: $missingCount ❌\n";
        $markdown .= "- **Progress**: $percentage%\n";
        $markdown .= "- **Projected progress (if all open PRs merged)**: $projectedPercentage% 🔮\n";

        if (!empty($excludedEndpoints)) {
            $markdown .= '- **Excluded**: ' . count($excludedEndpoints) . " 🚫 *(not relevant for the Admin API, not counted above)*\n";
        }

        $markdown .= "\n---\n\n";

        foreach ($domainGroups as $domain => $endpoints) {
            $domainImplemented = count(array_filter($endpoints, fn ($e) => $e['hasApi']));
            $domainTotal = count($endpoints);
            $domainPercentage = $domainTotal > 0 ? round(($domainImplemented / $domainTotal) * 100, 1) : 0;

            $markdown .= "## 🏷️ $domain\n\n";
            $markdown .= "$domainImplemented/$domainTotal ($domainPercentage%)\n\n";
            $markdown .= "|Action|Type|Status|API Endpoint|Author / PR|\n";
            $markdown .= "|-|-|-|-|-|\n";

            foreach ($endpoints as $endpoint) {
                $action = '`' . $endpoint['action'] . '`';
                if (!empty($endpoint['service'])) {
                    $action .= '<br>`' . $endpoint['service'] . '`';
                }
                $type = $endpoint['type'];
                // Keep only the emoji in the table, the legend explains it.
                $status = $this->statusEmoji($endpoint['status']);
                $api = $endpoint['api'];

                // Build author/PR info
                $authorInfo = '';
                if (!empty($endpoint['author'])) {
                    $authorInfo = $endpoint['author'];
                    if ($endpoint['pr_info'] && !empty($endpoint['pr_info']['pr_url'])) {
                        $prNumber = $endpoint['pr_info']['pr_number'];
                        $prLinks[$prNumber] = $endpoint['pr_info']['pr_url'];
                        $authorInfo = "{$endpoint['author']} [#$prNumber]";
                    }
                }

                $markdown .= "|$action|$type|$status|$api|$authorInfo|\n";
            }

            $markdown .= "\n";
        }

        $markdown .= $this->renderExcludedSection($excludedEndpoints);

        $markdown .= "## 📋 Status Legend\n\n";
        $markdown .= "- ✅ **Implemented**: API endpoint is available and working\n";
        $markdown .= "- 🚧 **In Progress**: Someone is actively working on this endpoint (PR open)\n";
        $markdown .= "- ❌ **Missing**: API endpoint needs to be implemented\n";
        $markdown .= "- 🚫 **Excluded**: Command / query / grid intentionally not exposed through the Admin API\n\n";
        $markdown .= '*Last updated: ' . date('Y-m-d H:i:s') . "*\n";

        if (!empty($prLinks)) {
            ksort($prLinks);
            $markdown .= "\n";
            foreach ($prLinks as $prNumber => $prUrl) {
                $markdown .= "[#$prNumber]: $prUrl\n";
            }
        }

        return $markdown;
    }

    /**
     * List the commands/queries/grids deliberately kept out of the Admin API, grouped by reason.
     * They are listed for transparency only and are not part of any metric above.
     */
    private function renderExcludedSection(array $excludedEndpoints): string
    {
        if (empty($excludedEndpoints)) {
            return '';
        }

        $actionsByReason = [];
        foreach ($excludedEndpoints as $endpoint) {
            $actionsByReason[$endpoint['exclusion_reason']][] = $endpoint['action'] ?: basename(str_replace('\\', '/', $endpoint['class']));
        }
        ksort($actionsByReason);

        $markdown = "## 🚫 Excluded from tracking\n\n";
        $markdown .= "These CQRS commands, queries and listing grids are intentionally **not** exposed through the Admin API, and not counted in any metric.\n\n";

        foreach ($actionsByReason as $reason => $actions) {
            sort($actions, SORT_STRING | SORT_FLAG_CASE);
            $markdown .= "- **$reason**: `" . implode('`, `', $actions) . "`\n";
        }

        return $markdown . "\n";
    }

    private function fetchGitHubPRs(string $owner, string $repo, string $state, ?string $token, int $limit = 500): array
    {
        // GitHub caps per_page at 100, so we paginate to collect more than 100 PRs.
        $perPage = 100;
        $headers = [
            'User-Agent: PrestaShop-API-Tracker',
            'Accept: application/vnd.github.v3+json',
        ];

        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }

        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers),
                'method' => 'GET',
            ],
        ]);

        $prs = [];
        for ($page = 1; count($prs) < $limit; ++$page) {
            $url = "https://api.github.com/repos/{$owner}/{$repo}/pulls?state={$state}&per_page={$perPage}&page={$page}";

            $response = file_get_contents($url, false, $context);
            if (false === $response) {
                throw new \RuntimeException('Failed to fetch PRs from GitHub API');
            }

            $batch = json_decode($response, true);
            if (!is_array($batch) || [] === $batch) {
                break;
            }

            $prs = array_merge($prs, $batch);

            // Last page reached when GitHub returns fewer than a full page.
            if (count($batch) < $perPage) {
                break;
            }
        }

        return array_slice($prs, 0, $limit);
    }

    private function analyzePRChanges(array $pr, ?string $token, SymfonyStyle $io): ?array
    {
        $endpoints = [];

        try {
            // Fetch PR files
            $url = $pr['url'] . '/files';
            $headers = [
                'User-Agent: PrestaShop-API-Tracker',
                'Accept: application/vnd.github.v3+json',
            ];

            if ($token) {
                $headers[] = "Authorization: token {$token}";
            }

            $context = stream_context_create([
                'http' => [
                    'header' => implode("\r\n", $headers),
                    'method' => 'GET',
                ],
            ]);

            $response = file_get_contents($url, false, $context);
            if (false === $response) {
                throw new \RuntimeException('Failed to fetch the PR files from GitHub API');
            }

            $files = json_decode($response, true);

            foreach ($files as $file) {
                $filename = $file['filename'];

                // Check if it's an API resource file
                if (str_contains($filename, 'src/ApiPlatform/Resources/') && str_ends_with($filename, '.php')) {
                    // Extract CQRS references from the patch
                    $patch = $file['patch'] ?? '';
                    $foundEndpoints = $this->extractCqrsFromPatch($patch);
                    $endpoints = array_merge($endpoints, $foundEndpoints);
                }
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to analyze the changes of PR #%d: %s', $pr['number'], $e->getMessage()));

            return null;
        }

        return array_unique($endpoints);
    }

    /**
     * Extract CQRS command/query references from a git patch.
     */
    private function extractCqrsFromPatch(string $patch): array
    {
        $endpoints = [];

        // Look for CQRS command/query references in added lines
        preg_match_all('/\+.*CQRSCommand:\s*([A-Za-z]+)::class/', $patch, $commandMatches);
        preg_match_all('/\+.*CQRSQuery:\s*([A-Za-z]+)::class/', $patch, $queryMatches);

        $allMatches = array_merge($commandMatches[1], $queryMatches[1]);

        foreach ($allMatches as $shortClassName) {
            $fullClassName = $this->findFullCqrsClassName($shortClassName);
            if ($fullClassName) {
                $endpoints[] = $fullClassName;
            }
        }

        // Listing operations, matched to their grid
        preg_match_all('/^\+.*gridDataFactory:.*$/m', $patch, $gridMatches);
        foreach ($gridMatches[0] as $line) {
            $dataFactory = $this->extractGridDataFactory($line, $patch);
            $gridKey = $dataFactory ? $this->findGridForDataFactory($dataFactory) : null;
            if ($gridKey) {
                $endpoints[] = $gridKey;
            }
        }

        return $endpoints;
    }
}
