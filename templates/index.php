<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Dashvideoplayer\AppInfo\Application::APP_ID, OCA\Dashvideoplayer\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Dashvideoplayer\AppInfo\Application::APP_ID, OCA\Dashvideoplayer\AppInfo\Application::APP_ID . '-main');

?>

<div id="dashvideoplayer"></div>
