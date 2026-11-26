const gulp = require( 'gulp' ),
  cp = require( 'child_process' ),
  fs = require( 'fs' ),
  path = require( 'path' ),
  commandExistsSync = require('command-exists').sync,
  log = require('fancy-log'),
  plugins = require( 'gulp-load-plugins' )();

function maybeFixBuildDirPermissions( done ) {
  cp.execSync( 'find ./../build -type d -exec chmod 755 {} \\;' );
  return done();
}

maybeFixBuildDirPermissions.description = 'Make sure that all directories in the build directory have 755 permissions.';
gulp.task( 'build:fix:dir-permissions', maybeFixBuildDirPermissions );

function maybeFixBuildFilePermissions( done ) {
  cp.execSync( 'find ./../build -type f -exec chmod 644 {} \\;' );
  return done();
}

maybeFixBuildFilePermissions.description = 'Make sure that all files in the build directory have 644 permissions.';
gulp.task( 'build:fix:file-permissions', maybeFixBuildFilePermissions );

function maybeFixIncorrectLineEndings( done ) {
  if (!commandExistsSync('dos2unix')) {
    log.error('Could not ensure that line endings are correct on the build files since you are missing the "dos2unix" utility! You should install it.')
    log.error('However, this is not a very big deal. The build task will continue.')
  } else {
    cp.execSync('find ./../build -type f -print0 | xargs -0 -n 1 -P 4 dos2unix');
  }

  return done();
}

maybeFixIncorrectLineEndings.description = 'Make sure that all line endings in the files in the build directory are UNIX line endings.';
gulp.task( 'build:fix:line-endings', maybeFixIncorrectLineEndings );

// Carbon Fields bundles its own Pimple. Apply the PHP 8.5 API replacements
// to the release copy until the bundled dependency includes them upstream.
function fixCarbonFieldsPimple( done ) {
  const { slug } = require( './gulpconfig.json' );
  const filename = path.resolve( '..', 'build', slug, 'vendor/htmlburger/carbon-fields/core/Pimple/Container.php' );
  const source = fs.readFileSync( filename, 'utf8' );
  const replacements = [
    [ '$this->factories->attach($callable);', '$this->factories->offsetSet($callable);' ],
    [ '$this->protected->attach($callable);', '$this->protected->offsetSet($callable);' ],
    [ '$this->factories->detach($factory);', '$this->factories->offsetUnset($factory);' ],
    [ '$this->factories->attach($extended);', '$this->factories->offsetSet($extended);' ],
  ];

  let patched = source;
  for ( const [ previous, replacement ] of replacements ) {
    const previousCount = source.split( previous ).length - 1;
    const replacementCount = source.split( replacement ).length - 1;
    if ( previousCount + replacementCount !== 1 ) {
      throw new Error( 'Review the Carbon Fields Pimple patch for this vendor version: ' + previous );
    }
    patched = patched.replace( previous, replacement );
  }

  if ( patched !== source ) {
    fs.writeFileSync( filename, patched );
  }
  done();
}

gulp.task( 'build:fix:carbon-fields-pimple', fixCarbonFieldsPimple );

gulp.task( 'build:fix', gulp.series(
  'build:fix:dir-permissions',
  'build:fix:file-permissions',
  'build:fix:line-endings',
  'build:fix:carbon-fields-pimple',
) );
