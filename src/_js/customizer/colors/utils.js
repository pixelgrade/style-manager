import contrastArray from "./components/builder/utils/contrast-array";
import { hexToHpluv, hpluvToRgb } from "hsluv";
import chroma from "chroma-js";
import { getSettingConfig } from "../global-service";

export const moveConnectedFields = ( oldSettings, from, to, ratio ) => {

  const settings = JSON.parse( JSON.stringify( oldSettings ) );

  if ( !! settings[from] && !! settings[to] ) {

    if ( ! settings[from].connected_fields ) {
      settings[from].connected_fields = [];
    }

    if ( ! settings[to].connected_fields ) {
      settings[to].connected_fields = [];
    }

    const oldFromConnectedFields = Object.values( settings[from]['connected_fields'] )
    const oldToConnectedFields = Object.values( settings[to]['connected_fields'] )
    const oldConnectedFields = oldToConnectedFields.concat( oldFromConnectedFields )
    const count = Math.round( ratio * oldConnectedFields.length )

    let newToConnectedFields = oldConnectedFields.slice( 0, count )
    const newFromConnectedFields = oldConnectedFields.slice( count )

    newToConnectedFields = Object.keys( newToConnectedFields ).map( function( key ) {
      return newToConnectedFields[key]
    } )
    newToConnectedFields = Object.keys( newToConnectedFields ).map( function( key ) {
      return newToConnectedFields[key]
    } )

    settings[to]['connected_fields'] = newToConnectedFields
    settings[from]['connected_fields'] = newFromConnectedFields
  }

  return settings
}

export const maybeFillPalettesArray = ( arr, minLength ) => {

  if ( Array.isArray( arr ) && !! arr.length ) {

    const userPalettes = arr.filter( palette => {
      const id = palette.id.toString();
      return id.indexOf( '_' ) !== 0;
    } );

    const userPalettesCount = userPalettes.length;

    if ( userPalettesCount < minLength ) {
      for ( let i = 0; i < minLength - userPalettesCount; i++ ) {
        const newPalette = JSON.parse( JSON.stringify( arr[ 0 ] ) );
        newPalette.id = userPalettesCount + i + 1;
        arr.splice( userPalettesCount + i, 0, newPalette );
      }
    }
  }
}

export const desaturateTextColor = ( hex ) => {
  const hpluv = hexToHpluv( hex );
  const h = Math.min( Math.max( hpluv[ 0 ], 0 ), 360 );
  const p = Math.min( Math.max( hpluv[ 1 ], 0 ), 100 );
  const l = Math.min( Math.max( hpluv[ 2 ], 0 ), 100 );
  const rgb = hpluvToRgb( [ h, p, l ] ).map( x => x * 255 );

  return chroma( rgb ).hex();
}

export const contrastToLuminance = ( contrast ) => {
  return 1.05 / contrast - 0.05;
}

export const getColorOptionsIDs = () => {
  return [
    'sm_color_grades_number',
    'sm_potential_color_contrast',
    'sm_color_grade_balancer',
    'sm_site_color_variation',
    'sm_elements_color_contrast',
    'sm_color_promotion_brand',
    'sm_color_promotion_white',
    'sm_color_promotion_black',
  ];
}

export const getColorOptionsDefaults = () => {
  const settingsIDs = getColorOptionsIDs();
  const defaults = {};

  settingsIDs.forEach( settingID => {
    const config = getSettingConfig( settingID );
    defaults[ settingID ] = config.default;
  } );

  return defaults;
}
