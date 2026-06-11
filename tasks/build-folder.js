const gulp = require( 'gulp' ),
  fs = require( 'fs' ),
  cp = require( 'child_process' ),
  del = require( 'del' );

const gulpconfig = require('./gulpconfig.json');

const slug = gulpconfig.slug;

// -----------------------------------------------------------------------------
// Copy plugin folder outside in a build folder.
// -----------------------------------------------------------------------------
function copyFolder() {
  fs.rmSync( './../build', { recursive: true, force: true } );
  fs.mkdirSync( './../build/' + slug, { recursive: true } );

  const command = [
    'rsync',
    '-a',
    '--delete',
    '--exclude', 'node_modules',
    '--exclude', 'tests',
    '--exclude', 'tasks',
    '--exclude', 'node-tasks',
    './',
    './../build/' + slug + '/',
  ];

  cp.execFileSync( command[0], command.slice(1), { stdio: 'inherit' } );

  return Promise.resolve();
}

copyFolder.description = 'Copy plugin production files to a separate build folder';
gulp.task( 'build:copy-folder', copyFolder );

// -----------------------------------------------------------------------------
// Remove unneeded files and folders from the build folder.
// -----------------------------------------------------------------------------

// Collect the paths listed across one or more .zipignore-style files and delete
// them from the build folder. Lines starting with `#` are comments.
function removeListedFiles( ignoreFiles ) {
  const files_to_remove = [];

  ignoreFiles.forEach( function( ignoreFile ) {
    const contents = fs.readFileSync( ignoreFile, 'utf8' );

    contents.split( /[\r\n]/ ).forEach( function( path ) {
      path = path.trim();

      if ( path && !path.startsWith( '#' ) ) {
        files_to_remove.push( '../build/' + slug + '/' + path );
      }
    } );
  } );

  return del.sync( files_to_remove, { force: true } );
}

async function removeUnneededFiles() {
  return removeListedFiles( [ '.zipignore' ] );
}

removeUnneededFiles.description = 'Remove unneeded files and folders from the build folder';
gulp.task( 'build:remove-unneeded-files', removeUnneededFiles );

// The WordPress.org build strips everything in .zipignore plus the
// commercial-distribution-only files in .zipignore-wporg (the WUpdates
// self-updater), which wp.org forbids.
async function removeUnneededFilesWporg() {
  return removeListedFiles( [ '.zipignore', '.zipignore-wporg' ] );
}

removeUnneededFilesWporg.description = 'Remove unneeded files plus commercial-only files for a WordPress.org build';
gulp.task( 'build:remove-unneeded-files:wporg', removeUnneededFilesWporg );

// -----------------------------------------------------------------------------
// Make the built main plugin file safe for the WordPress.org directory.
// -----------------------------------------------------------------------------
// Remove the `Update URI: false` header so WordPress.org can deliver updates.
// The header stays in source to protect existing commercial/WUpdates installs
// from slug collisions while the directory listing is being reinstated; only
// the wp.org build drops it.
function fixWporgPluginHeader( done ) {
  const mainFile = '../build/' + slug + '/' + slug + '.php';

  if ( !fs.existsSync( mainFile ) ) {
    done( new Error( 'Cannot find the main plugin file at ' + mainFile + '. Run build:folder first.' ) );
    return;
  }

  const original = fs.readFileSync( mainFile, 'utf8' );
  // Match the whole "Update URI: false" docblock line (optional leading
  // asterisk/whitespace) and drop it entirely, including its line break.
  const patched = original.replace( /^[ \t]*\*?[ \t]*Update URI:[ \t]*false[ \t]*\r?\n/im, '' );

  if ( patched === original ) {
    done( new Error( 'Expected to find an "Update URI: false" header to remove for the wp.org build, but none was present.' ) );
    return;
  }

  fs.writeFileSync( mainFile, patched );
  done();
}

fixWporgPluginHeader.description = 'Strip the "Update URI: false" header from the built plugin for WordPress.org';
gulp.task( 'build:fix-wporg-header', fixWporgPluginHeader );

gulp.task( 'build:folder', gulp.series(
  'build:copy-folder',
  'build:remove-unneeded-files'
) );

gulp.task( 'build:folder:wporg', gulp.series(
  'build:copy-folder',
  'build:remove-unneeded-files:wporg',
  'build:fix-wporg-header'
) );
