<?php
/* 
    owncloud-config.php

    Copyright (c) 2015 - 2019 Andreas Schmidhuber
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
require("auth.inc");
require("guiconfig.inc");
require_once("ext/owncloud/extension-lib.inc");

// Dummy standard message gettext calls for xgettext retrieval!!!
$dummy = gettext("The changes have been applied successfully.");
$dummy = gettext("The configuration has been changed.<br />You must apply the changes in order for them to take effect.");
$dummy = gettext("The following input errors were detected");

$domain = strtolower(get_product_name());
bindtextdomain($domain, "/usr/local/share/locale-owncloud");
$config_file = "ext/owncloud/owncloud.conf";
if (($configuration = ext_load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "owncloud.conf");
$errormsg = "";

$pgtitle = array(gettext("Extensions"), $configuration['appname']." ".$configuration['version'], gettext("Configuration"));

/* Get webserver status and IP@ */
function get_process_info() {
    global $config, $configuration, $errormsg;

    // Get webserver status
    $enable_webserver = isset($config['websrv']['enable']) ? true : false;
    $status_webserver = rc_is_service_running('websrv');
    if ($enable_webserver) $enable_webserver_msg = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gettext("enabled").'</b>&nbsp;&nbsp;</a>';
    else $enable_webserver_msg = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gettext("disabled").'</b>&nbsp;&nbsp;</a>';
    if (0 === $status_webserver) $status_webserver_msg = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gettext("running").'</b>&nbsp;&nbsp;</a>';
    else {
		$status_webserver_msg = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gettext("stopped").'</b>&nbsp;&nbsp;</a>';
		$errormsg .= sprintf(gettext("The %s service is not active, it must be enabled and running to use %s!"), gettext("Webserver"), gettext($configuration['application']))."&nbsp;";
	} 

    // check for standard Webserver upload directory
    if (($config['websrv']['uploaddir'] == "/var/tmp/ftmp") || ($config['websrv']['uploaddir'] == "")) {
    	$alert_title = sprintf(gettext("Change the %s in %s | %s to another path which has more disk space!"), gettext("Upload directory"), gettext("Services"), gettext("Webserver"));
		$alert_text = gettext("Upload directory")." ".gettext("is on default location, this could lead to upload problems for big files!"); 
		$status_webserver_uploaddir = '<a title="'.$alert_title.' "style=" background-color: orange; ">&nbsp;&nbsp;<b>'.$alert_text.'</b>&nbsp;&nbsp;</a>';
		$errormsg .= $alert_text." ".$alert_title."&nbsp;";
	}
	else $status_webserver_uploaddir = "";
	
    $state['webserver'] = $enable_webserver_msg."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$status_webserver_msg."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$status_webserver_uploaddir;
	return ($state);
}

/* Check if the directory exists, the mountpoint has at least o=rx permissions and
 * set the permission to 775 for the last directory in the path
 */
function change_perms($dir) {
    global $input_errors;

    $path = rtrim($dir,'/');                                            // remove trailing slash
    if (strlen($path) > 1) {
        if (!is_dir($path)) {                                           // check if directory exists
            $input_errors[] = sprintf(gettext("Directory %s doesn't exist!"), $path);
        }
        else {
            $path_check = explode("/", $path);                          // split path to get directory names
            $path_elements = count($path_check);                        // get path depth
            $fp = substr(sprintf('%o', fileperms("/$path_check[1]/$path_check[2]")), -1);   // get mountpoint permissions for others
            if ($fp >= 5) {                                             // transmission needs at least read & search permission at the mountpoint
                $directory = "/$path_check[1]/$path_check[2]";          // set to the mountpoint
                for ($i = 3; $i < $path_elements - 1; $i++) {           // traverse the path and set permissions to rx
                    $directory = $directory."/$path_check[$i]";         // add next level
                    exec("chmod o=+r+x \"$directory\"");                // set permissions to o=+r+x
                }
                $path_elements = $path_elements - 1;
                $directory = $directory."/$path_check[$path_elements]"; // add last level
                exec("chmod 775 {$directory}");                         // set permissions to 775
            }
            else $input_errors[] = sprintf(gettext("%s needs at least read & execute permissions at the mount point for directory %s! Set the Read and Execute bits for Others (Access Restrictions | Mode) for the mount point %s (in <a href='disks_mount.php'>Disks | Mount Point | Management</a> or <a href='disks_zfs_dataset.php'>Disks | ZFS | Datasets</a>) and hit Save in order to take them effect."), 
                gettext("NextOwnCloud"), $path, "/{$path_check[1]}/{$path_check[2]}");
        }
    }
}

