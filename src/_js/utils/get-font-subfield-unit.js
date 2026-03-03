// This is a mirror logic of the server-side Utils\Fonts::getSubFieldUnit()
import _ from "lodash";

export const getFontSubfieldUnit = ( settingID, field ) => {
  let sm;
  try {
    sm = parent.styleManager || window.styleManager;
  } catch ( e ) {
    sm = window.styleManager;
  }

  const setting = sm?.config?.settings?.[ settingID ];
  const fieldConfig = setting?.fields?.[ field ];

  if ( typeof setting === 'undefined' || typeof fieldConfig === 'undefined' ) {
    // These fields don't have an unit, by default.
    if ( _.includes( [
      'font-family',
      'font-weight',
      'font-style',
      'line-height',
      'text-align',
      'text-transform',
      'text-decoration'
    ], field ) ) {
      return false
    }

    // The rest of the subfields have pixels as default units.
    return 'px'
  }

  if ( typeof fieldConfig.unit !== 'undefined' ) {
    // Make sure that we convert all falsy unit values to the boolean false.
    return _.includes( [
      '',
      'false',
      false
    ], fieldConfig.unit ) ? false : fieldConfig.unit
  }

  if ( typeof fieldConfig[ 3 ] !== 'undefined' ) {
    // Make sure that we convert all falsy unit values to the boolean false.
    return _.includes( [
      '',
      'false',
      false
    ], fieldConfig[ 3 ] ) ? false : fieldConfig[ 3 ]
  }

  return 'px'
};
