<?php
/**
 * @package     PHP-Plesk-Backup
 * @copyright   2017 Serena Villa. All rights reserved.
 * @license     GNU GPL version 3; see LICENSE
 * @link        http://www.josie.it
 */

$config = [

  "hostname" => "", //hostname of the machine where we are performing the backup

  //ftp settings of the remote server where we want to store the backup
  "ftp_host" => "",
  "ftp_username" => "",
  "ftp_pass" => "",
  "ftp_passive_mode" => true,

  //mysql settings of the machine where we want to perform the backup
  "mysql_host" => "localhost",
  "mysql_user" => "admin",
  "mysql_pass" => "",
  "mysql_port" => "8306",

  "basedir" => "/backup", //base directory to be used for backup on remote ftp
  "date_format" => date("m_d_Y"), //to be used in file names
  "max_file_life" => 40, //max lifetime for old backup files before they are deleted (in days)
  "max_waiting_time" => 300, // max waiting time before proceeding with the next task leaving the old one going on in background (in seconds)

  //the following settings are used only with backup_scheduler.php
  "scheduler_alert_email" => "0", //email alert in case of errors in the scheduled task
  "scheduler_day" => "7", //day of the week for the weekly backup task (7 is Sunday)
  "scheduler_time" => "00:00:00", //time for the weekly backup task

];
