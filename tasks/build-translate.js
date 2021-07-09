var gulp = require('gulp'),
  cp = require( 'child_process' ),
  plugins = require('gulp-load-plugins')()

const gulpconfig = require('./gulpconfig.json');

var slug = gulpconfig.slug
var packageName = gulpconfig.packagename
var textdomain = gulpconfig.textdomain
var bugReport = gulpconfig.bugreport

// -----------------------------------------------------------------------------
// Replace the plugin's text domain with the actual text domain.
// -----------------------------------------------------------------------------
function pluginTextdomainReplace () {
  return gulp.src([
    '../build/' + slug + '/**/*.php',
    '../build/' + slug + '/**/*.js',
    '../build/' + slug + '/**/*.css',
    '../build/' + slug + '/**/*.pot'
  ])
    .pipe(plugins.replace(/__plugin_txtd/g, textdomain))
    .pipe(gulp.dest('../build/' + slug))
}

pluginTextdomainReplace.description = 'Replace the __plugin_txtd text-domain placeholder with the actual text-domain, in the build files.'
gulp.task('build:translate:replacetxtdomain', pluginTextdomainReplace)

function generatePotFile ( done ) {
  cp.execSync( 'wp i18n make-pot ../build/' + slug + '/ ../build/' + slug + '/languages/' + slug + '.pot --skip-js',
    {
      stdio: 'inherit' // Use the same console as the io for the child process.
    }
  );

  return done();
}

generatePotFile.description = 'Scan the build files and generate the .pot file.'
gulp.task('build:translate:generatepot', generatePotFile)

gulp.task('build:translate', gulp.series(
  'build:translate:replacetxtdomain',
  'build:translate:generatepot'
))
