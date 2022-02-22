import $ from 'jquery';

export const initializeFontPalettes = () => {

  $( '.js-font-palette' ).each( function( i, obj ) {
    const $paletteSet = $( obj );
    const $labels = $paletteSet.find( 'label' );

    $labels.on( 'click', function( event ) {
      const $label = $( event.target );
      const forID = $label.attr( 'for' );
      const $input = $( `#${ forID }` );
      const fontsLogic = $input.data( 'fonts_logic' );

      applyFontPalette( fontsLogic );
    } );
  } );
};

const applyFontPalette = ( fontsLogic ) => {
  $.each( fontsLogic, ( settingID, config ) => {
    wp.customize( settingID, setting => {
      setting.set( config );
    } );
  } );
};
