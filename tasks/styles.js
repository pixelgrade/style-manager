const gulp = require( 'gulp' ),
  fs = require( 'fs' ),
  path = require( 'path' ),
  dartSass = require( 'sass' ),
	rtlcss = require( 'gulp-rtlcss' ),
	rename = require( 'gulp-rename' );

function getScssEntries( directoryPath, entries = [] ) {
	const entryList = fs.readdirSync( directoryPath, { withFileTypes: true } );

	entryList.forEach( ( entry ) => {
		const fullPath = path.join( directoryPath, entry.name );

		if ( entry.isDirectory() ) {
			getScssEntries( fullPath, entries );
			return;
		}

		if ( path.extname( entry.name ) === '.scss' && !entry.name.startsWith( '_' ) ) {
			entries.push( fullPath );
		}
	} );

	return entries;
}

function compileStyles( cb ) {
	const sourceRoot = path.resolve( './src/_scss' );
	const destinationRoot = path.resolve( './dist/css' );
	const scssEntries = getScssEntries( sourceRoot );

	try {
		scssEntries.forEach( ( entryPath ) => {
			const relativePath = path.relative( sourceRoot, entryPath ).replace( /\.scss$/i, '.css' );
			const destinationPath = path.join( destinationRoot, relativePath );
			const compiled = dartSass.compile( entryPath, { style: 'compressed', loadPaths: [ sourceRoot ] } );

			fs.mkdirSync( path.dirname( destinationPath ), { recursive: true } );
			fs.writeFileSync( destinationPath, compiled.css.replace( /^@charset "UTF-8";\n/gm, '' ) );
		} );

		cb();
	} catch ( error ) {
		cb( error );
	}
}

function stylesRTL( cb ) {
	return gulp.src( [ './dist/css/**/*.css', '!./dist/css/**/*-rtl.css', './dist/js/**/*.css', '!./dist/js/**/*-rtl.css' ], { base: './' } )
	           .pipe( rtlcss() )
	           .pipe( rename( function( path ) { path.basename += "-rtl"; } ) )
	           .pipe( gulp.dest( '.' ) );
}

stylesRTL.description = 'Generate style-rtl.css file based on style.css';

function watch( cb ) {
	gulp.watch( [ './src/_scss/**/*.scss' ], compile );
}

const compile = gulp.series( compileStyles, stylesRTL );

gulp.task( 'compile:styles', compile );
gulp.task( 'watch:styles', gulp.series( compile, watch ) );
