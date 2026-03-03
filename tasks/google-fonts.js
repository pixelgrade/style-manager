var gulp = require( 'gulp' ),
  fs = require( 'fs' );

// -----------------------------------------------------------------------------
// This is a helper function used to update the Google fonts list and recreate the php file holding it (includes/resources/google.fonts.php).
// -----------------------------------------------------------------------------
async function updatePhpGoogleFontsList(done) {
  const endpoint = 'https://www.googleapis.com/webfonts/v1/webfonts?key=AIzaSyAn2JiVvY0QL1T1430udIHS-nB3vBnjrf4';
  try {
    const response = await fetch( endpoint );

    if ( !response.ok ) {
      throw new Error( 'Google Fonts API request failed with status ' + response.status + '.' );
    }

    const body = await response.json();

    // First lets inspect the body and make sure it is something we can manage.
    if ( !Array.isArray( body.items ) ) {
      throw new Error( 'There was no valid `items` entry in the response from Google APIs. Please manually check `'+ endpoint +'`.' );
    }

    let fontsList = {};
    // Go through the items and clean it up to our liking.
    body.items.forEach( function(item){
      delete item.kind;
      delete item.version;
      delete item.lastModified;
      delete item.files;

      fontsList[item.family] = item;
    } );

    // Start the PHP code list.
    let php = ['<?php'];

    php.push( '// Returns an associative array with fonts.' );
    php.push( 'return json_decode( \'' + JSON.stringify( fontsList ) + '\', true );' );

    fs.writeFileSync('resources/google.fonts.php', php.join( '\r\n' ));

    done();
  } catch ( error ) {
    done( error );
  }
}
updatePhpGoogleFontsList.description = 'Fetch and recreate the PHP file holding the Google Fonts list.';
gulp.task( 'update-php-google-fonts-list', updatePhpGoogleFontsList  );
