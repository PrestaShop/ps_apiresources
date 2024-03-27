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

namespace PsApiResourcesTest;

use Composer\Script\Event;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use Symfony\Component\Filesystem\Filesystem;

class EnvironmentBuilder
{
    public static function setupLocalTests(Event $event): void
    {
        $arguments = self::parseArguments($event);
        static::log('Setup local environment: ' . var_export($arguments, true));
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $moduleDir = realpath($vendorDir . '/..');
        $prestashopDir = sys_get_temp_dir() . '/prestashop-api-resources';
        self::setupPrestashop($prestashopDir, $moduleDir, $arguments);
    }

    public static function clearCache(): void
    {
        $prestashopDir = sys_get_temp_dir() . '/prestashop-api-resources';
        self::clearTestCache($prestashopDir);
    }

    private static function setupPrestashop(string $prestashopDir, string $moduleDir, array $arguments): void
    {
        $fs = new Filesystem();
        if (is_dir($prestashopDir) && $arguments['force-clone']) {
            static::log('Removing prestashop folder to force clone ' . $prestashopDir);
            $fs->remove($prestashopDir);
        }

        // Init git repository or get reference to existing one
        $git = new Git();
        if (!is_dir($prestashopDir)) {
            static::log('Cloning PrestaShop folder to ' . $prestashopDir);
            $repo = $git->cloneRepository('https://github.com/PrestaShop/PrestaShop.git', $prestashopDir);
            $buildAssetsNeeded = true;
            $buildDbNeeded = true;
        } else {
            static::log('Found PrestaShop folder at ' . $prestashopDir);
            $repo = $git->open($prestashopDir);
            $buildAssetsNeeded = false;
            $buildDbNeeded = false;
        }

        // Checkout to appropriate branch, if branch as changed then we must rebuild assets and DB
        $branchChanged = self::checkoutBranch($repo, $arguments);
        if ($branchChanged) {
            $buildAssetsNeeded = true;
            $buildDbNeeded = true;
        }

        // Build assets (composer dependencies and UI assets)
        if ($buildAssetsNeeded || $arguments['build-assets']) {
            static::log('Install composer dependencies');
            passthru('cd ' . $prestashopDir . ' && composer install');

            static::log('Build assets');
            passthru('cd ' . $prestashopDir . ' && make assets');
            static::clearTestCache($prestashopDir);
        }

        // Link the current module folder into the PrestaShop test instance
        $prestashopModulePath = $prestashopDir . '/modules/ps_apiresources';
        if (!is_link($prestashopModulePath)) {
            static::log('Remove installed module from modules folder');
            $fs->remove($prestashopModulePath);
            static::log('Linking module into modules folder ' . $prestashopModulePath);
            symlink($moduleDir, $prestashopModulePath);
            static::clearTestCache($prestashopDir);
            $buildDbNeeded = true;
        }

        // Copy parameters
        $configFolder = $prestashopDir . '/app/config/';
        $localeParameters = $moduleDir . '/tests/local-parameters/';
        foreach (['parameters.php', 'parameters.yml'] as $parameterFile) {
            if (!file_exists($configFolder . $parameterFile) || $arguments['update-local-parameters']) {
                // Prefer using the local file if present, if not use the dist default configuration
                $localFile = $localeParameters . $parameterFile;
                if (!file_exists($localFile)) {
                    $localFile = $localeParameters . $parameterFile . '.dist';
                }
                $configFile = $configFolder . $parameterFile;

                static::log('Copy parameter file ' . $localFile . ' into config folder ' . $configFile);
                $fs->copy($localFile, $configFile);
                static::clearTestCache($prestashopDir);

                // DB settings may have changed so we rebuild the DB
                $buildDbNeeded = true;
            }
        }

        // Build test DB
        if ($buildDbNeeded || $arguments['build-db']) {
            static::log('Build test DB');
            passthru('cd ' . $prestashopDir . ' && composer run create-test-db');
        }
    }

    /**
     * @param GitRepository $repo
     * @param array $arguments
     *
     * @return bool returns if the branch was changed
     */
    private static function checkoutBranch(GitRepository $repo, array $arguments): bool
    {
        if (empty($arguments['core-branch'])) {
            self::log('No specified branch no need to checkout any particular one.');

            return false;
        }

        $matches = [];
        if (preg_match('/([^:]+)\:(.+)/', $arguments['core-branch'], $matches)) {
            $remoteName = $matches[1];
            $coreBranch = $matches[2];
        } else {
            $remoteName = null;
            $coreBranch = $arguments['core-branch'];
        }
        if (!empty($remoteName)) {
            $remoteBranch = $remoteName . '/' . $coreBranch;
        } else {
            $remoteBranch = 'origin/' . $coreBranch;
        }

        if ($repo->getCurrentBranchName() !== $coreBranch) {
            if (!empty($remoteName)) {
                $remoteUrl = 'git@github.com:' . $remoteName . '/PrestaShop.git';
                $remoteList = $repo->execute('remote');
                if (!in_array($remoteName, $remoteList)) {
                    self::log('Add new remote ' . $remoteUrl);
                    $repo->addRemote($remoteName, $remoteUrl);
                }

                self::log('Fetch remote ' . $remoteName);
                $repo->fetch($remoteName);
            } else {
                self::log('Fetch origin');
                $repo->fetch('origin');
            }

            if (in_array($coreBranch, $repo->getLocalBranches())) {
                self::log('Remove local branch ' . $coreBranch);
                $repo->removeBranch($coreBranch);
            }

            self::log('Create branch ' . $remoteBranch);
            $repo->execute('checkout', '-b', $coreBranch, $remoteBranch);

            // Change of branch requires rebuild
            return true;
        } else {
            self::log('Already on branch ' . $remoteBranch);

            return false;
        }
    }

    private static function clearTestCache(string $prestashopDir): void
    {
        $cacheFolder = $prestashopDir . '/var/cache/test';
        if (is_dir($cacheFolder)) {
            static::log('Remove cache folder ' . $cacheFolder);
            $fs = new Filesystem();
            $fs->remove($cacheFolder);
        }
    }

    private static function log(string $message): void
    {
        echo $message . PHP_EOL;
    }

    private static function parseArguments(Event $event): array
    {
        $arguments = [
            'force-clone' => false,
            'build-assets' => false,
            'build-db' => false,
            'update-local-parameters' => false,
            // We keep null by default meaning no change of branch will be done unless specified
            'core-branch' => null,
        ];

        $composerArguments = $event->getArguments();
        if (in_array('--force', $composerArguments)) {
            // Force all init boolean options to true
            $arguments = array_merge($arguments, [
                'force-clone' => true,
                'build-assets' => true,
                'build-db' => true,
                'update-local-parameters' => true,
            ]);
        } else {
            foreach ($composerArguments as $argument) {
                foreach ($arguments as $argumentKey => $argumentValue) {
                    if (is_bool($argumentValue) && '--' . $argumentKey === $argument) {
                        $arguments[$argumentKey] = true;
                    }
                }
            }
        }

        // Handle arguments with values
        foreach ($composerArguments as $argument) {
            $matches = [];
            if (preg_match('/\-\-(.+)\=(.+)/', $argument, $matches)) {
                $argumentKey = $matches[1];
                $argumentValue = $matches[2];
                if (array_key_exists($argumentKey, $arguments)) {
                    $arguments[$argumentKey] = $argumentValue;
                }
            }
        }

        return $arguments;
    }
}
