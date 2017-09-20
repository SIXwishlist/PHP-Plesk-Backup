<?php
/**
 * @package     PHP-Plesk-Backup
 * @copyright   2017 Serena Villa. All rights reserved.
 * @license     GNU GPL version 3; see LICENSE
 * @link        http://www.josie.it
 */

require_once('PleskApiClient.php');

/**
 * Backup Helper to backup all domains in Plesk
 */
class BackupHelper
{
    private $_ftp_host;
    private $_ftp_username;
    private $_ftp_pass;
    private $_ftp_passive_mode;

    private $_hostname;

    private $_mysql_host;
    private $_mysql_user;
    private $_mysql_pass;
    private $_mysql_port;

    private $_date_format;
    private $_max_file_life;
    private $_basedir;
    private $_max_waiting_time;
    private $_sleep_time = 10;

    private $_scheduler_alert_email;
    private $_scheduler_day;
    private $_scheduler_time;

    private $_link;

    private $_client;

    public function __construct($config)
    {

        if(!isset($config['hostname']) || !isset($config['ftp_host']) || !isset($config['ftp_username']) || !isset($config['ftp_pass']) || !isset($config['mysql_pass'])) {
          throw new Exception('Missing settings! Check your config.php file.');
        }

        $this->_ftp_host = $config['ftp_host'];
        $this->_ftp_username = $config['ftp_username'];
        $this->_ftp_pass = $config['ftp_pass'];
        $this->_ftp_passive_mode = (isset($config['ftp_passive_mode'])) ? $config['ftp_passive_mode'] : 'true';

        $this->_mysql_host = (isset($config['mysql_host'])) ? $config['mysql_host'] : "localhost";
        $this->_mysql_user = (isset($config['mysql_user'])) ? $config['mysql_user'] : "admin";
        $this->_mysql_pass = $config['mysql_pass'];
        $this->_mysql_port = (isset($config['mysql_port'])) ? $config['mysql_port'] : "8306";

        $this->_hostname = $config['hostname'];

        $this->_basedir = (isset($config['basedir'])) ? $config['basedir'] : "/backup";
        $this->_date_format = (isset($config['date_format'])) ? $config['date_format'] : "m_d_Y";
        $this->_max_file_life = (isset($config['max_file_life'])) ? $config['max_file_life'] : 40;
        $this->_max_waiting_time = (isset($config['max_waiting_time'])) ? $config['max_waiting_time'] : 300;

        $this->_scheduler_alert_email = (isset($config['scheduler_alert_email'])) ? $config['scheduler_alert_email'] : '0';
        $this->_scheduler_day = (isset($config['scheduler_day'])) ? $config['scheduler_day'] : '7';
        $this->_scheduler_time = (isset($config['scheduler_time'])) ? $config['scheduler_time'] : '00:00:00';

        try {
          $this->_client = new PleskApiClient($this->_hostname);
          $this->_client->setCredentials($this->_mysql_user, $this->_mysql_pass);
        }
        catch (Exception $e) {
          die($e->getMessage());
        }

    }

    public function scheduleBackups(){

      $this->serverSettings();

      $this->_link = mysqli_connect($this->_mysql_host.":".$this->_mysql_port, $this->_mysql_user, $this->_mysql_pass) or die("can't connect to mysql server");
      mysqli_select_db($this->_link, "psa") or die("can't select mysql db");

      $query = mysqli_query($this->_link,"SELECT dom.id, dom.name FROM domains dom");
      while ($row=mysqli_fetch_array($query)){
        $dir = $this->_basedir.'/'.$row['name'].'/';
        $this->deleteOldFiles($dir);
        $this->scheduleDomainBackup($row['name'],$row['id'],$dir);
      }

      mysqli_close($this->_link);

    }

    public function doBackup(){

      $this->serverSettings();

      $this->_link = mysqli_connect($this->_mysql_host.":".$this->_mysql_port, $this->_mysql_user, $this->_mysql_pass) or die("can't connect to mysql server");
      mysqli_select_db($this->_link, "psa") or die("can't select mysql db");

      $query = mysqli_query($this->_link,"SELECT dom.id, dom.name FROM domains dom");
      while ($row=mysqli_fetch_array($query)){
        $dir = $this->_basedir.'/'.$row['name'].'/';
        $this->deleteOldFiles($dir);
        $this->backupDomain($row['name'],$row['id'],$dir);
      }

      mysqli_close($this->_link);
    }

    private function deleteOldFiles($dir){

      $connection = ftp_connect($this->_ftp_host) or die("can't connect to ftp");
      ftp_login($connection,$this->_ftp_username,$this->_ftp_pass) or die("can't login to ftp");
      ftp_pasv($connection,TRUE);

      $this->ftpMkSubdir($connection,'/',$dir);

      $dateToCompare = date('Y-m-d',  strtotime('-'.$this->_max_file_life.' days',time()));
      $files = ftp_nlist($connection,$dir);

      foreach($files as $file){
           $modTime = ftp_mdtm($connection, $file);
           if(strtotime($dateToCompare) >= $modTime){
               echo "Deleting ".$file." ...\n";
               ftp_delete($connection,$file);
           }
      }

      ftp_close($connection);

    }

    private function getTaskStatus($task_id){
      	$request='<packet>
      <backup-manager>
         <get-tasks-info>
            <task-id>'.$task_id.'</task-id>
         </get-tasks-info>
      </backup-manager>
      </packet>';
      $response = $this->_client->request($request);
      return $this->valueIn('status',$this->valueIn('task', $response));
    }

