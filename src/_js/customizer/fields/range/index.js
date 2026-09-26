import $ from 'jquery';

import { resolveRangeDisplayValue } from '../../../utils/range-display-value';

export const handleRangeFields = () => {

  const rangeControlSelectors = [
    `.accordion-section-content[id*="${ styleManager.config.options_name }"]`,
    '#sub-accordion-section-sm_color_palettes_section',
    '#sub-accordion-section-sm_color_usage_section',
    '#sub-accordion-section-sm_layout_section',
    '#sub-accordion-section-sm_fine_tune_color_palette_section',
    '#sub-accordion-section-sm_fine_tune_font_palette_section',
  ];

  const rangeControlSelector = rangeControlSelectors.join( ', ' );

  $( rangeControlSelector ).each( function ( i, container ) {
    const $rangeFields = $( container ).find( 'input[type="range"]' );

    // For each range input add a number field (for preview mainly - but it can also be used for input)
    $rangeFields.each( function( i, obj ) {
      const $range = $( obj );
      const settingID = $range.data( 'customize-setting-link' );

      // style-manager#215 (follow-up): while the setting is unset (PHP
      // rendered an empty `value` attribute), the browser's own native
      // <input type="range"> default-value fallback ((min+max)/2) shows a
      // width nothing applies (e.g. 260 for "Rail Base (Small)", when the
      // site actually renders 230 or a saved Small-only value). Snap the
      // slider to the PHP-derived `data-effective-default` (present only on
      // controls wired with one — see LayoutSection.php) BEFORE cloning it
      // into the number field, so both start in sync on the real rendered
      // value; a control with a real saved value (a non-empty `value`
      // attribute) is left untouched, and one with no effective default
      // keeps its previous (unchanged) midpoint rendering.
      const rawValueAttr = $range.attr( 'value' );
      if ( '' === rawValueAttr || undefined === rawValueAttr ) {
        const effectiveDefaultAttr = $range.attr( 'data-effective-default' );
        $range.val( resolveRangeDisplayValue(
          rawValueAttr,
          parseFloat( $range.attr( 'min' ) ),
          parseFloat( $range.attr( 'max' ) ),
          parseFloat( $range.attr( 'step' ) ) || 1,
          undefined !== effectiveDefaultAttr ? parseFloat( effectiveDefaultAttr ) : undefined
        ) );
      }

      const $number = $range.clone();

      $number.attr( 'type', 'text' ).attr( 'class', 'range-value' ).removeAttr( 'data-value_entry' );
      $number.data( 'source', $range );

      if ( $range.first().attr( 'id' ) ) {
        $number.attr( 'id', $range.first().attr( 'id' ) + '_number' );
      }

      $number.insertAfter( $range );

      wp.customize( settingID, setting => {
        setting.bind( newValue => {
          $number.val( newValue );
        } );
      } );

      // font options don't have a setting associated with every input
      if ( ! settingID ) {
        $range.on( 'input', ( event ) => {
          $number.val( event.target.value );
        } );
      }

      // When clicking outside the number field or on Enter.
      $number.on( 'blur keyup', onRangePreviewBlur );

    } );
  } );
};

function onRangePreviewBlur( event ) {
  const $number = $( event.target );
  const $range = $number.data( 'source' );

  if ( 'keyup' === event.type && event.keyCode !== 13 ) {
    return
  }

  if ( event.target.value === $range.val() ) {
    // Nothing to do if the values are identical.
    return;
  }

  if ( ! hasValidValue( $number ) ) {
    $number.val( $range.val() );
    shake( $number );
  } else {
    // Do not mark this trigger as being programmatically triggered by Style Manager since it is a result of a user input.
    $range.val( $number.val() ).trigger( 'change' );
  }
}

function hasValidValue( $input ) {
  const min = $input.attr( 'min' );
  const max = $input.attr( 'max' );
  const value = $input.val();

  if ( typeof min !== 'undefined' && parseFloat( min ) > parseFloat( value ) ) {
    return false
  }

  if ( typeof max !== 'undefined' && parseFloat( max ) < parseFloat( value ) ) {
    return false;
  }

  return true;
}

function shake( $field ) {
  $field.addClass( 'input-shake input-error' );
  $field.one( 'animationend', function() {
    $field.removeClass( 'input-shake input-error' )
  } )
}
