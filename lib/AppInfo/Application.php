<?php

namespace OCA\Dashvideoplayer\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\IContainer;
use OCP\IConfig;
use OCP\ILogger;
use OCA\Dashvideoplayer\AppConfig;
use OCA\Dashvideoplayer\Controller\PlayerController;
use OCA\Dashvideoplayer\Controller\ViewerController;
use OCA\Dashvideoplayer\Controller\SettingsController;

class Application extends App implements IBootstrap {
    public function __construct(array $urlParams = []) {
        parent::__construct('dashvideoplayer', $urlParams);

        $container = $this->getContainer();

        // Register services
        $container->registerService('RootStorage', fn(IContainer $c) => $c->getServer()->getRootFolder());
        $container->registerService('UserSession', fn(IContainer $c) => $c->getServer()->getUserSession());
        $container->registerService('Logger', fn(IContainer $c) => $c->getServer()->getLogger());
        $container->registerService('Config', fn(IContainer $c) => $c->getServer()->getConfig());
        $container->registerService('AppConfig', fn(IContainer $c) => new AppConfig(
            'dashvideoplayer',
            $c->get(IConfig::class),
            $c->get(ILogger::class)
        ));

        // Register controllers
        $container->registerService('PlayerController', fn(IContainer $c) => new PlayerController(
            $c->get('AppName'),
            $c->get('Request'),
            $c->get('RootStorage'),
            $c->get('UserSession'),
            $c->getServer()->getURLGenerator(),
            $c->get('Logger'),
            $c->get('AppConfig'),
            $c->get('IManager'),
            $c->get('Session')
        ));

        $container->registerService('ViewerController', fn(IContainer $c) => new ViewerController(
            $c->get('AppName'),
            $c->get('Request'),
            $c->get('RootStorage'),
            $c->get('UserSession'),
            $c->getServer()->getURLGenerator(),
            $c->get('Logger'),
            $c->get('AppConfig'),
            $c->get('IManager'),
            $c->get('Session'),
            $c->get('UserSession')->getUser()?->getUID()
        ));

        $container->registerService('SettingsController', fn(IContainer $c) => new SettingsController(
            $c->get('AppName'),
            $c->get('Request'),
            $c->get('AppConfig')
        ));

        // Add resources
        \OCP\Util::addScript('dashvideoplayer', 'main');
        \OCP\Util::addStyle('dashvideoplayer', 'main');
    }

    /**
     * Register file viewers for MPD and M3U8
     */
    public function register(IRegistrationContext $context): void {
        $context->registerFileViewer(
            'application/dash+xml',
            fn() => new \OCA\Dashvideoplayer\Viewer\MpdViewer()
        );

        $context->registerFileViewer(
            'application/vnd.apple.mpegurl',
            fn() => new \OCA\Dashvideoplayer\Viewer\M3u8Viewer()
        );
    }

    public function boot(IBootContext $context): void {
        // Nothing special for now
    }
}