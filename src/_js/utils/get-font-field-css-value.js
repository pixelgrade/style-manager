// Mirror logic of server-side Utils\Fonts::getCSSValue()
import _ from "lodash";
import { getFontFamilyFallbackStack } from "./get-font-family-fallback-stack";
import { getFontSubfieldUnit } from "./get-font-subfield-unit";
import { sanitizeFontFamilyCSSValue } from './sanitize-font-family-css-value';

export const getFontFieldCSSValue = ( settingID, value ) => {

  const CSSValue = {};

  if ( typeof value.font_family !== 'undefined' && !_.includes( [ '', 'false', false ], value.font_family ) ) {
    CSSValue[ 'font-family' ] = value.font_family;
    // "Expand" the font family by appending the fallback stack, if any is available.
    // But only do this, if the value is not already a font stack!
    if ( CSSValue[ 'font-family' ].indexOf( ',' ) === - 1 ) {
      const fallbackStack = getFontFamilyFallbackStack( CSSValue[ 'font-family' ] );
      if ( fallbackStack.length ) {
        CSSValue[ 'font-family' ] += ',' + fallbackStack
      }
    }

    CSSValue[ 'font-family' ] = sanitizeFontFamilyCSSValue( CSSValue[ 'font-family' ] )
  }

  if ( typeof value.font_variant !== 'undefined' && !_.includes( [ '', 'false', false ], value.font_variant ) ) {
    let variant = value.font_variant;

    if ( _.isString( variant ) ) {
      // We may have a style in the variant; attempt to split.
      if ( variant.indexOf( 'italic' ) !== - 1 ) {
        CSSValue[ 'font-style' ] = 'italic';
        variant = variant.replace( 'italic', '' )
      } else if ( variant.indexOf( 'oblique' ) !== - 1 ) {
        CSSValue[ 'font-style' ] = 'oblique';
        variant = variant.replace( 'oblique', '' )
      }

      // If anything remained, then we have a font weight also.
      if ( variant !== '' ) {
        if ( variant === 'regular' || variant === 'normal' ) {
          variant = '400'
        }

        CSSValue[ 'font-weight' ] = variant
      }
    } else if ( _.isNumber( variant ) ) {
      CSSValue[ 'font-weight' ] = String( variant );
    }
  }

  if ( typeof value.font_size !== 'undefined' && !_.includes( [ '', 'false', false ], value.font_size ) ) {
    let fontSizeUnit = false;

    CSSValue[ 'font-size' ] = value.font_size;
    // If the value already contains a unit (is not numeric), go with that.
    if ( isNaN( value.font_size ) ) {
      // If we have a standardized value field (as array), use that.
      if ( typeof value.font_size.value !== 'undefined' ) {
        CSSValue[ 'font-size' ] = value.font_size.value;
        if ( typeof value.font_size.unit !== 'undefined' ) {
          fontSizeUnit = value.font_size.unit
        }
      } else {
        fontSizeUnit = getFontSubfieldUnit( settingID, 'font-size' )
      }
    } else {
      fontSizeUnit = getFontSubfieldUnit( settingID, 'font-size' )
    }

    if ( false !== fontSizeUnit ) {
      CSSValue[ 'font-size' ] += fontSizeUnit
    }
  }

  if ( typeof value.letter_spacing !== 'undefined' && !_.includes( [ '', 'false', false ], value.letter_spacing ) ) {
    let letterSpacingUnit = false;

    CSSValue[ 'letter-spacing' ] = value.letter_spacing;
    // If the value already contains a unit (is not numeric), go with that.
    if ( isNaN( value.letter_spacing ) ) {
      // If we have a standardized value field (as array), use that.
      if ( typeof value.letter_spacing.value !== 'undefined' ) {
        CSSValue[ 'letter-spacing' ] = value.letter_spacing.value;
        if ( typeof value.letter_spacing.unit !== 'undefined' ) {
          letterSpacingUnit = value.letter_spacing.unit
        }
      } else {
        letterSpacingUnit = getFontSubfieldUnit( settingID, 'letter-spacing' )
      }
    } else {
      letterSpacingUnit = getFontSubfieldUnit( settingID, 'letter-spacing' )
    }

    if ( false !== letterSpacingUnit ) {
      CSSValue[ 'letter-spacing' ] += letterSpacingUnit
    }
  }

  if ( typeof value.line_height !== 'undefined' && !_.includes( [ '', 'false', false ], value.line_height ) ) {
    let lineHeightUnit = false;

    CSSValue[ 'line-height' ] = value.line_height;
    // If the value already contains a unit (is not numeric), go with that.
    if ( isNaN( value.line_height ) ) {
      // If we have a standardized value field (as array), use that.
      if ( typeof value.line_height.value !== 'undefined' ) {
        CSSValue[ 'line-height' ] = value.line_height.value;
        if ( !!value.line_height.unit !== 'undefined' ) {
          lineHeightUnit = value.line_height.unit
        }
      } else {
        lineHeightUnit = getFontSubfieldUnit( settingID, 'line-height' )
      }
    } else {
      lineHeightUnit = getFontSubfieldUnit( settingID, 'line-height' )
    }

    if ( false !== lineHeightUnit ) {
      CSSValue[ 'line-height' ] += lineHeightUnit
    }
  }

  if ( typeof value.text_align !== 'undefined' && !_.includes( [ '', 'false', false ], value.text_align ) ) {
    CSSValue[ 'text-align' ] = value.text_align
  }

  if ( typeof value.text_transform !== 'undefined' && !_.includes( [ '', 'false', false ], value.text_transform ) ) {
    CSSValue[ 'text-transform' ] = value.text_transform
  }

  if ( typeof value.text_decoration !== 'undefined' && !_.includes( [ '', 'false', false ], value.text_decoration ) ) {
    CSSValue[ 'text-decoration' ] = value.text_decoration
  }

  return CSSValue
};
