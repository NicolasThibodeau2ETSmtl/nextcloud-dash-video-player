<?php
namespace OCA\Dashvideoplayer\Controller;

// Core
use OCP\AppFramework\Controller;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

// App
use OCA\Dashvideoplayer\AppConfig;

class SettingsController extends Controller
{
    private $config;
    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object     
     * @param OCA\Drawio\AppConfig $config - application configuration
     */
    public function __construct($AppName,
                                IRequest $request,                                
                                AppConfig $config
                                )
    {
        parent::__construct($AppName, $request);    
        $this->config = $config;
    }

    

    /**
     * Get supported formats
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function getsettings()
    {
         $data = array();
         $data['formats'] = $this->config->formats;
         $data['settings'] = array();         
         return $data;
    }

}
