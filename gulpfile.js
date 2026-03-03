var gulp = require( 'gulp' );

require( './tasks/build-fix' );
require( './tasks/build-folder' );
require( './tasks/build-preflight' );
require( './tasks/build-translate' );
require( './tasks/build-zip' );
require( './tasks/composer' );
require( './tasks/google-fonts' );
require( './tasks/styles' );

gulp.task( 'zip', gulp.series( 'build:preflight', 'build:folder', 'build:fix', 'build:translate', 'build:zip' ) );
gulp.task( 'dev', gulp.parallel( 'watch:styles' ) );
