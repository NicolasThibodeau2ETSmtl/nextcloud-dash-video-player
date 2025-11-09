<?php

namespace OCA\Dashvideoplayer\AppInfo;

use OCP\AppFramework\App;
use OCP\Util;

use OCA\Dashvideoplayer\AppConfig;
use OCA\Dashvideoplayer\Controller\PlayerController;
use OCA\Dashvideoplayer\Controller\ViewerController;

class Application extends App
{

    public $appConfig;

    public function __construct(array $urlParams = [])
    {
        $appName = "dashvideoplayer";

        parent::__construct($appName, $urlParams);

        $this->appConfig = new AppConfig($appName);

        Util::addScript($appName, "main");
        Util::addStyle($appName, "main");


        $container = $this->getContainer();

        $container->registerService("RootStorage", function ($c) {
            return $c->query("ServerContainer")->getRootFolder();
        });

        $container->registerService("UserSession", function ($c) {
            return $c->query("ServerContainer")->getUserSession();
        });

        $container->registerService("Logger", function ($c) {
            return $c->query("ServerContainer")->getLogger();
        });

        $container->registerService("PlayerController", function ($c) {
            return new PlayerController(
                $c->query("AppName"),
                $c->query("Request"),
                $c->query("RootStorage"),
                $c->query("UserSession"),
                $c->query("ServerContainer")->getURLGenerator(),
                $c->query("Logger"),
                $this->appConfig,
                $c->query("IManager"),
                $c->query("Session")
            );
        });

        $container->registerService("ViewerController", function ($c) {
            return new ViewerController(
                $c->query("AppName"),
                $c->query("Request"),
                $c->query("RootStorage"),
                $c->query("UserSession"),
                $c->query("ServerContainer")->getURLGenerator(),
                $c->query("Logger"),
                $this->appConfig,
                $c->query("IManager"),
                $c->query("Session")
            );
        });
        
    }
}
