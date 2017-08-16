<?php
// Sadly, I didn't find any off-the-shelf yaml linters that didn't make me mad.
// Requires PHP 5.4

use Symfony\Component\Yaml\Parser;

function runForYamlFiles( $callback ) {
	$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/../' );
	$filter = new RecursiveCallbackFilterIterator( $directoryIterator, function ( $current, $key, $iterator ) {
		// Skip tests and vendor directories.
		if ( $current->getFilename() === 'tests'
			|| $current->getFilename() === 'vendor'
			|| $current->getFilename() === 'node_modules'
		) {
			return false;
		}

		// Recurse
		if ( $current->isDir() ) {
			return true;
		}

		// Match .yaml or .yml
		return preg_match( '/\.ya?ml$/', $current->getFilename() );
	} );

	$iterator = new RecursiveIteratorIterator( $filter );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$callback( $file->getPathname() );
		}
	}
}

function lintYamlFile( $path ) {
	$yamlParser = new Parser();
	try {
		$data = $yamlParser->parse( file_get_contents( $path ) );
	} catch ( Exception $ex ) {
		global $exitStatus;
		$exitStatus = -1;

		error_log( $path . ': ' . $ex->getMessage() );
	}
}

$exitStatus = 0;
require_once __DIR__ . '/../../vendor/autoload.php';
runForYamlFiles( 'lintYamlFile' );
exit( $exitStatus );
