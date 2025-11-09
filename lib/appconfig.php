<?php

namespace OCA\Dashvideoplayer;

class AppConfig
{
    private $appName;

    public function __construct($AppName)
    {
        $this->appName = $AppName;

        $this->config = \OC::$server->getConfig();
        $this->logger = \OC::$server->getLogger();
    }


    public function GetAppName()
    {
        return $this->appName;
    }

    /**
     * Additional data about formats
     *
     * @var array
     */
    public $formats = [
        "mpd" => ["mime" => "application/mpd", "type" => "video"],
        "m3u8" => ["mime" => "application/m3u8", "type" => "video"]
    ];
}
