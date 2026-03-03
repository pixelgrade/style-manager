var gulp = require( 'gulp' ),
  fs = require( 'fs' ),
  cp = require( 'child_process' ),
  merge = require('merge-stream'),
  plugins = require( 'gulp-load-plugins' )();

gulp.task( 'composer:delete_lock_and_vendor', function () {
  fs.rmSync( 'composer.lock', { force: true } );
  fs.rmSync( 'vendor', { recursive: true, force: true } );
  return Promise.resolve();
} );

gulp.task( 'composer:delete_prefixed_vendor_libraries', function () {
  [
    'vendor/cedaro/wp-plugin',
    'vendor/pimple/pimple',
    'vendor/psr/container',
    'vendor/psr/log',
    'vendor/symfony/polyfill-mbstring',
    'vendor/symfony/polyfill-php72',
  ].forEach( function( path ) {
    fs.rmSync( path, { recursive: true, force: true } );
  } );

  return Promise.resolve();
} );

gulp.task( 'composer:create_vendor_prefixed_folder', function () {
  return gulp.src( '*.*', { read: false } )
    .pipe( gulp.dest( './vendor_prefixed' ) );
} );

gulp.task( 'composer:prefix', function ( cb ) {
  cp.exec( 'composer prefix-dependencies', function ( err, stdout, stderr ) {
    console.log( stdout );
    console.log( stderr );
    cb( err );
  } );
} );

/**
 * Update namespace of certain files that php-scoper can't patch.
 */
gulp.task( 'composer:prefix_outside_files', function () {
  return merge(

    gulp.src( [ 'vendor_prefixed/symfony/polyfill-mbstring/bootstrap.php' ], { allowEmpty: true } )
      .pipe( plugins.replace( /use Symfony\\Polyfill\\Mbstring/gm, 'use Pixelgrade\\StyleManager\\Vendor\\Symfony\\Polyfill\\Mbstring' ) )
      .pipe( gulp.dest( 'vendor_prefixed/symfony/polyfill-mbstring/' ) ),

    gulp.src( [ 'vendor_prefixed/symfony/polyfill-mbstring/Resources/mb_convert_variables.php8' ], { allowEmpty: true } )
      .pipe( plugins.replace( /use Symfony\\Polyfill\\Mbstring/gm, 'use Pixelgrade\\StyleManager\\Vendor\\Symfony\\Polyfill\\Mbstring' ) )
      .pipe( gulp.dest( 'vendor_prefixed/symfony/polyfill-mbstring/Resources/' ) ),

    gulp.src( [ 'vendor_prefixed/symfony/polyfill-php72/bootstrap.php' ], { allowEmpty: true } )
      .pipe( plugins.replace( /use Symfony\\Polyfill\\Php72/gm, 'use Pixelgrade\\StyleManager\\Vendor\\Symfony\\Polyfill\\Php72' ) )
      .pipe( gulp.dest( 'vendor_prefixed/symfony/polyfill-php72/' ) )
  );
} );
