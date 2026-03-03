var gulp = require( 'gulp' ),
  fs = require( 'fs' ),
  path = require( 'path' ),
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
    'vendor/instituteweb/composer-scripts',
  ].forEach( function( pathToDelete ) {
    fs.rmSync( pathToDelete, { recursive: true, force: true } );
  } );

  var installedJsonPath = path.resolve( 'vendor/composer/installed.json' );
  var allowedProductionPackages = [ 'htmlburger/carbon-fields' ];

  if ( fs.existsSync( installedJsonPath ) ) {
    var installedData = JSON.parse( fs.readFileSync( installedJsonPath, 'utf8' ) );

    if ( Array.isArray( installedData ) ) {
      installedData = installedData.filter( function( entry ) {
        return entry && allowedProductionPackages.includes( entry.name );
      } );
    } else {
      installedData.packages = ( installedData.packages || [] ).filter( function( entry ) {
        return entry && allowedProductionPackages.includes( entry.name );
      } );

      if ( Array.isArray( installedData[ 'packages-dev' ] ) ) {
        installedData[ 'packages-dev' ] = [];
      }
    }

    fs.writeFileSync( installedJsonPath, JSON.stringify( installedData, null, 2 ) + '\n' );
  }

  var vendorRootPath = path.resolve( 'vendor' );
  var allowedVendorNamespaces = [ 'composer', 'htmlburger' ];

  if ( fs.existsSync( vendorRootPath ) ) {
    fs.readdirSync( vendorRootPath, { withFileTypes: true } ).forEach( function( entry ) {
      if ( !entry.isDirectory() ) {
        return;
      }

      if ( allowedVendorNamespaces.includes( entry.name ) ) {
        return;
      }

      fs.rmSync( path.join( vendorRootPath, entry.name ), { recursive: true, force: true } );
    } );
  }

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
      .pipe( gulp.dest( 'vendor_prefixed/symfony/polyfill-mbstring/Resources/' ) )
  );
} );
