<?php

namespace OCA\Dashvideoplayer\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCP\AutoloadNotAllowedException;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

use OC\Files\Filesystem;
use OC\Files\View;
use OC\User\NoUserException;

use OCA\Files\Helper;
use OCA\Files_Versions\Storage;
use OCA\Viewer\Event\LoadViewer;

use OCA\Dashvideoplayer\AppConfig;


class ViewerController extends Controller
{

    private $userSession;
    private $root;
    private $urlGenerator;
    private $logger;
    private $config;
    private $userId;
    /**
     * Session
     *
     * @var ISession
     */
    private $session;
    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;


    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IURLGenerator $urlGenerator - url generator service     
     * @param ILogger $logger - logger
     * @param OCA\Dashvideoplayer\AppConfig $config - app config
     */
    public function __construct(
        $AppName,
        IRequest $request,
        IRootFolder $root,
        IUserSession $userSession,
        IURLGenerator $urlGenerator,
        ILogger $logger,
        AppConfig $config,
        IManager $shareManager,
        ISession $session,
        $UserId
    ) {
        parent::__construct($AppName, $request);

        $this->userSession = $userSession;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->config = $config;
        $this->shareManager = $shareManager;
        $this->session = $session;
        $this->userId = $UserId;
    }


    /**
     * This comment is very important, CSRF fails without it
     *
     * @param integer $fileId - file identifier
     *
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index($fileId, $shareToken = NULL, $filePath = NULL)
    {
        //$this->logger->warning("Open: $fileId $shareToken $filePath", array("app" => $this->appName));
        if (empty($shareToken) && !$this->userSession->isLoggedIn()) {
            $redirectUrl = $this->urlGenerator->linkToRoute("core.login.showLoginForm", [
                "redirect_url" => $this->request->getRequestUri()
            ]);
            return new RedirectResponse($redirectUrl);
        }


        if ($fileId) {
            list(
                $file, $error
            ) = $this->getFile($fileId);
            if (isset($error)) {
                $this->logger->error("Load: " . $fileId . " " . $error, array("app" => $this->appName));
                return ["error" => $error];
            }
            $uid = $this->userSession->getUser()->getUID();
            $baseFolder = $this->root->getUserFolder($uid);
            $relativePath = $baseFolder->getRelativePath($file->getPath());
        } else {
            $this->logger->warning("DASH: ENTRE AL ELSE DEL INDEX", array("app" => $this->appName));
            list($file, $error, $share) = $this->getFileByToken($fileId, $shareToken);
            $relativePath = $file->getPath();
           
            if (isset($error)) {
                $this->logger->error("Load with token: " . $shareToken . " " . $error, array("app" => $this->appName));
                return ["error" => $error];
            }            
        }

        /*
        Temp hack:
        Remove "admin/files" from path provided by nextcloud
        */
        $relativePath = str_replace("/admin/files", "", $relativePath);


        /* 
        Generate video's web url for the player to use as 'src' attr
        I've tried with the following urls
        http://localhost:8888/nextcloud/remote.php/webdav/directory/somevideofile.mpd --> works for authenticated users
        http://localhost:8888/nextcloud/public.php/webdav/directory/somevideofile.mpd --> works for authenticated users 
        http://localhost:8888/nextcloud/index.php/s/BNz8foit5GxEMd8/download


        */

        $baseUri = \OC::$WEBROOT . '/index.php/s/'. $shareToken.'/download';
        //$baseUri = \OC::$WEBROOT . '/public.php/webdav';
        //$videoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$baseUri$relativePath";
        $videoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$baseUri";

        if (!function_exists('str_contains')) {
            function str_contains(string $haystack, string $needle)
            {
                return empty($needle) || strpos($haystack, $needle) !== false;
            }
        }

        $coverUrl = "";
        if (str_contains($videoUrl, 'mpd'))
            $coverUrl = str_replace("mpd", "jpeg", $videoUrl);
        if (str_contains($videoUrl, 'm3u'))
            $coverUrl = str_replace("m3u", "jpeg", $videoUrl);

        $params = [
            "fileId" => $fileId,
            "filePath" => $filePath,
            "videoUrl" => $videoUrl,
            "coverUrl" => $coverUrl,
            "extra" => $relativePath,
            "shareToken" => $shareToken,
        ];

        if ($this->userId) {
            $response = new TemplateResponse($this->appName, "viewer", $params);
        } else {
            $this->logger->error("DASH PublicTemplateResponse", array("app" => $this->appName));
            $response = new PublicTemplateResponse($this->appName, "viewer", $params);
        }


        $csp = new ContentSecurityPolicy();
        $csp->allowInlineScript(true);
        $csp->addAllowedScriptDomain('*');
        $csp->addAllowedFrameDomain('*');
        $csp->addAllowedFrameDomain("blob:");
        $csp->addAllowedConnectDomain('*');
        $csp->addAllowedImageDomain('*');
        $csp->addAllowedMediaDomain('*');
        $csp->addAllowedChildSrcDomain('*');
        $csp->addAllowedChildSrcDomain("blob:");
        $response->setContentSecurityPolicy($csp);
        
