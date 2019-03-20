<?php
/**
 * this file is part of ziggy
 *
 * @author Mr. Lewis <https://github.com/lewisvoncken>
 */

namespace Experius\Akeneo\Application;

use Experius\Util\ArrayFunctions;
use Experius\Util\BinaryString;
use Experius\Util\OperatingSystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * Config consists of several parts which are merged.
 * The configuration which is global (not Akeneo project specific) is loaded
 * during construction.
 *
 * As soon as the Akeneo folder is known, loadStageTwo should be called.
 *
 * The toArray method only works if the Akeneo folder specific configuration is already loaded.
 *
 * Class ConfigurationLoader
 *
 * @package Experius\Akeneo\Command
 */
class ConfigurationLoader
{
    /**
     * Config passed in the constructor
     *
     * @var array
     */
    protected $initialConfig;

    /**
     * @var array
     */
    protected $configArray = null;

    /**
     * Cache
     *
     * @var array
     */
    protected $distConfig;

    /**
     * Cache
     *
     * @var array
     */
    protected $pluginConfig;

    /**
     * Cache
     *
     * @var array
     */
    protected $systemConfig;

    /**
     * Cache
     *
     * @var array
     */
    protected $userConfig;

    /**
     * Cache
     *
     * @var array
     */
    protected $projectConfig;

    /**
     * @var string
     */
    protected $customConfigFilename = 'ziggy.yaml';

    /**
     * @var bool
     */
    protected $isPharMode = true;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Load config
     * If $akeneoRootFolder is null, only non-project config is loaded
     *
     * @param array $config
     * @param bool $isPharMode
     * @param OutputInterface $output
     */
    public function __construct(array $config, $isPharMode, OutputInterface $output)
    {
        $this->initialConfig = $config;
        $this->isPharMode = $isPharMode;
        $this->output = $output;
    }

    /**
     * @param bool $loadExternalConfig
     * @return array
     */
    public function getPartialConfig($loadExternalConfig = true)
    {
        $config = $this->initialConfig;
        $config = $this->loadDistConfig($config);
        if ($loadExternalConfig) {
            $config = $this->loadSystemConfig($config);
            $config = $this->loadUserConfig($config);
        }

        return $config;
    }

    /**
     * @param string $akeneoRootFolder
     * @param bool $loadExternalConfig
     * @param string $ziggyStopFileFolder
     */
    public function loadStageTwo($akeneoRootFolder, $loadExternalConfig = true, $ziggyStopFileFolder = '')
    {
        $config = $this->initialConfig;
        $config = $this->loadDistConfig($config);
        if ($loadExternalConfig) {
            $config = $this->loadPluginConfig($config, $akeneoRootFolder);
            $config = $this->loadSystemConfig($config);
            $config = $this->loadUserConfig($config, $akeneoRootFolder);
            $config = $this->loadProjectConfig($akeneoRootFolder, $ziggyStopFileFolder, $config);
        }
        $this->configArray = $config;
    }

    /**
     * @throws \ErrorException
     *
     * @return array
     */
    public function toArray()
    {
        if ($this->configArray == null) {
            throw new \ErrorException('Configuration not yet fully loaded');
        }

        return $this->configArray;
    }

    /**
     * @param array $initConfig
     *
     * @return array
     */
    protected function loadDistConfig(array $initConfig)
    {
        if ($this->distConfig == null) {
            $distConfigFilePath = __DIR__ . '/../../../../config.yaml';
            $this->distConfig = ConfigFile::createFromFile($distConfigFilePath)->toArray();
        }
        $this->logDebug('Load dist config');

        $config = ArrayFunctions::mergeArrays($this->distConfig, $initConfig);

        return $config;
    }

    /**
     * Check if there is a global config file in /etc folder
     *
     * @param array $config
     *
     * @return array
     */
    public function loadSystemConfig(array $config)
    {
        if ($this->systemConfig == null) {
            if (OperatingSystem::isWindows()) {
                $systemWideConfigFile = getenv('WINDIR') . '/' . $this->customConfigFilename;
            } else {
                $systemWideConfigFile = '/etc/' . $this->customConfigFilename;
            }

            if ($systemWideConfigFile && file_exists($systemWideConfigFile)) {
                $this->logDebug('Load system config <comment>' . $systemWideConfigFile . '</comment>');
                $this->systemConfig = Yaml::parse($systemWideConfigFile);
            } else {
                $this->systemConfig = [];
            }
        }

        $config = ArrayFunctions::mergeArrays($config, $this->systemConfig);

        return $config;
    }

