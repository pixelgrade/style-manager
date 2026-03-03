var gulp = require( 'gulp' ),
	fs = require( 'fs' ),
	path = require( 'path' ),
	spawnSync = require( 'child_process' ).spawnSync;

const gulpconfig = require('./gulpconfig.json');

var slug = gulpconfig.slug;

// -----------------------------------------------------------------------------
// Create the plugin installer archive and delete the build folder
// -----------------------------------------------------------------------------
function makeZip( done ) {
	var versionString = '';
  // get plugin version from the main plugin file
  var contents = fs.readFileSync("./" + slug + ".php", "utf8");

	// split it by lines
	var lines = contents.split(/[\r\n]/);

	function checkIfVersionLine(value, index, ar) {
		var myRegEx = /^[\s\*]*[Vv]ersion:/;
		if (myRegEx.test(value)) {
			return true;
		}
		return false;
	}

	// apply the filter
	var versionLine = lines.filter(checkIfVersionLine);

	try {
		versionString = versionLine[0].replace(/^[\s\*]*[Vv]ersion:/, '').trim();
		versionString = '-' + versionString.replace(/\./g, '-');
	} catch ( error ) {
		done( new Error( 'Unable to extract plugin version from main plugin file.' ) );
		return;
	}

	var rootDir = path.resolve( __dirname, '..' );
	var parentDir = path.resolve( rootDir, '..' );
	var buildDir = path.join( parentDir, 'build' );
	var zipFileName = slug + versionString + '.zip';

	try {
		if ( !fs.existsSync( buildDir ) ) {
			throw new Error( 'Build directory not found at ' + buildDir + '. Run build:folder first.' );
		}

		// Remove previous zip archives for this plugin before generating a new one.
		fs.readdirSync( parentDir )
			.filter( ( fileName ) => fileName.startsWith( slug ) && fileName.endsWith( '.zip' ) )
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
makeZip.description = 'Create the plugin installer archive and delete the build folder';
gulp.task( 'build:zip', makeZip );
