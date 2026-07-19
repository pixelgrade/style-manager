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
    '--exclude', '.worktrees',
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

async function removeUnneededFilesWporg() {
  return removeListedFiles( [ '.zipignore' ] );
}

removeUnneededFilesWporg.description = 'Remove unneeded files for a WordPress.org build';
gulp.task( 'build:remove-unneeded-files:wporg', removeUnneededFilesWporg );

gulp.task( 'build:folder', gulp.series(
  'build:copy-folder',
  'build:remove-unneeded-files'
) );

gulp.task( 'build:folder:wporg', gulp.series(
  'build:copy-folder',
  'build:remove-unneeded-files:wporg'
) );
