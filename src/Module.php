<?php

namespace Autowp\Image;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;

class Module implements
    ConsoleUsageProviderInterface,
    ConsoleBannerProviderInterface,
    ConfigProviderInterface
{
    /**
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'console'            => $provider->getConsoleConfig(),
            'controller_plugins' => $provider->getControllerPluginConfig(),
            'controllers'        => $provider->getControllersConfig(),
            'service_manager'    => $provider->getDependencyConfig(),
            'view_helpers'       => $provider->getViewHelperConfig(),
        ];
    }

    /**
     * @param Console $console
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleBanner(Console $console)
    {
        return 'Autowp\Image Module';
    }

    /**
     * @param Console $console
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleUsage(Console $console)
    {
        //description command
        return [
            'image-storage list-broken-files'             => 'List broken files',
            'image-storage fix-broken-files'              => 'Try to fix broken files',
            'image-storage flush-format <format>'         => 'Flush formated images by format',
            'image-storage flush-image <image>'           => 'Flush formated images by image id',
            'image-storage delete-broken-files <dirname>' => 'Delete broken files',
            'image-storage clear-empty-dirs <dirname>'    => 'Clear empty directories',
        ];
    }
}