if ((isset($_POST['save']) && $_POST['save']) || (isset($_POST['install']) && $_POST['install'])) {
    unset($input_errors);
	if (empty($input_errors)) {
        $configuration['enable'] = isset($_POST['enable']);
        if (isset($_POST['enable'])) {
        	$configuration['application'] = $_POST['application']; 
            $configuration['storage_path'] = !empty($_POST['storage_path']) ? $_POST['storage_path'] : $config['websrv']['documentroot']."/owncloud";
            $configuration['storage_path'] = rtrim($configuration['storage_path'],'/');         // ensure to have NO trailing slash
            if (strpos($configuration['storage_path'], "{$config['websrv']['documentroot']}") === false) {
                $input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext($configuration['application'])." ".gettext("Document Root"), "<b>'{$config['websrv']['documentroot']}'</b>");
                $configuration['url'] = "";
            }
            else {
                $configuration['download_path'] = !empty($_POST['download_path']) ? $_POST['download_path'] : $g['media_path'];
                $configuration['download_path'] = rtrim($configuration['download_path'],'/');         // ensure to have NO trailing slash
                if (strpos($configuration['download_path'], "/mnt/") === false) {
                    $input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext($configuration['application'])." ".gettext("Data Folder"), "<b>'/mnt/'</b>");
	                $configuration['url'] = "";
                }
                else {
                    // get the user for chown => <runasuser>server.username = "www"</runasuser> or if not set use "root"
                    if (isset($config['websrv']['runasuser']) && !empty($config['websrv']['runasuser'])) {
                        $user = explode(" ", $config['websrv']['runasuser']);
                        $user = str_replace('"', '', $user[2]);
                    }
                    else $user = "root";                    
					$configuration['webuser'] = $user;
					
                    if (!is_dir($configuration['storage_path'])) mkdir($configuration['storage_path'], 0775, true);
                    change_perms($configuration['storage_path']);
                    chown($configuration['storage_path'], $user);
                    if (!is_dir($configuration['download_path'])) mkdir($configuration['download_path'], 0775, true);
                    change_perms($configuration['download_path']);
	                $configuration['backup_path'] = !empty($_POST['backup_path']) ? $_POST['backup_path'] : $g['media_path'];
	                $configuration['backup_path'] = rtrim($configuration['backup_path'],'/');         // ensure to have NO trailing slash
	                if (strpos($configuration['download_path'], "/mnt/") === false) {
	                    $input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext($configuration['application'])." ".gettext("Data Folder"), "<b>'/mnt/'</b>");
	                }
                    if (!is_dir($configuration['backup_path'])) mkdir($configuration['backup_path'], 0775, true);
                    change_perms($configuration['backup_path']);
                    chown($configuration['download_path'], $user);
                	chown($config['websrv']['uploaddir'], $user);		// ensures to set the user has the right permission to write to the path 
                    chown($configuration['backup_path'], $user);

			        $ipaddr = get_ipaddr($config['interfaces']['lan']['if']);
			        $owncloud_document_root = str_replace($config['websrv']['documentroot'], "", $configuration['storage_path']);
			        $owncloud_document_root = trim($owncloud_document_root, " /");		// ensures to have no "/" at the beginning or end of the string
			        $url = htmlspecialchars("{$config['websrv']['protocol']}://{$ipaddr}:{$config['websrv']['port']}/{$owncloud_document_root}");
			        $configuration['url'] = "<a href='{$url}' target='_blank'>{$url}</a>";
			        $phpinfo_url = htmlspecialchars("{$config['websrv']['protocol']}://{$ipaddr}:{$config['websrv']['port']}/nextowncloud-phpinfo.php");
			        $configuration['phpinfo_url'] = "=&gt;&nbsp;<a href='{$phpinfo_url}' target='_blank'>PHPInfo</a>";

					$configuration[$configuration['application']]['storage_path'] = $configuration['storage_path'];
					$configuration[$configuration['application']]['download_path'] = $configuration['download_path'];
					$configuration[$configuration['application']]['backup_path'] = $configuration['backup_path'];
					$configuration[$configuration['application']]['url'] = $configuration['url'];

                    $savemsg .= get_std_save_message(ext_save_config($config_file, $configuration))." ";

                    if (isset($_POST['install']) && $_POST['install']) {
                        // download installer & install
                        $return_val = mwexec("fetch -vo {$configuration['storage_path']}/master.zip {$configuration[$configuration['application']]['source']}", false);
                        if ($return_val == 0) {
                            $return_val = mwexec("tar -xf {$configuration['storage_path']}/master.zip -C {$configuration['storage_path']}/  --strip-components 1", false);
                            if ($return_val == 0) { 
                                exec("rm {$configuration['storage_path']}/master.zip");
                                copy("{$configuration['rootfolder']}/nextowncloud-phpinfo.php", "{$config['websrv']['documentroot']}/nextowncloud-phpinfo.php");
			                    mwexec("chown -R {$user} {$configuration['storage_path']}", true);
								$savemsg = $configuration['application']." ".gettext("has been successfully installed.");
								$savemsg .= "<br />".sprintf(gettext("Proceed to the %s %s and finish the installation, don't forget to use the %s under 'Storage and database' on the setup page!"), $configuration['application'], gettext("URL"), gettext("Data Folder"));
                            }
                            else {
                                $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /");
                                return;
                            }
                        }
                        else { $input_errors[] = sprintf(gettext("Download of installation file %s failed, installation aborted!"), $configuration[$configuration['application']]['source']); }
                    }
					if (is_array($config['websrv'])) {									
					// Prepare Webserver for additional settings to prevent warnings from NC/OC
						if (is_array($config['websrv']['auxparam'])) $rc_param_count = count($config['websrv']['auxparam']);
						else $rc_param_count = 0; 
						// check for Strict-Transport-Security
						$rc_param_found = 0;
						for ($i = 0; $i < $rc_param_count; $i++) if (preg_match("/Strict-Transport-Security/", $config['websrv']['auxparam'][$i])) $rc_param_found = 1;
						if ($rc_param_found == 0) {
							$config['websrv']['auxparam'][] = '$HTTP["scheme"]=="https"{setenv.add-response-header=("Strict-Transport-Security"=>"max-age=63072000;includeSubdomains;")}';
							write_config();
							$savemsg .= "<br />".gettext("Webserver")." ".gettext("Auxiliary parameters")." ".gettext("has been extended with").' $HTTP["scheme"]=="https"{setenv.add-response-header=("Strict-Transport-Security"=>"max-age=63072000;includeSubdomains;")}';
						}
						// check for Referrer-Policy => NC v14.0.3
						$rc_param_found = 0;
						for ($i = 0; $i < $rc_param_count; $i++) if (preg_match("/Referrer-Policy/", $config['websrv']['auxparam'][$i])) $rc_param_found = 1;
						if ($rc_param_found == 0) {
							$config['websrv']['auxparam'][] = 'setenv.set-response-header = ("Referrer-Policy"=>"no-referrer")';
							write_config();
							$savemsg .= "<br />".gettext("Webserver")." ".gettext("Auxiliary parameters")." ".gettext("has been extended with").' setenv.set-response-header = ("Referrer-Policy"=>"no-referrer")';
						}
						// check for .well-known\/caldav => NC v14.0.3
						$rc_param_found = 0;
						for ($i = 0; $i < $rc_param_count; $i++) if (preg_match("/.well-known\/caldav/", $config['websrv']['auxparam'][$i])) $rc_param_found = 1;
						if ($rc_param_found == 0) {
							$urlRedirect = 'url.redirect += ("^/.well-known/caldav"  => "/'.$owncloud_document_root.'/remote.php/dav")';
							$config['websrv']['auxparam'][] = $urlRedirect;
							write_config();
							$savemsg .= "<br />".gettext("Webserver")." ".gettext("Auxiliary parameters")." ".gettext("has been extended with")." {$urlRedirect}";
						}
						// check for .well-known\/carddav => NC v14.0.3
						$rc_param_found = 0;
						for ($i = 0; $i < $rc_param_count; $i++) if (preg_match("/.well-known\/carddav/", $config['websrv']['auxparam'][$i])) $rc_param_found = 1;
						if ($rc_param_found == 0) {
							$urlRedirect = 'url.redirect += ("^/.well-known/carddav"  => "/'.$owncloud_document_root.'/remote.php/dav")';
							$config['websrv']['auxparam'][] = $urlRedirect;
							write_config();
							$savemsg .= "<br />".gettext("Webserver")." ".gettext("Auxiliary parameters")." ".gettext("has been extended with")." {$urlRedirect}";
						}
					}
                    require_once("{$configuration['rootfolder']}/owncloud-start.php");	// Webserver restart

					$rsync_logfile = rc_getenv_ex('rsync_client_logfile',"{$g['varlog_path']}/rsync_client.log");
					$backup_script = fopen("{$configuration['rootfolder']}/{$configuration['application']}-backup.sh", "w");
					fwrite($backup_script, "#!/bin/sh"."\n# WARNING: THIS IS AN AUTOMATICALLY CREATED SCRIPT, DO NOT CHANGE THE CONTENT!\n");
					fwrite($backup_script, "# Command for cron backup usage: {$configuration['rootfolder']}/{$configuration['application']}-backup.sh\n");
					fwrite($backup_script, "RSYNC_LOGFILE={$rsync_logfile}"."\n");
					fwrite($backup_script, "ERROR_COUNT=0"."\n");
					fwrite($backup_script, "logger -p local4.notice {$configuration['application']} backup started"."\n");
					fwrite($backup_script, "/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --on >> \$RSYNC_LOGFILE"."\n");
					fwrite($backup_script, "if [ $? -ne 0 ]; then echo 'Error - set maintenance mode ON failed!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($backup_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['storage_path']}/ {$configuration[$configuration['application']]['backup_path']}/APPLICATION"."\n");
					fwrite($backup_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($backup_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['download_path']}/ {$configuration[$configuration['application']]['backup_path']}/DATA"."\n");
					fwrite($backup_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($backup_script, "/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --off >> \$RSYNC_LOGFILE"."\n");
					fwrite($backup_script, "if [ $? -ne 0 ]; then echo 'Error - set maintenance mode OFF failed!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($backup_script, "logger -p local4.notice {$configuration['application']} backup finished with \$ERROR_COUNT error\(s\)"."\n");
					fwrite($backup_script, "date > {$configuration['rootfolder']}/{$configuration['application']}-backup-date.txt"."\n");
					fclose($backup_script);
	                chmod("{$configuration['rootfolder']}/{$configuration['application']}-backup.sh", 0755); 
					$savemsg .= "<br />".sprintf(gettext("Command for cron backup usage: %s"), "{$configuration['rootfolder']}/{$configuration['application']}-backup.sh").".<br />";

					$restore_script = fopen("{$configuration['rootfolder']}/{$configuration['application']}-restore.sh", "w");
					fwrite($restore_script, "#!/bin/sh"."\n# WARNING: THIS IS AN AUTOMATICALLY CREATED SCRIPT, DO NOT CHANGE THE CONTENT!\n");
					fwrite($restore_script, "RSYNC_LOGFILE={$rsync_logfile}"."\n");
					fwrite($restore_script, "ERROR_COUNT=0"."\n");
					fwrite($restore_script, "logger -p local4.notice {$configuration['application']} restore started"."\n");
					fwrite($restore_script, "/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --on >> \$RSYNC_LOGFILE"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error - set maintenance mode ON failed!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['backup_path']}/APPLICATION/ {$configuration[$configuration['application']]['storage_path']}"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['backup_path']}/DATA/ {$configuration[$configuration['application']]['download_path']}"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --off >> \$RSYNC_LOGFILE"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error - set maintenance mode OFF failed!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "logger -p local4.notice {$configuration['application']} restore finished with \$ERROR_COUNT error\(s\)"."\n");
					fclose($restore_script);
	                chmod("{$configuration['rootfolder']}/{$configuration['application']}-restore.sh", 0755);

					$restore_script = fopen("{$configuration['rootfolder']}/{$configuration['application']}-restore_userdata.sh", "w");
					fwrite($restore_script, "#!/bin/sh"."\n# WARNING: THIS IS AN AUTOMATICALLY CREATED SCRIPT, DO NOT CHANGE THE CONTENT!\n");
					fwrite($restore_script, "RSYNC_LOGFILE={$rsync_logfile}"."\n");
					fwrite($restore_script, "ERROR_COUNT=0"."\n");
					fwrite($restore_script, "logger -p local4.notice {$configuration['application']} restore userdata started"."\n");
					fwrite($restore_script, "/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --on >> \$RSYNC_LOGFILE"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error - set maintenance mode ON failed!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['backup_path']}/APPLICATION/config {$configuration[$configuration['application']]['storage_path']}"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['backup_path']}/APPLICATION/themes {$configuration[$configuration['application']]['storage_path']}"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/rsync -Aax  --log-file=\$RSYNC_LOGFILE --delete {$configuration[$configuration['application']]['backup_path']}/DATA/ {$configuration[$configuration['application']]['download_path']}"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error during rsync execution!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --off >> \$RSYNC_LOGFILE"."\n");
					fwrite($restore_script, "if [ $? -ne 0 ]; then echo 'Error - set maintenance mode OFF failed!' >> \$RSYNC_LOGFILE; ERROR_COUNT=$((ERROR_COUNT+1)); fi"."\n");
					fwrite($restore_script, "logger -p local4.notice {$configuration['application']} restore userdata finished with \$ERROR_COUNT error\(s\)"."\n");
					fclose($restore_script);
	                chmod("{$configuration['rootfolder']}/{$configuration['application']}-restore_userdata.sh", 0755);

                }
            }
        }   //Eo-post-enable
        else $savemsg .= get_std_save_message(ext_save_config($config_file, $configuration))." ";
    }   // Eo-empty input_errors
}   // Eo-save-install

