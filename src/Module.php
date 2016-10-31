<?php

namespace Autowp\Image;

class Module
{
    /**
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'service_manager'    => $provider->getDependencyConfig(),
            'view_helpers'       => $provider->getViewHelperConfig(),
            'controller_plugins' => $provider->getControllerPluginConfig()
        ];
    }
}
