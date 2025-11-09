<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Dashvideoplayer\AppInfo\ApplicationOld::APP_ID, OCA\Dashvideoplayer\AppInfo\ApplicationOld::APP_ID . '-main');
Util::addStyle(OCA\Dashvideoplayer\AppInfo\ApplicationOld::APP_ID, OCA\Dashvideoplayer\AppInfo\ApplicationOld::APP_ID . '-main');

?>

<div id="dashvideoplayer"></div>