if (isset($_POST['remove']) && $_POST['remove']) {
	if (strpos($configuration['storage_path'], "{$config['websrv']['documentroot']}") === false) {
		$input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext($configuration['application'])." ".gettext("Document Root"), "<b>'{$config['websrv']['documentroot']}'</b>");
	}
	elseif (strpos($configuration['download_path'], "/mnt/") === false) {
		$input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext($configuration['application'])." ".gettext("Data Folder"), "<b>'/mnt/'</b>");
	}
	else mwexec("rm -Rf {$configuration['storage_path']} {$configuration['download_path']}");
}

if (isset($_POST['backup']) && $_POST['backup']) {
	mwexec("nohup {$configuration['rootfolder']}/{$configuration['application']}-backup.sh >/dev/null 2>&1 &", true);
	$savemsg .= sprintf(gettext("%s started, output goes to %s"), gettext('Backup'), gettext('Diagnostics')." > ".gettext('Log')." > ".gettext('RSYNC - Client'));
}

if (isset($_POST['restore']) && $_POST['restore']) {
	mwexec("nohup {$configuration['rootfolder']}/{$configuration['application']}-restore.sh >/dev/null 2>&1 &", true);
	$savemsg .= sprintf(gettext("%s started, output goes to %s"), gettext('Restore'), gettext('Diagnostics')." > ".gettext('Log')." > ".gettext('RSYNC - Client'));
}

