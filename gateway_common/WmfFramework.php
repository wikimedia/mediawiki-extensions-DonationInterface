<?php

if ( defined( 'MEDIAWIKI' ) ) {
	class_alias( WmfFramework_Mediawiki::class, 'WmfFramework', true );
} else {
	class_alias( WmfFramework_Drupal::class, 'WmfFramework', true );
}
