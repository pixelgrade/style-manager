// This is a mirror logic of the server-side Utils\Fonts::getSubFieldUnit()
import _ from "lodash";

export const getFontSubfieldUnit = ( settingID, field ) => {

  if ( typeof styleManager.config.settings[ settingID ] === 'undefined' || typeof styleManager.config.settings[ settingID ].fields[ field ] === 'undefined' ) {
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

  if ( typeof styleManager.config.settings[ settingID ].fields[ field ].unit !== 'undefined' ) {
    // Make sure that we convert all falsy unit values to the boolean false.
    return _.includes( [
      '',
      'false',
      false
    ], styleManager.config.settings[ settingID ].fields[ field ].unit ) ? false : styleManager.config.settings[ settingID ].fields[ field ].unit
  }

  if ( typeof styleManager.config.settings[ settingID ].fields[ field ][ 3 ] !== 'undefined' ) {
    // Make sure that we convert all falsy unit values to the boolean false.
    return _.includes( [
      '',
      'false',
      false
    ], styleManager.config.settings[ settingID ].fields[ field ][ 3 ] ) ? false : styleManager.config.settings[ settingID ].fields[ field ][ 3 ]
  }

  return 'px'
};