if (isset($_POST['restore_userdata']) && $_POST['restore_userdata']) {
	mwexec("nohup {$configuration['rootfolder']}/{$configuration['application']}-restore_userdata.sh >/dev/null 2>&1 &", true);
	$savemsg .= sprintf(gettext("%s started, output goes to %s"), gettext('Restore'), gettext('Diagnostics')." > ".gettext('Log')." > ".gettext('RSYNC - Client'));
}

if (isset($_POST['tune_cache']) && $_POST['tune_cache']) {
	$return_val = mwexec("cat {$configuration['storage_path']}/config/config.php | grep memcache.local");
	if ($return_val != 0) {
		include("{$configuration['storage_path']}/config/config.php");
		$CONFIG['memcache.local'] = '\OC\Memcache\APCu';
		$patch = "<?php\n".'$CONFIG = '.var_export($CONFIG, true).";\n";
		mwexec("/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --on", true);
		file_put_contents("{$configuration['storage_path']}/config/config.php", $patch);
		mwexec("/usr/local/bin/sudo -u {$configuration['webuser']} /usr/local/bin/php {$configuration['storage_path']}/occ maintenance:mode --no-warnings --off", true);
	}
	$savemsg .= gettext("APCu memcache is now activated.")."<br />";
}

// initialize params for first run
$configuration['application'] = !empty($configuration['application']) ? $configuration['application'] : "OwnCloud";
$configuration['OwnCloud']['storage_path'] = !empty($configuration['OwnCloud']['storage_path']) ? $configuration['OwnCloud']['storage_path'] : str_replace("//", "/", $config['websrv']['documentroot']."/owncloud");
$configuration['OwnCloud']['download_path'] = !empty($configuration['OwnCloud']['download_path']) ? $configuration['OwnCloud']['download_path'] : $g['media_path'];
$configuration['OwnCloud']['backup_path'] = !empty($configuration['OwnCloud']['backup_path']) ? $configuration['OwnCloud']['backup_path'] : $g['media_path'];
$configuration['OwnCloud']['url'] = !empty($configuration['OwnCloud']['url']) ? $configuration['OwnCloud']['url'] : "";
$configuration['NextCloud']['storage_path'] = !empty($configuration['NextCloud']['storage_path']) ? $configuration['NextCloud']['storage_path'] : str_replace("//", "/", $config['websrv']['documentroot']."/nextcloud");
$configuration['NextCloud']['download_path'] = !empty($configuration['NextCloud']['download_path']) ? $configuration['NextCloud']['download_path'] : $g['media_path'];
$configuration['NextCloud']['backup_path'] = !empty($configuration['NextCloud']['backup_path']) ? $configuration['NextCloud']['backup_path'] : $g['media_path'];
$configuration['NextCloud']['url'] = !empty($configuration['NextCloud']['url']) ? $configuration['NextCloud']['url'] : "";

