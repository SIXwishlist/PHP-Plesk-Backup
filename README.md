# PHP-Plesk-Backup
The script will backup all the domains on a Plesk server and upload the compressed backup files on remote FTP repository.<br>

## Quick start
- [Download the latest version][download-link] of the package on the Plesk server you want to backup<br>
- Rename config-sample.php to config.php<br>
- Edit config.php file and fill in all the settings in order to have the script working correctly

## The two main methods

The _doBackup()_ method (used in backup.php) will parse all the domains and set a backup task for each one of them to be run immediately. Every domain will be backup in a different file and the script will wait for a backup task to be completed before proceeding with the next one.<br><br>
The _scheduleBackups()_ method (used in scheduled_backup.php) will parse all the domains in your server and set a scheduled backup task for each of them so that a backup will be performed weekly (you can set the day/time of the scheduled task using the settings, see below).


## The settings

You can add/change all the settings in the config.php file.<br>
In the file you will find a **$config** array that looks like this:

```php
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

  //the following settings are used only with scheduleBackups() method
  "scheduler_alert_email" => "0", //email alert in case of errors in the scheduled task
  "scheduler_day" => "7", //day of the week for the weekly backup task (7 is Sunday)
  "scheduler_time" => "00:00:00", //time for the weekly backup task

];
```

Just add all the missing settings and change the default ones if needed.
> Please note: "max_file_life" is set to 40 by default, so the backup files older than 40 days will be deleted. If you don't want this to happen, please increase this value accordingly.

## Execute the backup

The backup can be easily executed by running the included **backup.php** file.

## Automate the backup

You can use the _scheduleBackups_ method and run it periodically so that all the domains, even the newly added, have a scheduled backup task set.<br><br>
In alternative you can easily automate the backup by adding a scheduled activity on your Plesk Admin Panel.

**On Linux**<br>
> Please note: replace "/opt/scripts/PHP-Plesk-Backup" with the path where you downloaded the script.  
```html
/usr/local/psa/admin/bin/php -q '/opt/scripts/PHP-Plesk-Backup/backup.php'
```

**On Windows**<br>
> Please note: replace "C:\Scripts\PHP-Plesk-Backup\" with the path where you downloaded the script.  
```html
"C:\Program Files (x86)\Plesk\admin\engine\php.exe" -q "C:\Scripts\PHP-Plesk-Backup\backup.php"
```

[download-link]: https://github.com/josieit/PHP-Plesk-Backup/archive/master.zip
