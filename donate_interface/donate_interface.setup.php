testing<?php

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/donate_interface/donate_interface.php" );
EOT;
        exit( 1 );
}
 
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['donate_interface'] = $dir . 'donate_interface.php'; # Tell MediaWiki to load the extension body.
