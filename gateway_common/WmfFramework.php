<?php

if ( defined( 'MEDIAWIKI' ) ) {
	class_alias( 'WmfFramework_Mediawiki', 'WmfFramework' );
} else {
	class_alias( 'WmfFramework_Drupal', 'WmfFramework' );
}

