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
async function removeUnneededFiles() {
  const files_to_remove = [];
  const contents = fs.readFileSync( '.zipignore', 'utf8' );

  // Files that should not be present in build
  contents.split( /[\r\n]/ ).forEach( function( path ) {
    path = path.trim();

    // We will skip line starting with # since those are comments (as per the .gitignore standard).
    if ( path && !path.startsWith('#') ) {
      files_to_remove.push( '../build/' + slug + '/' + path );
    }
  } );

  return del.sync( files_to_remove, {force: true} );
}

removeUnneededFiles.description = 'Remove unneeded files and folders from the build folder';
gulp.task( 'build:remove-unneeded-files', removeUnneededFiles );

gulp.task( 'build:folder', gulp.series(
  'build:copy-folder',
  'build:remove-unneeded-files'
) );