$configuration['storage_path'] = !empty($configuration['storage_path']) ? $configuration['storage_path'] : $configuration[$configuration['application']]['storage_path'];
$configuration['download_path'] = !empty($configuration['download_path']) ? $configuration['download_path'] : $configuration[$configuration['application']]['download_path'];
$configuration['backup_path'] = !empty($configuration['backup_path']) ? $configuration['backup_path'] : $configuration[$configuration['application']]['backup_path'];
$configuration['url'] = !empty($configuration['url']) ? $configuration['url'] : $configuration[$configuration['application']]['url'];

if (($message = ext_check_version("{$configuration['rootfolder']}/version_server.txt", "owncloud", $configuration['version'], gettext("Maintenance"))) !== false) $savemsg .= $message;

/* check for secure data directory setup */
if (($configuration['enable']) && (is_file("{$configuration['storage_path']}/config/config.php"))) {
	include("{$configuration['storage_path']}/config/config.php");
	if ($CONFIG['datadirectory'] !== $configuration['download_path']) {
		$input_errors[] = sprintf(gettext("The %s is not used in your current %s configuration!"), gettext("Data Folder"), gettext($configuration['application']));
		$input_errors[] = sprintf(gettext("For security reasons this folder should NOT be set to a directory below %s!"), $config['websrv']['documentroot']);
		$input_errors[] = sprintf(gettext("It is strongly recommended to remove and repeat the %s installation!"), gettext($configuration['application']));
	}
	$return_val = mwexec("cat {$configuration['storage_path']}/config/config.php | grep memcache.local");
	if ($return_val != 0) {
		$savemsg .= sprintf(gettext("No memory cache has been configured for %s. Use %s to activate the APCu memcache."), gettext($configuration['application']), gettext("Tune Cache"));
	}
}

