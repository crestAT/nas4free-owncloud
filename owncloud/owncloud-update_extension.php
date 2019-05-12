<?php
/*
    owncloud-update_extension.php
    
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

$domain = strtolower(get_product_name());
bindtextdomain($domain, "/usr/local/share/locale-owncloud");
$config_file = "ext/owncloud/owncloud.conf";
if (($configuration = ext_load_config($config_file)) === false) {
    $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "owncloud.conf");
    $exitExtension = true;
} else $exitExtension = false; 

$pgtitle = array(gettext("Extensions"), $configuration['appname']." ".$configuration['version'], gettext("Maintenance"));

if (is_file("{$configuration['rootfolder']}/oneload")) { require_once("{$configuration['rootfolder']}/oneload"); }

$return_val = mwexec("fetch -o {$configuration['rootfolder']}/version_server.txt https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/version.txt", false);
if ($return_val == 0) { 
    $server_version = exec("cat {$configuration['rootfolder']}/version_server.txt"); 
    if ($server_version != $configuration['version']) { $savemsg .= sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"), $server_version, gettext("Update Extension")); }
    mwexec("fetch -o {$configuration['rootfolder']}/release_notes.txt https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/release_notes.txt", false);
}
else { $server_version = gettext("Unable to retrieve version from server!"); }

if (isset($_POST['ext_remove']) && $_POST['ext_remove']) {
// remove start/stop commands
	ext_remove_rc_commands("owncloud");
// remove links
    if (is_link("/usr/local/share/locale-owncloud")) unlink("/usr/local/share/locale-owncloud");
    if (is_link("/usr/local/www/owncloud-config.php")) unlink("/usr/local/www/owncloud-config.php");
    if (is_link("/usr/local/www/owncloud-update_extension.php")) unlink("/usr/local/www/owncloud-update_extension.php");
    if (is_link("/usr/local/www/ext/owncloud")) unlink("/usr/local/www/ext/owncloud");
    mwexec("rmdir -p /usr/local/www/ext");
	if (is_file(PHP_EXTENSION_DIR."/smbclient.so")) unlink(PHP_EXTENSION_DIR."/smbclient.so");
	if (is_file("/usr/local/etc/php/ext-20-smbclient.ini")) unlink("/usr/local/etc/php/ext-20-smbclient.ini");
    write_config();
	mwexec("rm /usr/local/etc/php/z-nextowncloud-php.ini && service websrv restart");
    mwexec("rm -R {$configuration['rootfolder']}");
	header("Location:index.php");
}

if (isset($_POST['ext_update']) && $_POST['ext_update']) {
// download installer & install
    $return_val = mwexec("fetch -vo {$configuration['rootfolder']}/owncloud-install.php 'https://raw.github.com/crestAT/nas4free-owncloud/master/owncloud/owncloud-install.php'", false);
    if ($return_val == 0) {
        require_once("{$configuration['rootfolder']}/owncloud-install.php"); 
        $version = exec("cat {$configuration['rootfolder']}/version.txt");
        $savemsg = sprintf(gettext("Update to version %s completed!"), $version);
        header("Refresh:8");;
    }
    else { $input_errors[] = sprintf(gettext("Download of installation file %s failed, installation aborted!"), "owncloud-install.php"); }
}

bindtextdomain($domain, "/usr/local/share/locale");                  // to get the right main menu language
include("fbegin.inc");
bindtextdomain($domain, "/usr/local/share/locale-owncloud"); 
?>
<form action="owncloud-update_extension.php" method="post" name="iform" id="iform" onsubmit="spinner()">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabinact"><a href="owncloud-config.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabact"><a href="owncloud-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
		</ul>
	</td></tr>
	<tr><td class="tabcont">
        <?php 
			if (!empty($input_errors)) { 
				print_input_errors($input_errors);
				if ($exitExtension) exit;
			}
		?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline(gettext("Extension Update"));?>
			<?php html_text("ext_version_current", gettext("Installed version"), $configuration['version']);?>
			<?php html_text("ext_version_server", gettext("Latest version"), $server_version);?>
			<?php html_separator();?>
        </table>
        <div id="update_remarks">
            <?php html_remark("note_remove", gettext("Note"),
				gettext("Removing the extension will delete all extension folders including the data folders from the system."));
			?>
            <br />
            <input id="ext_update" name="ext_update" type="submit" class="formbtn" value="<?=gettext("Update Extension");?>" 
				onclick="return confirm('<?=gettext("The selected operation will be completed. Please do not click any other buttons!");?>')" />
            <input id="ext_remove" name="ext_remove" type="submit" class="formbtn" value="<?=gettext("Remove Extension");?>" 
				onclick="return confirm('<?=gettext("Do you really want to remove the extension from the system?");?>')" />
        </div>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_separator();?>
			<?php html_separator();?>
			<?php html_titleline(gettext("Extension")." ".gettext("Release Notes"));?>
			<tr>
                <td class="listt">
                    <div>
                        <textarea id="content" name="content" class="listcontent" cols="1" rows="25" readonly="readonly"><?php unset($lines); exec("/bin/cat {$configuration['rootfolder']}/release_notes.txt", $lines); foreach ($lines as $line) { echo $line."\n"; }?></textarea>
                    </div>
                </td>
			</tr>
        </table>
        <?php include("formend.inc");?>
    </td></tr>
</table>
</form>
<?php include("fend.inc");?>
