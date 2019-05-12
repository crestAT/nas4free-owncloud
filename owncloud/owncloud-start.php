<?php
/*
    owncloud-start.php 

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
require_once("config.inc");
$rootfolder = dirname(__FILE__);
require_once("{$rootfolder}/owncloud/extension-lib.inc");

unlink_if_exists("/usr/local/www/ext/owncloud");	// prevent nested symlinks
$return_val = 0; 
// required for external storage inclusion -> PHP-smbclient
$pkgName = "smbclient";
$pkgFileNameNeeded = "php".PHP_MAJOR_VERSION.PHP_MINOR_VERSION."-pecl-{$pkgName}";										// needed file PHP version based
if (($manifest = ext_load_config("{$rootfolder}/bin/+MANIFEST")) !== false) $pkgManifestFileName = $manifest['name'];	// currently installed pkg
if ($pkgFileNameNeeded != $pkgManifestFileName) {																		// if manifest not exists or PHP version changed ...
	$pkgNeeded = exec("pkg search {$pkgName} | awk '/{$pkgFileNameNeeded}/ {print $1}'", $execOutput, $return_val);		// retrieve available packages
	$pkgFile = "{$rootfolder}/bin/All/{$pkgNeeded}.txz";																// create package file name
	if (!is_file($pkgFile)) exec("pkg fetch -y -o {$rootfolder}/bin {$pkgNeeded}", $execOutput, $return_val);			// fetch necessary package
	$return_val += mwexec("LC_ALL=en_US.UTF-8 tar -xzf {$pkgFile} -C {$rootfolder}/bin", true);							// extract package
	$manifest = ext_load_config("{$rootfolder}/bin/+MANIFEST");
}
$return_val += mwexec("mkdir -p ".PHP_EXTENSION_DIR, true);																// create dir if not exist
foreach ($manifest['files'] as $mFKey => $mFValue) {
	if (strpos($mFKey, "{$pkgName}.so") > 0) {																			// get lib path for copying
		$libPath = "{$rootfolder}/bin{$mFKey}";
		$return_val += mwexec("cp {$libPath} ".PHP_EXTENSION_DIR, true);
	}
	if (strpos($mFKey, "{$pkgName}.ini") > 0) {																			// get ini path for copying
		$libPath = "{$rootfolder}/bin{$mFKey}";
		$return_val += mwexec("cp {$libPath} {$mFKey}", true);
	}
}
// create links to extension files
$return_val += mwexec("ln -sf {$rootfolder}/locale-owncloud /usr/local/share/", true);
$return_val += mwexec("ln -sf {$rootfolder}/owncloud-config.php /usr/local/www/owncloud-config.php", true);
$return_val += mwexec("ln -sf {$rootfolder}/owncloud-update_extension.php /usr/local/www/owncloud-update_extension.php", true);
$return_val += mwexec("mkdir -p /usr/local/www/ext", true);
$return_val += mwexec("ln -sf {$rootfolder}/owncloud /usr/local/www/ext/owncloud", true);
// override system defaults => opcache.max_accelerated_files=10000 and opcache.revalidate_freq=1 etc
$return_val += mwexec("cp {$rootfolder}/z-nextowncloud-php.ini /usr/local/etc/php/", true);
$return_val += mwexec("echo upload_tmp_dir={$config['websrv']['uploaddir']} >> /usr/local/etc/php/z-nextowncloud-php.ini", true);

// restart webserver -> REQUIRED FOR ALL PHP RELATED CHANGES!!!
$return_val += mwexec("service websrv restart", true);

// required for updater to work
if (!is_file("/usr/local/share/certs/ca-root-nss.crt")) {
	$return_val += mwexec("mkdir -p /usr/local/share/certs", true);
	$return_val += mwexec("ln -sf /usr/local/etc/ssl/cert.pem /usr/local/share/certs/ca-root-nss.crt", true);
}

// check for product name and eventually rename translation files for new product name (XigmaNAS)
$domain = strtolower(get_product_name());
if ($domain <> "nas4free") {
	$return_val += mwexec("find {$rootfolder}/locale-owncloud -name nas4free.mo -execdir mv nas4free.mo {$domain}.mo \;", true);
}

if ($return_val == 0) mwexec("logger nextowncloud-extension: GUI loaded");
else mwexec("logger nextowncloud-extension: error(s) during startup, failed with return value = {$return_val}"); 
?>
