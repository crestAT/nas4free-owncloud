<?php
/*
    owncloud-start.php 

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
/* /usr/local/bin/php-cgi -f /mnt/DATA/TEST/owncloud/owncloud-start.php */
require_once("config.inc");
$rootfolder = dirname(__FILE__);

if (is_link("/usr/local/share/locale-owncloud")) unlink("/usr/local/share/locale-owncloud");
if (is_link("/usr/local/www/owncloud-config.php")) unlink("/usr/local/www/owncloud-config.php");
if (is_link("/usr/local/www/owncloud-update_extension.php")) unlink("/usr/local/www/owncloud-update_extension.php");
if (is_link("/usr/local/www/ext/owncloud")) unlink("/usr/local/www/ext/owncloud");
mwexec("rmdir -p /usr/local/www/ext");
$return_val = 0; 
// create links to extension files
$return_val += mwexec("ln -sw {$rootfolder}/locale-owncloud /usr/local/share/", true);
$return_val += mwexec("ln -sw {$rootfolder}/owncloud-config.php /usr/local/www/owncloud-config.php", true);
$return_val += mwexec("ln -sw {$rootfolder}/owncloud-update_extension.php /usr/local/www/owncloud-update_extension.php", true);
$return_val += mwexec("mkdir -p /usr/local/www/ext");
$return_val += mwexec("ln -sw {$rootfolder}/owncloud /usr/local/www/ext/owncloud");
if ($return_val == 0) exec("logger owncloud-extension: gui loaded");
else exec("logger owncloud-extension: error during startup, link creation failed with return value = {$return_val}"); 
?>