    /**
     * Load config from all installed bundles
     *
     * @param array $config
     * @param string $akeneoRootFolder
     *
     * @return array
     */
    public function loadPluginConfig(array $config, $akeneoRootFolder)
    {
        if ($this->pluginConfig == null) {
            $this->pluginConfig = [];
            $moduleBaseFolders = [];
            $customFilename = $this->customConfigFilename;
            $customName = pathinfo($customFilename, PATHINFO_FILENAME);
            if (OperatingSystem::isWindows()) {
                $config['plugin']['folders'][] = getenv('WINDIR') . '/' . $customName . '/modules';
                $config['plugin']['folders'][] = OperatingSystem::getHomeDir() . '/' . $customName . '/modules';
            } else {
                $config['plugin']['folders'][] = OperatingSystem::getHomeDir() . '/.' . $customName . '/modules';
            }
            $config['plugin']['folders'][] = $akeneoRootFolder . '/lib/' . $customName . '/modules';
            foreach ($config['plugin']['folders'] as $folder) {
                if (is_dir($folder)) {
                    $moduleBaseFolders[] = $folder;
                }
            }

            /**
             * Allow modules to be placed vendor folder if not in phar mode
             */
            if (!$this->isPharMode && is_dir($this->getVendorDir())) {
                $finder = Finder::create();
                $finder
                    ->files()
                    ->depth(2)
                    ->followLinks()
                    ->ignoreUnreadableDirs(true)
                    ->name($customFilename)
                    ->in($this->getVendorDir());

                foreach ($finder as $file) {
                    /* @var $file SplFileInfo */
                    $this->registerPluginConfigFile($akeneoRootFolder, $file);
                }
            }

            if (count($moduleBaseFolders) > 0) {
                // Glob plugin folders
                $finder = Finder::create();
                $finder
                    ->files()
                    ->depth(1)
                    ->followLinks()
                    ->ignoreUnreadableDirs(true)
                    ->name($customFilename)
                    ->in($moduleBaseFolders);

                foreach ($finder as $file) {
                    /* @var $file SplFileInfo */
                    $this->registerPluginConfigFile($akeneoRootFolder, $file);
                }
            }
        }

        $config = ArrayFunctions::mergeArrays($config, $this->pluginConfig);

        return $config;
    }

    /**
     * @param string $rawConfig
     * @param string $akeneoRootFolder
     * @param SplFileInfo|null $file [optional]
     *
     * @return string
     */
    protected function applyVariables($rawConfig, $akeneoRootFolder, SplFileInfo $file = null)
    {
        $replace = array(
            '%module%' => $file ? $file->getPath() : '',
            '%root%'   => $akeneoRootFolder,
        );

        return str_replace(array_keys($replace), $replace, $rawConfig);
    }

    /**
     * Check if there is a user config file. ~/.ziggy.yaml
     *
     * @param array $config
     * @param string $akeneoRootFolder [optional]
     *
     * @return array
     */
    public function loadUserConfig(array $config, $akeneoRootFolder = null)
    {
        if (null === $this->userConfig) {
            $this->userConfig =[];
            $locator = new ConfigLocator($this->customConfigFilename, $akeneoRootFolder);
            if ($userConfigFile = $locator->getUserConfigFile()) {
                $this->userConfig = $userConfigFile->toArray();
            }
        }

        $config = ArrayFunctions::mergeArrays($config, $this->userConfig);

        return $config;
    }

    /**
     * AKENEO_ROOT/app/etc/ziggy.yaml
     *
     * @param string $akeneoRootFolder
     * @param string $ziggyStopFileFolder
     * @param array $config
     *
     * @return array
     */
    public function loadProjectConfig($akeneoRootFolder, $ziggyStopFileFolder, array $config)
    {
        if (null !== $this->projectConfig) {
            return ArrayFunctions::mergeArrays($config, $this->projectConfig);
        }

        $this->projectConfig =[];

        $locator = new ConfigLocator($this->customConfigFilename, $akeneoRootFolder);

        if ($projectConfigFile = $locator->getProjectConfigFile()) {
            $this->projectConfig = $projectConfigFile->toArray();
        }

        if ($stopFileConfigFile = $locator->getStopFileConfigFile($ziggyStopFileFolder)) {
            $this->projectConfig = $stopFileConfigFile->mergeArray($this->projectConfig);
        }

        return ArrayFunctions::mergeArrays($config, $this->projectConfig);
    }

    /**
     * Loads a plugin config file and merges it to plugin config
     *
     * @param string $akeneoRootFolder
     * @param SplFileInfo $file
     */
    protected function registerPluginConfigFile($akeneoRootFolder, $file)
    {
        if (BinaryString::startsWith($file->getPathname(), 'vfs://')) {
            $path = $file->getPathname();
        } else {
            $path = $file->getRealPath();

            if ($path === '') {
                throw new \UnexpectedValueException(sprintf("Realpath for '%s' did return an empty string.", $file));
            }

            if ($path === false) {
                $this->log(sprintf("<error>Plugin config file broken link '%s'</error>", $file));

                return;
            }
        }

        $this->logDebug('Load plugin config <comment>' . $path . '</comment>');
        $localPluginConfigFile = ConfigFile::createFromFile($path);
        $localPluginConfigFile->applyVariables($akeneoRootFolder, $file);
        $this->pluginConfig = $localPluginConfigFile->mergeArray($this->pluginConfig);
    }

    /**
     * @return string
     */
    public function getVendorDir()
    {
        /* old vendor folder to give backward compatibility */
        $vendorFolder = $this->getConfigurationLoaderDir() . '/../../../../vendor';
        if (is_dir($vendorFolder)) {
            return $vendorFolder;
        }

        /* correct vendor folder for composer installations */
        $vendorFolder = $this->getConfigurationLoaderDir() . '/../../../../../../../vendor';
        if (is_dir($vendorFolder)) {
            return $vendorFolder;
        }

        return '';
    }

    /**
     * @return string
     */
    public function getConfigurationLoaderDir()
    {
        return __DIR__;
    }

    /**
     * @param string $message
     */
    private function logDebug($message)
    {
        if (OutputInterface::VERBOSITY_DEBUG <= $this->output->getVerbosity()) {
            $this->log('<debug>' . $message . '</debug>');
        }
    }

    /**
     * @param string $message
     */
    private function log($message)
    {
        $this->output->writeln($message);
    }
}