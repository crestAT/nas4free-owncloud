<?php
/* 
    owncloud-config.php

    Copyright (c) 2015 - 2016 Andreas Schmidhuber
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

    The views and conclusions contained in the software and documentation are those
    of the authors and should not be interpreted as representing official policies,
    either expressed or implied, of the FreeBSD Project.
 */
require("auth.inc");
require("guiconfig.inc");
require("ext/owncloud/json.inc");

// Dummy standard message gettext calls for xgettext retrieval!!!
$dummy = gettext("The changes have been applied successfully.");
$dummy = gettext("The configuration has been changed.<br />You must apply the changes in order for them to take effect.");
$dummy = gettext("The following input errors were detected");

bindtextdomain("nas4free", "/usr/local/share/locale-owncloud");
$config_file = "ext/owncloud/owncloud.conf";
if (($configuration = load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "owncloud.conf");

$pgtitle = array(gettext("Extensions"), gettext("OwnCloud")." ".$configuration['version'], gettext("Configuration"));

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
                exec("chown {$_POST['who']} {$directory}*");
            }
            else $input_errors[] = sprintf(gettext("%s needs at least read & execute permissions at the mount point for directory %s! Set the Read and Execute bits for Others (Access Restrictions | Mode) for the mount point %s (in <a href='disks_mount.php'>Disks | Mount Point | Management</a> or <a href='disks_zfs_dataset.php'>Disks | ZFS | Datasets</a>) and hit Save in order to take them effect."), 
                gettext("OwnCloud"), $path, "/{$path_check[1]}/{$path_check[2]}");
        }
    }
}

if ((isset($_POST['save']) && $_POST['save']) || (isset($_POST['install']) && $_POST['install'])) {
    unset($input_errors);
	if (empty($input_errors)) {
        $configuration['enable'] = isset($_POST['enable']);
        if (isset($_POST['enable'])) {
            $configuration['storage_path'] = !empty($_POST['storage_path']) ? $_POST['storage_path'] : $config['websrv']['documentroot']."/owncloud";
            $configuration['storage_path'] = rtrim($configuration['storage_path'],'/');         // ensure to have NO trailing slash
            if (strpos($configuration['storage_path'], "{$config['websrv']['documentroot']}") === false) {
                $input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("OwnCloud")." ".gettext("document root"), "<b>'{$config['websrv']['documentroot']}'</b>");
            }
            else {
                $configuration['download_path'] = !empty($_POST['download_path']) ? $_POST['download_path'] : $g['media_path'];
                $configuration['download_path'] = rtrim($configuration['download_path'],'/');         // ensure to have NO trailing slash
                if (strpos($configuration['download_path'], "/mnt/") === false) {
                    $input_errors[] = sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("OwnCloud")." ".gettext("data folder"), "<b>'/mnt/'</b>");
                }
                else {
                    if (!is_dir($configuration['storage_path'])) mkdir($configuration['storage_path'], 0775, true);
                    change_perms($_POST['storage_path']);
                    if (!is_dir($configuration['download_path'])) mkdir($configuration['download_path'], 0775, true);
                    change_perms($_POST['download_path']);
                    // get the user for chown => <runasuser>server.username = "www"</runasuser>
                    $user = explode(" ", $config['websrv']['runasuser']);
                    chown($configuration['download_path'], str_replace('"', '', $user[2]));
                    $savemsg .= get_std_save_message(save_config($config_file, $configuration))." ";
                    require_once("{$configuration['rootfolder']}/owncloud-start.php");
                    if (isset($_POST['install']) && $_POST['install']) {
                        // download installer & install
                        $return_val = mwexec("fetch -vo {$configuration['storage_path']}/master.zip 'https://download.owncloud.org/community/owncloud-9.1.0.zip'", true);
                        if ($return_val == 0) {
                            $return_val = mwexec("tar -xf {$configuration['storage_path']}/master.zip -C {$configuration['storage_path']}/  --strip-components 1", true);
                            if ($return_val == 0) { 
                                exec("rm {$configuration['storage_path']}/master.zip");
                                $savemsg .= "<br />".gettext("OwnCloud")." ".gettext("successfully installed."); 
                            }
                            else {
                                $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /");
                                return;
                            }
                        }
                        else { $input_errors[] = sprintf(gettext("Download of installation file %s failed, installation aborted!"), "https://download.owncloud.org/community/owncloud-9.1.0.zip"); }
                    }
                }
            }
        }   //Eo-post-enable
        else $savemsg .= get_std_save_message(save_config($config_file, $configuration))." ";
    }   // Eo-empty input_errors
}   // Eo-save-install