    private function valueIn($element_name, $xml, $content_only = true) {
        if ($xml == false) {
            return false;
        }
        $found = preg_match('#<'.$element_name.'(?:\s+[^>]+)?>(.*?)'.
                '</'.$element_name.'>#s', $xml, $matches);
        if ($found != false) {
            if ($content_only) {
                return $matches[1];  //ignore the enclosing tags
            } else {
                return $matches[0];  //return the full pattern match
            }
        }
        // No match found: return false.
        return false;
    }

    private function scheduleDomainBackup($dom,$id_dom,$dir){

      echo "Scheduling backup for ".$dom." ...\n";

      $this->domainSettings($dom,$id_dom,$dir);

      $query = mysqli_query($this->_link,"SELECT COUNT(*) as count FROM backupsscheduled WHERE obj_id=".$id_dom." AND obj_type='domain'");
      while ($row=mysqli_fetch_array($query)) { $count = $row['count']; }
      $query_string = "INSERT INTO backupsscheduled (`obj_id`,`obj_type`,`repository`,`last,period`,`active`,`processed`,`rotation`,`prefix`,`email`,`split_size`,`suspend`,`with_content`,`backup_day`,`backup_time`,`content_type`,`full_backup_period`,`mssql_native_backup`,`backupExcludeFilesId`,`backupExcludeLogs`) VALUES
      (".$id_dom.",'domain','ftp','".date('Y-m-d H:i:s')."', '604800', 'true', 'false', 5, '','".$this->_scheduler_alert_email."', 0, 'false', 'true', '".$this->_scheduler_day."', '".$this->_scheduler_time."', 'backup_content_all_at_domain', 0, 1, 2, 1)";
      if ($count > 0 ){
        //update only the settings that are configurable, the other ones should be already ok!
            mysqli_query($this->_link,"UPDATE backupsscheduled SET
              `backup_day` = '".$this->_scheduler_day."',
              `backup_time` = '".$this->_scheduler_time."',
              `email` = '".$this->_scheduler_alert_email."'
            WHERE obj_type ='domain' AND obj_id =".$id_dom);
      } else {
            mysqli_query($this->_link,$query_string);
      }

    }

    private function domainSettings($dom,$id_dom,$dir){
      $request_storage = '<packet>
      <backup-manager>
         <set-remote-storage-settings>
              <webspace-name>'.$dom.'</webspace-name>
              <settings>
                      <protocol>ftp</protocol>
                      <host>'.$this->_ftp_host.'</host>
                      <port>22</port>
                      <directory>'.$dir.'</directory>
                      <login>'.$this->_ftp_username.'</login>
                      <password>'.$this->ftp_pass.'</password>
                      <passive-mode>'.$this->_ftp_passive_mode.'</passive-mode>
              </settings>
         </set-remote-storage-settings>
      </backup-manager>
      </packet>';

      $this->_client->request($request_storage);

      mysqli_query($this->_link,"UPDATE backupssettings SET value='true' where param='backup_ftp_settingactive' WHERE id = ".$id_dom);
      mysqli_query($this->_link,"UPDATE backupssettings SET value='true' where param='backup_ftp_settinguse_ftps' WHERE id = ".$id_dom);
      mysqli_query($this->_link,"UPDATE backupssettings SET value='".$this->_ftp_passive_mode."' where param='backup_ftp_settingpassive_mode' WHERE id = ".$id_dom);
    }

    private function backupDomain($dom,$id_dom,$dir){

      echo "Backing up ".$dom." ...\n";
      echo "Selected dir is ".$dir."\n";

      $this->domainSettings($dom,$id_dom,$dir);

      $request_backup='<packet>
      <backup-manager>
         <backup-webspace>
            <webspace-name>'.$dom.'</webspace-name>
            <remote>ftp</remote>
            <prefix>'.$dom.'</prefix>
            <description>Backup settimanale '.$dom.'</description>
            <split-size>0</split-size>
         </backup-webspace>
      </backup-manager>
      </packet>';

      $response = $this->_client->request($request_backup);
      $task_id = $this->valueIn('task-id', $response);

      if ($task_id && $task_id > 0){
        echo "Running task id ".$task_id." ...\n";
        $i = 0;
        while ($this->getTaskStatus($task_id) != "finished" && $i < ($this->_max_waiting_time/$this->_sleep_time)){
          echo "[".date('Y-m-d H:i:s')."] Waiting for the task to be completed ...\n";
          sleep($this->_sleep_time);
          $i++;
        }
      } else {
        echo "There was an error in getting the task id!";
      }
    }

    private function serverSettings(){

      $request_settings='<packet>
    	  <backup-manager>
    	    <set-server-wide-settings>
    		<settings>
    	        <max-backup-files>5</max-backup-files>
    	        <max-backup-processes>10</max-backup-processes>
    	        <low-priority>true</low-priority>
    	        <do-not-compress>false</do-not-compress>
    	        <allow-local-ftp-backup>false</allow-local-ftp-backup>
    	        <keep-local-backup>false</keep-local-backup>
    	      </settings>
    	    </set-server-wide-settings>
    	  </backup-manager>
    	</packet>';

    	$this->_client->request($request_settings);

    }

    private function ftpMkSubdir($ftpcon,$ftpbasedir,$ftpath){
     @ftp_chdir($ftpcon, $ftpbasedir);
     $parts = explode('/',$ftpath);
     foreach($parts as $part){
        if(!@ftp_chdir($ftpcon, $part)){
           @ftp_mkdir($ftpcon, $part);
           @ftp_chdir($ftpcon, $part);
           //@ftp_chmod($ftpcon, 0777, $part);
        }
     }
  }

}
