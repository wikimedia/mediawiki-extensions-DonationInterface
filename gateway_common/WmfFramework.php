<?php

if ( defined( 'MEDIAWIKI' ) ) {
	class_alias( 'WmfFramework_Mediawiki', 'WmfFramework', true );
} else {
	class_alias( 'WmfFramework_Drupal', 'WmfFramework', true );
}