$configuration['storage_path'] = !empty($configuration['storage_path']) ? $configuration['storage_path'] : str_replace("//", "/", $config['websrv']['documentroot']."/owncloud");
$configuration['download_path'] = !empty($configuration['download_path']) ? $configuration['download_path'] : $g['media_path'];

// Get webserver status
$status_webserver = rc_is_service_running('websrv');
if (0 === $status_webserver) $status_webserver_msg = '<a href="services_websrv.php" title="'.gettext("running").'"><img src="images/status_enabled.png" border="0" alt="" /></a>';
else $status_webserver_msg = '<a href="services_websrv.php" title="'.gettext("stopped").'"><img src="images/status_disabled.png" border="0" alt="" /></a>';

// Retrieve IP@ only when webserver is running
if (0 === $status_webserver) {
    $ipaddr = get_ipaddr($config['interfaces']['lan']['if']);
    $owncloud_document_root = str_replace($config['websrv']['documentroot'], "", $configuration['storage_path']);
    $url = htmlspecialchars("http://{$ipaddr}:{$config['websrv']['port']}/{$owncloud_document_root}");
    $ipurl = "<a href='{$url}' target='_blank'>{$url}</a>";
}
else $ipurl = "";

 /* 
$return_val = mwexec("fetch -o {$configuration['rootfolder']}//server-version.txt https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/version.txt", false);
if ($return_val == 0) {
    $server_version = exec("cat {$configuration['rootfolder']}/log/version.txt");
    if ($server_version != $configuration['version']) { $savemsg .= sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"), $server_version, gettext("Maintenance")); }
}   //EOversion-check
 */

bindtextdomain("nas4free", "/usr/local/share/locale");                  // to get the right main menu language
include("fbegin.inc");
bindtextdomain("nas4free", "/usr/local/share/locale-owncloud"); ?>
<!-- The Spinner Elements -->
<?php include("ext/owncloud/spinner.inc");?>
<script src="ext/owncloud/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->

<script type="text/javascript">
<!--
function enable_change(enable_change) {
    var endis = !(document.iform.enable.checked || enable_change);
	document.iform.storage_path.disabled = endis;
	document.iform.storage_pathbrowsebtn.disabled = endis;
	document.iform.download_path.disabled = endis;
	document.iform.download_pathbrowsebtn.disabled = endis;
	document.iform.install.disabled = endis;
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
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline_checkbox("enable", gettext("OwnCloud"), $configuration['enable'], gettext("Enable"), "enable_change(false)");?>
            <?php html_text("installation_directory", gettext("Installation directory"), sprintf(gettext("The extension is installed in %s"), $configuration['rootfolder']));?>
            <?php html_text("status_webserver", gettext("Status")." ".gettext("Webserver"), $status_webserver_msg);?>
			<?php html_filechooser("storage_path", gettext("OwnCloud")." ".gettext("document root"), $configuration['storage_path'], sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("OwnCloud")." ".gettext("document root"), "<b>'{$config['websrv']['documentroot']}'</b>"), true, 60);?>
			<?php html_filechooser("download_path", gettext("OwnCloud")." ".gettext("data folder"), $configuration['download_path'], sprintf(gettext("The %s MUST be set to a directory below %s."), gettext("OwnCloud")." ".gettext("data folder"), "<b>'/mnt/'</b>").
            " ".gettext("Use this folder at the first login screen.").
            "<br /><b><font color='red'>".sprintf(gettext("For security reasons this folder should NOT be set to a directory below %s!"), $config['websrv']['documentroot'])."</font></b>", true, 60);?>
            <?php html_text("url", gettext("OwnCloud")." ".gettext("URL"), $ipurl);?>
        </table>
        <div id="submit">
			<input id="save" name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>"/>
            <input name="install" type="submit" class="formbtn" title="<?=gettext("OwnCloud")." ".gettext("Install");?>" value="<?=gettext("Install");?>" onclick="return confirm('<?=sprintf(gettext("Ready to install %s?"), gettext("OwnCloud"));?>')" />
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
