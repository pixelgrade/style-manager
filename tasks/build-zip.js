var gulp = require( 'gulp' ),
	fs = require( 'fs' ),
	path = require( 'path' ),
	spawnSync = require( 'child_process' ).spawnSync;

const gulpconfig = require('./gulpconfig.json');

var slug = gulpconfig.slug;

// Extract the plugin version (e.g. "2-2-13") from the main plugin file header.
function getVersionString( done ) {
	var contents = fs.readFileSync( './' + slug + '.php', 'utf8' );
	var lines = contents.split( /[\r\n]/ );

	function checkIfVersionLine( value ) {
		return /^[\s\*]*[Vv]ersion:/.test( value );
	}

	var versionLine = lines.filter( checkIfVersionLine );

	try {
		var versionString = versionLine[0].replace( /^[\s\*]*[Vv]ersion:/, '' ).trim();
		return '-' + versionString.replace( /\./g, '-' );
	} catch ( error ) {
		done( new Error( 'Unable to extract plugin version from main plugin file.' ) );
		return null;
	}
}

// -----------------------------------------------------------------------------
// Create the WordPress.org plugin installer archive and delete the build folder.
// -----------------------------------------------------------------------------
function makeZip( done ) {
	var versionString = getVersionString( done );
	if ( versionString === null ) {
		return;
	}

	var rootDir = path.resolve( __dirname, '..' );
	var parentDir = path.resolve( rootDir, '..' );
	var buildDir = path.join( parentDir, 'build' );
	var zipPrefix = slug + '-wporg';
	var zipFileName = zipPrefix + versionString + '.zip';

	try {
		if ( !fs.existsSync( buildDir ) ) {
			throw new Error( 'Build directory not found at ' + buildDir + '. Run build:folder first.' );
		}

		// Remove previous archives for this target before generating a new one.
		fs.readdirSync( parentDir )
			.filter( fileName => fileName.startsWith( zipPrefix ) && fileName.endsWith( '.zip' ) )
			.forEach( ( fileName ) => fs.rmSync( path.join( parentDir, fileName ), { force: true } ) );

		var zipResult = spawnSync(
			'zip',
			[ '-r', '-X', path.join( '..', zipFileName ), '.' ],
			{ cwd: buildDir, stdio: 'inherit' }
		);

		if ( zipResult.status !== 0 ) {
			throw new Error( 'zip command failed with exit code ' + zipResult.status + '.' );
		}

		fs.rmSync( buildDir, { recursive: true, force: true } );
		done();
	} catch ( error ) {
		done( error );
	}
}

makeZip.description = 'Create the WordPress.org plugin archive and delete the build folder';
gulp.task( 'build:zip', makeZip );
gulp.task( 'build:zip:wporg', makeZip );
