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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class GenerateApiTrackingTableCommand extends Command
{
    private array $cqrsEndpoints = [];
    private array $cqrsLookup = [];

    protected function configure(): void
    {
        $this
            ->setName('prestashop:api:generate-tracking-table')
            ->setAliases(['prestashop:generate-api-tracking'])
            ->setDescription('Generate API tracking table for Admin API endpoints')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file', 'api-endpoints-tracking.md')
            ->addOption('github-token', 'g', InputOption::VALUE_OPTIONAL, 'GitHub API token for PR status detection')
            ->addOption('skip-github', null, InputOption::VALUE_NONE, 'Skip GitHub PR analysis for faster execution');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputFile = $input->getOption('output');

        try {
            $io->title('🔍 PrestaShop API Tracking Table Generator');

            // Step 1: Get all CQRS endpoints from the core
            $io->section('🔍 Scanning CQRS endpoints from the core...');
            $cqrsEndpoints = $this->getCqrsEndpointsFromCoreCommand();
            $io->info(sprintf('Found %d CQRS endpoints', count($cqrsEndpoints)));

            // Step 2: Scan API Platform resources directly
            $io->section('📄 Scanning API Platform resources...');
            $apiEndpoints = $this->scanApiPlatformResources();
            $io->info(sprintf('Found %d API endpoints with CQRS mappings', count($apiEndpoints)));

            // Step 3: Analyze GitHub PRs for status detection (if enabled)
            $prStatusMap = [];
            if (!$input->getOption('skip-github')) {
                $io->section('🐙 Analyzing GitHub PRs for status detection...');
                $githubToken = $input->getOption('github-token');
                $prStatusMap = $this->analyzeGitHubPullRequests($githubToken, $io);
                $io->info(sprintf('Found status information for %d endpoints from GitHub PRs', count($prStatusMap)));
            }

            // Step 4: Compare and match
            $io->section('🔍 Comparing CQRS endpoints with API implementations...');
            $matchedEndpoints = $this->compareCqrsWithApi($cqrsEndpoints, $apiEndpoints, $prStatusMap);

            $apiCount = count(array_filter($matchedEndpoints, fn ($e) => $e['has_api']));
            $io->info(sprintf('Matched %d CQRS endpoints with API implementations', $apiCount));

            // Step 4: Generate markdown table
            $io->section('📝 Generating markdown table...');
            $domainGroups = $this->processDomainGroups($matchedEndpoints);
            $this->generateMarkdownTable($domainGroups, $outputFile);

            $totalEndpoints = count($matchedEndpoints);
            $implementedCount = $this->countImplementedEndpoints($domainGroups);
            $inProgressCount = $this->countInProgressEndpoints($domainGroups);
            $percentage = $totalEndpoints > 0 ? round(($implementedCount / $totalEndpoints) * 100, 1) : 0;

            $io->success([
                'API tracking table generated successfully!',
                sprintf('📁 Saved to: %s', $outputFile),
                sprintf('📊 Summary: %d implemented, %d in progress, %d missing (%s%% complete)',
                    $implementedCount, $inProgressCount, $totalEndpoints - $implementedCount - $inProgressCount, $percentage),
            ]);

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
        $resourcesPath = _PS_MODULE_DIR_ . 'ps_apiresources/src/ApiPlatform/Resources';

        if (!is_dir($resourcesPath)) {
            return $endpoints;
        }

        $finder = new Finder();
        $finder->files()->name('*.php')->in($resourcesPath);

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $mappings = $this->extractCqrsMappingsFromResourceFile($content);
            $endpoints = array_merge($endpoints, $mappings);
        }

        return $endpoints;
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
            ];
        }

        return $matched;
    }

    private function analyzeGitHubPullRequests(?string $githubToken, SymfonyStyle $io): array
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
                $changedEndpoints = $this->analyzePRChanges($pr, $githubToken);
                foreach ($changedEndpoints as $endpoint) {
                    $statusMap[$endpoint] = [
                        'status' => '🚧 In Progress',
                        'pr_url' => $pr['html_url'],
                        'pr_title' => $pr['title'],
                        'assignee' => $pr['assignee']['login'] ?? $pr['user']['login'] ?? 'Unknown',
                    ];
                }
            }
        } catch (\Exception $e) {
            $io->warning('GitHub API analysis failed: ' . $e->getMessage());
            $io->text('Continuing without PR status detection...');
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
            $assigneeInfo = '';

            if ($hasApi) {
                $finalStatus = '✅ Implemented';
            } elseif ($prStatus) {
                $finalStatus = $prStatus['status'];
                $assigneeInfo = $prStatus['assignee'];
            }

            $domainGroups[$domain][] = [
                'action' => $endpoint['action'] ?: basename(str_replace('\\', '/', $endpoint['class'])),
                'type' => $endpoint['type'],
                'hasApi' => $hasApi,
                'api' => $endpoint['api'],
                'status' => $finalStatus,
                'assignee' => $assigneeInfo,
                'pr_info' => $prStatus,
            ];
        }

        ksort($domainGroups);

        foreach ($domainGroups as $domain => &$endpoints) {
            usort($endpoints, function ($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return 'Command' === $a['type'] ? -1 : 1;
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

    private function generateMarkdownTable(array $domainGroups, string $outputFile): void
    {
        $totalEndpoints = array_sum(array_map('count', $domainGroups));
        $implementedCount = $this->countImplementedEndpoints($domainGroups);
        $inProgressCount = $this->countInProgressEndpoints($domainGroups);
        $missingCount = $totalEndpoints - $implementedCount - $inProgressCount;
        $percentage = $totalEndpoints > 0 ? round(($implementedCount / $totalEndpoints) * 100, 1) : 0;

        $markdown = "# PrestaShop API Endpoints - Tracking\n\n";
        $markdown .= "This table tracks the progress of API endpoint implementations for PrestaShop CQRS commands and queries.\n\n";
        $markdown .= "## 📊 Overall Progress\n\n";
        $markdown .= "- **Total Endpoints**: $totalEndpoints\n";
        $markdown .= "- **Implemented**: $implementedCount ✅\n";
        $markdown .= "- **In Progress**: $inProgressCount 🚧\n";
        $markdown .= "- **Missing**: $missingCount ❌\n";
        $markdown .= "- **Progress**: $percentage%\n\n";
        $markdown .= "---\n\n";

        foreach ($domainGroups as $domain => $endpoints) {
            $domainImplemented = count(array_filter($endpoints, fn ($e) => $e['hasApi']));
            $domainTotal = count($endpoints);
            $domainPercentage = $domainTotal > 0 ? round(($domainImplemented / $domainTotal) * 100, 1) : 0;

            $markdown .= "## 🏷️ Domain: $domain\n\n";
            $markdown .= "**Progress**: $domainImplemented/$domainTotal ($domainPercentage%)\n\n";
            $markdown .= "| Action | Type | Status | API Endpoint | Assignee / PR |\n";
            $markdown .= "|--------|------|--------|--------------|---------------|\n";

            foreach ($endpoints as $endpoint) {
                $action = '`' . $endpoint['action'] . '`';
                $type = $endpoint['type'];
                $status = $endpoint['status'];
                $api = $endpoint['api'];

                // Build assignee/PR info
                $assigneeInfo = '';
                if (!empty($endpoint['assignee'])) {
                    $assigneeInfo = $endpoint['assignee'];
                    if ($endpoint['pr_info'] && !empty($endpoint['pr_info']['pr_url'])) {
                        $assigneeInfo = "[{$endpoint['assignee']}](https://github.com/{$endpoint['assignee']}) / [PR]({$endpoint['pr_info']['pr_url']})";
                    }
                }

                $markdown .= "| $action | $type | $status | $api | $assigneeInfo |\n";
            }

            $markdown .= "\n";
        }

        $markdown .= "## 📋 Status Legend\n\n";
        $markdown .= "- ✅ **Implemented**: API endpoint is available and working\n";
        $markdown .= "- 🚧 **In Progress**: Someone is actively working on this endpoint (PR open)\n";
        $markdown .= "- ❌ **Missing**: API endpoint needs to be implemented\n\n";
        $markdown .= '*Last updated: ' . date('Y-m-d H:i:s') . "*\n";

        file_put_contents($outputFile, $markdown);
    }

    private function fetchGitHubPRs(string $owner, string $repo, string $state, ?string $token, int $limit = 50): array
    {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/pulls?state={$state}&per_page={$limit}";

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
            throw new \RuntimeException('Failed to fetch PRs from GitHub API');
        }

        return json_decode($response, true) ?: [];
    }

    private function analyzePRChanges(array $pr, ?string $token): array
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
                return $endpoints;
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
            // Silently continue if we can't analyze PR changes
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

        return $endpoints;
    }
}
