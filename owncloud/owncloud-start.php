<?php
/*
    owncloud-start.php 

    Copyright (c) 2015 - 2018 Andreas Schmidhuber
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

$return_val = 0; 
// create links to extension files
$return_val += mwexec("ln -sf {$rootfolder}/locale-owncloud /usr/local/share/", true);
$return_val += mwexec("ln -sf {$rootfolder}/owncloud-config.php /usr/local/www/owncloud-config.php", true);
$return_val += mwexec("ln -sf {$rootfolder}/owncloud-update_extension.php /usr/local/www/owncloud-update_extension.php", true);
$return_val += mwexec("mkdir -p /usr/local/www/ext", true);
$return_val += mwexec("ln -sf {$rootfolder}/owncloud /usr/local/www/ext/owncloud", true);
// use z-... prefix to override system defaults => opcache.max_accelerated_files=10000 and opcache.revalidate_freq=1
$return_val += mwexec("cp {$rootfolder}/nextowncloud-php.ini /usr/local/etc/php/", true);
$return_val += mwexec("echo upload_tmp_dir={$config['websrv']['uploaddir']} >> /usr/local/etc/php/nextowncloud-php.ini && service websrv restart", true);
// required for updater to work
if (!is_file("/usr/local/share/certs/ca-root-nss.crt")) {
	$return_val += mwexec("mkdir -p /usr/local/share/certs", true);
	$return_val += mwexec("ln -sf /usr/local/etc/ssl/cert.pem /usr/local/share/certs/ca-root-nss.crt", true);
}
// required for external storage inclusion -> PHP-smbclient
$return_val += mwexec("mkdir -p /usr/local/lib/php/extensions/no-debug-non-zts-20170718", true);
$return_val += mwexec("cp {$rootfolder}/bin/smbclient.so /usr/local/lib/php/extensions/no-debug-non-zts-20170718/smbclient.so", true);
$return_val += mwexec("cp {$rootfolder}/bin/ext-20-smbclient.ini /usr/local/etc/php/ext-20-smbclient.ini", true);

// check for product name and eventually rename translation files for new product name (XigmaNAS)
$domain = strtolower(get_product_name());
if ($domain <> "nas4free") {
	$return_val += mwexec("find {$rootfolder}/locale-owncloud -name nas4free.mo -execdir mv nas4free.mo {$domain}.mo \;", true);
}

if ($return_val == 0) mwexec("logger nextowncloud-extension: GUI loaded");
else mwexec("logger nextowncloud-extension: error(s) during startup, failed with return value = {$return_val}"); 
?>
