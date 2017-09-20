<?php

/**
 * @package     PHP-Plesk-Backup
 * @copyright   2017 Serena Villa. All rights reserved.
 * @license     GNU GPL version 3; see LICENSE
 * @link        http://www.josie.it
 */

set_time_limit(0);

//date_default_timezone_set('Europe/Paris'); //turn off that warning :-)

require 'config.php';
require 'BackupHelper.php';

$backupHelper = new BackupHelper($config);

//doesn't perform backup now, just schedules a weekly backup for all the domains
$backupHelper->scheduleBackups();

?>