get_process_info();
if (is_ajax()) {
    $getinfo = get_process_info();
	render_ajax($getinfo);
}

bindtextdomain($domain, "/usr/local/share/locale");                  // to get the right main menu language
include("fbegin.inc");
bindtextdomain($domain, "/usr/local/share/locale-owncloud"); 
?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 10000, 'owncloud-config.php', null, function(data) {
        $('#getinfo_webserver').html(data.webserver);
	});
});
//]]>
</script>
<script type="text/javascript">
<!--
function enable_change(enable_change) {
    var endis = !(document.iform.enable.checked || enable_change);
	document.iform.application.disabled = endis;
	document.iform.storage_path.disabled = endis;
	document.iform.storage_pathbrowsebtn.disabled = endis;
	document.iform.download_path.disabled = endis;
	document.iform.download_pathbrowsebtn.disabled = endis;
	document.iform.backup_path.disabled = endis;
	document.iform.backup_pathbrowsebtn.disabled = endis;
	if (typeof document.iform.install !== "undefined") document.iform.install.disabled = endis;
	if (typeof document.iform.remove !== "undefined") document.iform.remove.disabled = endis;
	if (typeof document.iform.backup !== "undefined") document.iform.backup.disabled = endis;
	if (typeof document.iform.restore !== "undefined") document.iform.restore.disabled = endis;
	if (typeof document.iform.restore_userdata !== "undefined") document.iform.restore_userdata.disabled = endis;
}

