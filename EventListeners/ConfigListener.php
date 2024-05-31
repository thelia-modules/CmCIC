<?php

namespace CmCIC\EventListeners;

use CmCIC\CmCIC;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Thelia\Model\ModuleConfigQuery;

class ConfigListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'module.config' => ['onModuleConfigure', 128],
        ];
}

    public function onModuleConfigure(GenericEvent $event)
    {
        $subject = $event->getSubject();

        if ($subject !== "HealthStatus") {
            throw new \RuntimeException('Event subject does not match expected value');
        }

        $configModule = ModuleConfigQuery::create()
            ->filterByModuleId(CmCIC::getModuleId())
            ->find();

        $moduleConfig = [];
        $path = __DIR__ . "/../" . CmCIC::JSON_CONFIG_PATH;
        $moduleConfig = ['json_path' => is_readable($path) ? 'exists' : null];
        $configsCompleted = true;
        if ($configModule->count() === 0) {
            $configsCompleted = false;
        }
        $moduleConfig['module'] = CmCIC::getModuleCode();

        foreach ($configModule as $config) {
            $moduleConfig[$config->getName()] = $config->getValue();
            if ($config->getValue() === null) {
                $configsCompleted = false;
            }
        }


        $moduleConfig['completed'] = $configsCompleted;

        $event->setArgument('cmcic.module.config', $moduleConfig);
    }
}