<?php
/* 
    owncloud-install.php
    
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
$version = "v0.0.1";      // extension version
$appname = "OwnCloud";    // extension name

require_once("config.inc");

$install_dir = dirname(__FILE__);                           // get directory where the installer script resides
$config_name = strtolower($appname);
$version_striped = str_replace(".", "", $version);

$arch = $g['arch'];
$platform = $g['platform'];
// no check necessary since the extension is for all archictectures/platforms/releases
//if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64" && $arch != "rpi" && $arch != "rpi2")) { echo "\f{$arch} is an unsupported architecture!\n"; exit(1);  }
//if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "\funsupported platform!\n";  exit(1); }

// install extension
global $input_errors;
global $savemsg;

// check FreeBSD release for fetch options >= 9.3
$release = explode("-", exec("uname -r"));
if ($release[0] >= 9.3) $verify_hostname = "--no-verify-hostname";
else $verify_hostname = "";

// fetch release archive
$return_val = mwexec("fetch {$verify_hostname} -vo {$install_dir}/master.zip 'https://github.com/crestAT/nas4free-{$config_name}/releases/download/{$version}/{$config_name}-{$version_striped}.zip'", true);
if ($return_val == 0) {
    $return_val = mwexec("tar -xf {$install_dir}/master.zip -C {$install_dir}/ --exclude='.git*' --strip-components 2", true);
    if ($return_val == 0) {
        exec("rm {$install_dir}/master.zip");
        exec("chmod -R 775 {$install_dir}");
        require_once("{$install_dir}/{$config_name}/json.inc");
        $config_file = "{$install_dir}/{$config_name}/{$config_name}.conf";
        if (is_file("{$install_dir}/version.txt")) { $file_version = exec("cat {$install_dir}/version.txt"); }
        else { $file_version = "n/a"; }
//        $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
    }
    else { 
        $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /"); 
        return;
    }
}
else { 
    $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip"); 
    return;
}

// install / update application on NAS4Free
if (($configuration = load_config($config_file)) === false) $configuration = array();             // new installation
    $configuration['appname'] = $appname;
    $configuration['version'] = exec("cat {$install_dir}/version.txt");
    $configuration['rootfolder'] = $install_dir;
    $configuration['postinit'] = "/usr/local/bin/php-cgi -f {$install_dir}/{$config_name}-start.php";

    if ( is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
        for ($i; $i < count($config['rc']['postinit']['cmd']);) {
            if (preg_match('/extended-gui/', $config['rc']['postinit']['cmd'][$i])) break; ++$i; }
    }
    $config['rc']['postinit']['cmd'][$i] = $config['extended-gui']['rootfolder']."extended-gui_start.php";

    if (is_array($config['rc']['postinit'] ) && is_array($config['rc']['postinit']['cmd'] ) ) {
        for ($i = 0; $i < count($config['rc']['postinit']['cmd']); $i++) {
            if (preg_match("/{$config_name}/", $config['rc']['postinit']['cmd'][$i])) break; }
    }
    $config['rc']['postinit']['cmd'][$i] = $configuration['postinit']; 
    save_config($config_file, $configuration);
    write_config();
    require_once("{$install_dir}/{$config_name}-start.php");
    $savemsg = gettext("Installation completed, use WebGUI | Extensions | ".$appname." to configure the application!");
?>