function change_application() {
	if (typeof document.iform.install !== "undefined") document.iform.install.disabled = true;
	if (typeof document.iform.remove !== "undefined") document.iform.remove.disabled = true;
	if (typeof document.iform.backup !== "undefined") document.iform.backup.disabled = true;
	if (typeof document.iform.restore !== "undefined") document.iform.restore.disabled = true;
	if (typeof document.iform.restore_userdata !== "undefined") document.iform.restore_userdata.disabled = true;
	switch(document.iform.application.selectedIndex) {
		case 0:
			document.iform.storage_path.value = decodeURIComponent('<?php echo urlencode($configuration['OwnCloud']['storage_path']);?>');
			document.iform.download_path.value = decodeURIComponent('<?php echo urlencode($configuration['OwnCloud']['download_path']);?>');
			$('#getinfo_url').html("<?php echo $configuration['OwnCloud']['url'];?>");
			document.iform.backup_path.value = decodeURIComponent('<?php echo urlencode($configuration['OwnCloud']['backup_path']);?>');
			break;
				
		case 1:
			document.iform.storage_path.value = decodeURIComponent('<?php echo urlencode($configuration['NextCloud']['storage_path']);?>');
			document.iform.download_path.value = decodeURIComponent('<?php echo urlencode($configuration['NextCloud']['download_path']);?>');
			$('#getinfo_url').html("<?php echo $configuration['NextCloud']['url'];?>");
			document.iform.backup_path.value = decodeURIComponent('<?php echo urlencode($configuration['NextCloud']['backup_path']);?>');
			break;
	}
}
//-->
</script>
<form action="owncloud-config.php" method="post" name="iform" id="iform" onsubmit="spinner()">
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabact"><a href="owncloud-config.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabinact"><a href="owncloud-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
		</ul>
	</td></tr>
    <tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($errormsg)) print_error_box($errormsg);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline_checkbox("enable", gettext("NextOwnCloud"), $configuration['enable'], gettext("Enable"), "enable_change(false)");?>
            <?php html_text("installation_directory", gettext("Installation directory"), sprintf(gettext("The extension is installed in %s"), $configuration['rootfolder']));?>
            <tr>
                <td class="vncell"><?=gettext("Webserver")." ".gettext("Status");?></td>
                <td class="vtable"><span name="getinfo_webserver" id="getinfo_webserver"><?=get_process_info()['webserver'];?></span></td>
            </tr>
			<?php html_combobox("application", gettext("Application"), $configuration['application'], array('OwnCloud' =>'OwnCloud','NextCloud'=> 'NextCloud'), gettext("Choose application"), true, false, "change_application()");?>
			<?php html_filechooser("storage_path", gettext("Document Root"), $configuration['storage_path'], sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("Document Root"), "<b>'{$config['websrv']['documentroot']}'</b>"), true, 60);?>
			<?php html_filechooser("download_path", gettext("Data Folder"), $configuration['download_path'], sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("Data Folder"), "<b>'/mnt/'</b>").
            " <b><font color='blue'>".gettext("Use this folder at the first login screen.")."</font></b>".
            "<br /><b><font color='red'>".sprintf(gettext("For security reasons this folder should NOT be set to a directory below %s!"), $config['websrv']['documentroot'])."</font></b>", true, 60);?>
			<?php html_filechooser("backup_path", gettext("Backup Folder"), $configuration['backup_path'], sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("Backup Folder"), "<b>'/mnt/'</b>"), true, 60);?>
            <?php html_text("last_backup", gettext("Last Backup"), exec("cat {$configuration['rootfolder']}/{$configuration['application']}-backup-date.txt"));?>
            <tr>
                <td class="vncell"><?=gettext("URL");?></td>
                <td class="vtable">
					<?php
						if ($configuration['enable']) {
							if (is_file("{$configuration['storage_path']}/index.php")) {
								echo "<span id='getinfo_url'>{$configuration['url']}</span>&nbsp;&nbsp;&nbsp;";
								if (is_file("{$config['websrv']['documentroot']}/nextowncloud-phpinfo.php")) {
									echo "<span id='phpinfo_url'>{$configuration['phpinfo_url']}</span>";
								}
							}
						} 
					?>
				</td>
            </tr>
        </table>
        <div id="remarks">
            <?php html_remark("note", gettext("Note"), 
				sprintf(gettext("Use the %s WebGUI > 'Administration' to change values and to update the application."), $configuration['application'])."<br /><b>".
				sprintf(gettext("Always perform a %s prior to an application update!"), gettext("Backup"))."</b>");?>
        </div>
		<div id="submit">
			<input name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>"/>
			<?php if ($configuration['enable']):?>
				<?php if (!is_file("{$configuration['storage_path']}/version.php")):?>
					<input name="install" type="submit" class="formbtn" value="<?=gettext("Install");?>" onclick="return confirm('<?=gettext("Ready to install?");?>')" />
				<?php else:?> 
					<input name="remove" type="submit" class="formbtn" title="<?=gettext("Remove")." ".sprintf(gettext("the %s application. The %s and the %s will be deleted!"), $configuration['application'], gettext("Document Root"), gettext("Data Folder"));?>"
					value="<?=gettext("Remove");?>" onclick="return confirm('<?=sprintf(gettext("The application will be removed, all data in the %s (%s) and the %s (%s) will be deleted. Ready to proceed?"), gettext("Document Root"), gettext($configuration['storage_path']), gettext("Data Folder"), gettext($configuration['download_path']));?>')" />
					<input name="backup" type="submit" class="formbtn" title="<?=gettext("Backup")." ".sprintf(gettext("the %s and the %s!"), gettext("Document Root"), gettext("Data Folder"));?>"
					value="<?=gettext("Backup");?>" onclick="return confirm('<?=sprintf(gettext("The %s (%s) and the %s (%s) will be backuped. Ready to proceed?"), gettext("Document Root"), gettext($configuration['storage_path']), gettext("Data Folder"), gettext($configuration['download_path']));?>')" />
					<input name="restore" type="submit" class="formbtn" title="<?=gettext("Restore")." ".sprintf(gettext("the %s and the %s!"), gettext("Document Root"), gettext("Data Folder"));?>"
					value="<?=gettext("Restore");?>" onclick="return confirm('<?=sprintf(gettext("The %s (%s) and the %s (%s) will be restored, the current installation will be overwritten with the backup from %s. Ready to proceed?"), gettext("Document Root"), gettext($configuration['storage_path']), gettext("Data Folder"), gettext($configuration['download_path']), exec("cat {$configuration['rootfolder']}/{$configuration['application']}-backup-date.txt"));?>')" />
					<input name="restore_userdata" type="submit" class="formbtn" title="<?=gettext("Restore Userdata")." ".sprintf(gettext("to a manually installed/updated %s installation!"), $configuration['application']);?>"
					value="<?=gettext("Restore Userdata");?>" onclick="return confirm('<?=gettext("Restore Userdata")." ".sprintf(gettext("to a manually installed/updated %s installation!"), $configuration['application']);?>')" />
					<?php if (($configuration['enable']) && (is_file("{$configuration['storage_path']}/config/config.php"))):?>
						<?php $return_val = mwexec("cat {$configuration['storage_path']}/config/config.php | grep memcache.local");
							if ($return_val != 0):?>
								<input name="tune_cache" type="submit" class="formbtn" title="<?=gettext("Tune Cache")." ".gettext("to activate the APCu memcache");?>"
								value="<?=gettext("Tune Cache");?>" onclick="return confirm('<?=gettext("Tune Cache")." ".gettext("to activate the APCu memcache")."?";?>')" />
							<?php endif;?>
					<?php endif;?>
				<?php endif;?>
			<?php endif;?>
        </div>
	</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
