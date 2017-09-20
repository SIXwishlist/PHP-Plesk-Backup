<?php

/**
 * @package     PHP-Plesk-Backup
 * @copyright   2017 Serena Villa. All rights reserved.
 * @license     GNU GPL version 3; see LICENSE
 * @link        http://www.josie.it
 */

set_time_limit(0);

require 'config.php';
require 'BackupHelper.php';

$backupHelper = new BackupHelper($config);
$backupHelper->doBackup();

?>
