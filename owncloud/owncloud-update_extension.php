<?php
/*
    owncloud-update_extension.php
    
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
require_once("ext/owncloud/json.inc");

bindtextdomain("nas4free", "/usr/local/share/locale-owncloud");
$config_file = "ext/owncloud/owncloud.conf";
if (($configuration = load_config($config_file)) === false) {
    $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "owncloud.conf");
}

$pgtitle = array(gettext("Extensions"), gettext("OwnCloud")." ".$configuration['version'], gettext("Maintenance"));

if (is_file("{$configuration['rootfolder']}/oneload")) { require_once("{$configuration['rootfolder']}/oneload"); }

$return_val = mwexec("fetch -o {$configuration['rootfolder']}/version_server.txt https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/version.txt", false);
if ($return_val == 0) { 
    $server_version = exec("cat {$configuration['rootfolder']}/version_server.txt"); 
    if ($server_version != $configuration['version']) { $savemsg .= sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"), $server_version, gettext("Update Extension")); }
    mwexec("fetch -o {$configuration['rootfolder']}/release_notes.txt https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/release_notes.txt", false);
}
else { $server_version = gettext("Unable to retrieve version from server!"); }

if (isset($_POST['ext_remove']) && $_POST['ext_remove']) {
// remove start command
    if (is_array($config['rc']['postinit'] ) && is_array($config['rc']['postinit']['cmd'])) {
		for ($i = 0; $i < count($config['rc']['postinit']['cmd']); $i++) {
    		if (preg_match('/owncloud/', $config['rc']['postinit']['cmd'][$i])) unset($config['rc']['postinit']['cmd'][$i]);
		}
	}
// remove links
    if (is_link("/usr/local/share/locale-owncloud")) unlink("/usr/local/share/locale-owncloud");
    if (is_link("/usr/local/www/owncloud-config.php")) unlink("/usr/local/www/owncloud-config.php");
    if (is_link("/usr/local/www/owncloud-update_extension.php")) unlink("/usr/local/www/owncloud-update_extension.php");
    if (is_link("/usr/local/www/ext/owncloud")) unlink("/usr/local/www/ext/owncloud");
    mwexec("rmdir -p /usr/local/www/ext");
    write_config();
	header("Location:index.php");
}

if (isset($_POST['ext_update']) && $_POST['ext_update']) {
// download installer & install
    $return_val = mwexec("fetch -vo {$configuration['rootfolder']}/owncloud-install.php 'https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/owncloud-install.php'", true);
    if ($return_val == 0) {
        require_once("{$configuration['rootfolder']}/owncloud-install.php"); 
        $version = exec("cat {$configuration['rootfolder']}/version.txt");
        $savemsg = sprintf(gettext("Update to version %s completed!"), $version);
        header("Refresh:8");;
    }
    else { $input_errors[] = sprintf(gettext("Download of installation file %s failed, installation aborted!"), "owncloud-install.php"); }
}

bindtextdomain("nas4free", "/usr/local/share/locale");                  // to get the right main menu language
include("fbegin.inc");
bindtextdomain("nas4free", "/usr/local/share/locale-owncloud"); ?>
<!-- The Spinner Elements -->
<?php include("ext/owncloud/spinner.inc");?>
<script src="ext/owncloud/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->

<form action="owncloud-update_extension.php" method="post" name="iform" id="iform" onsubmit="spinner()">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabinact"><a href="owncloud-config.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabact"><a href="owncloud-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
		</ul>
	</td></tr>
	<tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline(gettext("Extension Update"));?>
			<?php html_text("ext_version_current", gettext("Installed version"), $configuration['version']);?>
			<?php html_text("ext_version_server", gettext("Latest version"), $server_version);?>
			<?php html_separator();?>
        </table>
        <div id="update_remarks">
            <?php html_remark("note_remove", gettext("Note"), sprintf(gettext("Removing %s integration from NAS4Free will leave the installation folder untouched - remove the files using Windows Explorer, FTP or some other tool of your choice. <br /><b>Please note: this page will no longer be available.</b> You'll have to re-run %s extension installation to get it back on your NAS4Free."), $configuration['appname'], $configuration['appname']));?>
            <br />
            <input id="ext_update" name="ext_update" type="submit" class="formbtn" value="<?=gettext("Update Extension");?>" onclick="return confirm('<?=gettext("The selected operation will be completed. Please do not click any other buttons!");?>')" />
            <input id="ext_remove" name="ext_remove" type="submit" class="formbtn" value="<?=gettext("Remove Extension");?>" onclick="return confirm('<?=gettext("Do you really want to remove the extension from the system?");?>')" />
        </div>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_separator();?>
			<?php html_separator();?>
			<?php html_titleline(gettext("Extension")." ".gettext("Release Notes"));?>
			<tr>
                <td class="listt">
                    <div>
                        <textarea style="width: 98%;" id="content" name="content" class="listcontent" cols="1" rows="25" readonly="readonly"><?php unset($lines); exec("/bin/cat {$configuration['rootfolder']}/release_notes.txt", $lines); foreach ($lines as $line) { echo $line."\n"; }?></textarea>
                    </div>
                </td>
			</tr>
        </table>
        <?php include("formend.inc");?>
    </td></tr>
</table>
</form>
<?php include("fend.inc");?>