        return $response;
    }

    /**
     * Print public player section
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function PublicPage($fileId, $shareToken)
    {
        return $this->index($fileId, $shareToken);
    }

    /**
     * Collecting the file parameters for the Dashvideoplayer application
     *
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param string $shareToken - access token
     *
     * @return DataDownloadResponse
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function PublicFile($fileId, $filePath = NULL, $shareToken = NULL)
    {
        if (empty($shareToken)) {
            return ["error" => ("Not permitted")];
        }

        $user = $this->userSession->getUser();
        $userId = NULL;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list($file, $error, $share) = $this->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Config: $fileId $error", array("app" => $this->appName));
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = $this->config->formats[$ext];

        if (!isset($format)) {
            $this->logger->info("Format is not supported for editing: $fileName", array("app" => $this->appName));
            return ["error" => ("Format is not supported")];
        }

        $fileUrl = ""; //$this->getUrl($file, $shareToken);

        $params = [
            "url" => $fileUrl,
            "file" => ""
        ];

        try {
            return new DataDownloadResponse($file->getContent(), $file->getName(), $file->getMimeType());
        } catch (NotPermittedException  $e) {
            $this->logger->error("Download Not permitted: $fileId " . $e->getMessage(), array("app" => $this->appName));
            //$params["error"] = new JSONResponse(["message" => ("Not permitted")], Http::STATUS_FORBIDDEN);
            return new JSONResponse(["message" => ("Not permitted")], Http::STATUS_FORBIDDEN);
        }
        return new JSONResponse(["message" => ("Download failed")], Http::STATUS_INTERNAL_SERVER_ERROR);

        //return $params;
    }

    /**
     * @NoAdminRequired
     */
    private function getFile($fileId)
    {
        if (empty($fileId)) {
            return [null, ("FileId is empty")];
        }

        $files = $this->root->getById($fileId);
        if (empty($files)) {
            return [null, ("File not found")];
        }
        $file = $files[0];

        if (!$file->isReadable()) {
            return [null, ("You do not have enough permissions to view the file")];
        }
        return [$file, null];
    }

    /**
     * Getting file by token
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getFileByToken($fileId, $shareToken)
    {
        $this->logger->error("@@ DASH getFileByToken. fi: $fileId / st: $shareToken ", array("app" => $this->appName));
        list($node, $error, $share) = $this->getNodeByToken($shareToken);

        if (isset($error)) {
            return [NULL, $error, NULL];
        }    

        if ($node instanceof Folder) {
            try {
                $files = $node->getById($fileId);
            } catch (\Exception $e) {
                $this->logger->error("@@ DASH getFileByToken: $fileId " . $e->getMessage(), array("app" => $this->appName));
                return [NULL, ("Invalid request"), NULL];
            }

            if (empty($files)) {
                $this->logger->info("Files not found: $fileId", array("app" => $this->appName));
                return [NULL, ("File not found"), NULL];
            }
            $file = $files[0];
            $this->logger->info("getFileByToken. instanceof if", array("app" => $this->appName));
        } else {
            $file = $node;
            $this->logger->info("getFileByToken. instanceof else", array("app" => $this->appName));
        }

        return [$file, NULL, $share];
    }

    /**
     * Getting file by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getNodeByToken($shareToken)
    {
        $this->logger->error("@@ DASH getNodeByToken. st: $shareToken ", array("app" => $this->appName));
        list($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            $this->logger->error("@@DASH getNodeByToken. isset error ", array("app" => $this->appName));
            return [NULL, $error, NULL];
        }

        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            $this->logger->error("@@DASH getNodeByToken. getPermissions error ", array("app" => $this->appName));
            return [NULL, ("You do not have enough permissions to view the file"), NULL];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->error("@@DASH getNodeByToken. NotFoundException error ", array("app" => $this->appName));
            return [NULL, ("File not found"), NULL];
        }     
        return [$node, NULL, $share];
    }

    /**
     * Getting share by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getShare($shareToken)
    {
        $this->logger->error("@@DASH getShare. st: $shareToken ", array("app" => $this->appName));
        if (empty($shareToken)) {
            return [NULL, ("FileId is empty")];
        }

        $share;
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound $e) {
            $this->logger->error("@@DASH getShare error: " . $e->getMessage(), array("app" => $this->appName));
            $share = NULL;
        }

        if ($share === NULL || $share === false) {
            $this->logger->error("@@DASH getShare noshare ", array("app" => $this->appName));
            return [NULL, ("You do not have enough permissions to view the file")];
        }

        if (
            $share->getPassword()
            && (!$this->session->exists("public_link_authenticated")
                || $this->session->get("public_link_authenticated") !== (string) $share->getId())
        ) {
            $this->logger->error("@@DASH getShare: " . "You do not have enough permissions to view the file", array("app" => $this->appName));
            return [NULL, ("You do not have enough permissions to view the file")];
        }
        $this->logger->error("@@DASH getShare was OK ", array("app" => $this->appName));

        return [$share, NULL];
    }
}
