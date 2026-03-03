const gulp = require( 'gulp' );
const { sync: commandExistsSync } = require( 'command-exists' );
const { execFileSync } = require( 'child_process' );

function parseVersion( value ) {
	return value
		.trim()
		.replace( /^[^\d]*/, '' )
		.split( /[^\d]+/ )
		filter( Boolean )
		.slice( 0, 3 )
		map( ( part ) => Number( part ) || 0 );
}

function isLessThanVersion( left, right ) {
	for ( let index = 0; index < right.length; index++ ) {
		const leftValue = left[ index ] || 0;
		const rightValue = right[ index ] || 0;

		if ( leftValue < rightValue ) {
			return true;
		}

		if ( leftValue > rightValue ) {
			return false;
		}
	}

	return false;
}

function ensureBuildTooling( done ) {
	const requiredCommands = [ 'node', 'php', 'composer', 'rsync', 'wp', 'zip' ];
	const missingCommands = requiredCommands.filter( ( command ) => !commandExistsSync( command ) );

	if ( missingCommands.length ) {
		done( new Error( 'Missing required build tools in PATH: ' + missingCommands.join( ', ' ) + '.' ) );
		return;
	}

	const nodeVersion = parseVersion( process.versions.node );
	if ( isLessThanVersion( nodeVersion, [ 22, 0, 0 ] ) ) {
		done( new Error( 'Node.js 22+ is required. Current version: ' + process.versions.node + '.' ) );
		return;
	}

	const phpVersion = parseVersion( execFileSync( 'php', [ '-r', 'echo PHP_VERSION;' ], { encoding: 'utf8' } ) );
	if ( isLessThanVersion( phpVersion, [ 8, 1, 0 ] ) ) {
		done( new Error( 'PHP 8.1+ is required. Current version: ' + phpVersion.join( '.' ) + '.' ) );
		return;
	}

	const composerVersionOutput = execFileSync( 'composer', [ '--version' ], { encoding: 'utf8' } );
	const composerVersion = parseVersion( composerVersionOutput );
	if ( isLessThanVersion( composerVersion, [ 2, 2, 0 ] ) ) {
		done( new Error( 'Composer 2.2+ is required. Current version: ' + composerVersion.join( '.' ) + '.' ) );
		return;
	}

	done();
}

ensureBuildTooling.description = 'Fail fast when required build tools are missing.';
gulp.task( 'build:preflight', ensureBuildTooling );
