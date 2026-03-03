#!/usr/bin/env node

const fs = require( 'fs' );
const path = require( 'path' );

const rootDir = path.resolve( __dirname, '..' );
const autoloadFilesPath = path.join( rootDir, 'vendor/composer/autoload_files.php' );
const installedJsonPath = path.join( rootDir, 'vendor/composer/installed.json' );

function fail( message ) {
	console.error( '[verify-release-autoload] ' + message );
	process.exit( 1 );
}

function readRequiredFile( filePath ) {
	if ( !fs.existsSync( filePath ) ) {
		fail( 'Missing required file: ' + path.relative( rootDir, filePath ) );
	}

	return fs.readFileSync( filePath, 'utf8' );
}

const autoloadFilesContent = readRequiredFile( autoloadFilesPath );
const installedJsonContent = readRequiredFile( installedJsonPath );

const forbiddenAutoloadPatterns = [
	'vendor/phpstan/',
	'vendor/rector/',
	'vendor/brain/',
	'vendor/mockery/',
	'vendor/phpunit/',
	'vendor/phpcsstandards/',
	'vendor/nikic/',
	'vendor/symfony/',
	'vendor/psr/',
	'vendor/pimple/',
	'vendor/cedaro/',
];

const matchedForbiddenAutoloadPatterns = forbiddenAutoloadPatterns.filter(
	( pattern ) => autoloadFilesContent.includes( pattern )
);

if ( matchedForbiddenAutoloadPatterns.length ) {
	fail(
		'Forbidden production autoload references found: ' +
		matchedForbiddenAutoloadPatterns.join( ', ' ) +
		'.'
	);
}

let installedData = null;
try {
	installedData = JSON.parse( installedJsonContent );
} catch ( error ) {
	fail( 'Invalid JSON in vendor/composer/installed.json.' );
}

const installedPackages = Array.isArray( installedData ) ? installedData : ( installedData.packages || [] );
const installedPackageNames = installedPackages
	.map( ( item ) => item && item.name )
	.filter( Boolean );

const allowedProductionPackages = [ 'htmlburger/carbon-fields' ];
const unexpectedPackages = installedPackageNames.filter(
	( packageName ) => !allowedProductionPackages.includes( packageName )
);

if ( unexpectedPackages.length ) {
	fail(
		'Unexpected production packages in installed.json: ' +
		unexpectedPackages.join( ', ' ) +
		'. Expected only: ' + allowedProductionPackages.join( ', ' ) + '.'
	);
}

console.log( '[verify-release-autoload] Production autoload integrity checks passed.' );
