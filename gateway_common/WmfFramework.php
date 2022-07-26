<?php

if ( !class_exists( 'WmfFramework' ) ) {
	if ( defined( 'MEDIAWIKI' ) ) {
		class_alias( WmfFramework_Mediawiki::class, 'WmfFramework', true );
	} else {
		require_once __DIR__ . '/Config.php';
		require_once __DIR__ . '/DrupalFakeMwConfig.php';
		class_alias( WmfFramework_Drupal::class, 'WmfFramework', true );
	}
}
