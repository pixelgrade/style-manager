import { hexToHpluv, hpluvToRgb } from 'hsluv';
import chroma from 'chroma-js';

import contrastArray from "./contrast-array";

const defaultAttributes = {
  correctLightness: true,
  useSources: true,
  mode: 'lch',
  bezierInterpolation: false,
};

export const getPalettesFromColors = ( colorGroups, attributes = {}, simple = false ) => {
  const options = Object.assign( {}, defaultAttributes, attributes );
  const functionalColors = getFunctionalColors( colorGroups );
  const palettes = colorGroups.map( mapColorToPalette( options ) );
  const functionalPalettes = functionalColors.map( mapColorToPalette( options ) );
  const allPalettes = palettes.concat( functionalPalettes );

  return mapSanitizePalettes( allPalettes, options, simple );
}

const noop = palette => palette;

const mapSanitizePalettes = ( colors, attributes = {}, simple ) => {
  return colors.map( mapCorrectLightness( attributes ) )
               .map( mapUpdateProps )
               .map( mapUseSource( attributes ) )
               .map( mapAddTextColors )
               .map( mapAddSourceIndex( attributes ) )
               .map( mapMaybeSimplifyPalette( simple ) );
}

const mapAddTextColors = ( palette ) => {

  const textColors = palette.colors.slice( 9, 11 ).map( ( color, index ) => {
    return {
      ...color,
      value: getTextColor( color.value, 9 + index ),
    }
  } );

  palette.textColors = palette.colors.slice( 0, 1 ).concat( textColors );

  return palette;
}

const mapAddSourceIndex = ( attributes ) => {

  return ( palette, index, palettes ) => {
    const { source, colors } = palette;
    let sourceIndex = getSourceIndex( palette );

    // falback sourceIndex when the source isn't used in the palette
    if ( ! sourceIndex > -1 ) {
      sourceIndex = getBestPositionInPalette( source[0], colors.map( color => color.value ), attributes );
    }

    return {
      sourceIndex,
      ...palette
    };
  }
}

const mapColorToPalette = ( ( attributes ) => {

  return ( groupObject, index ) => {

    const colorObjects = groupObject.sources;
    const sources = colorObjects.map( colorObj => colorObj.value );
    const colors = createAutoPalette( sources, attributes );

    const { label, id } = colorObjects[0];

    return {
      id: id || ( index + 1 ),
      lightColorsCount: 5,
      label: label,
      source: sources,
      colors: colors,
    };
  }
} );

const mapMaybeSimplifyPalette = ( simple ) => {

  if ( ! simple ) {
    return noop;
  }

  return ( palette ) => {

    const { sourceIndex } = palette;
    const colors = palette.colors.slice();

    const WHITE_COUNT = 0;
    const LIGHT_COUNT = 3;
    const COLOR_COUNT = 6;
    const DARK_COUNT = 3;

    maybeFlatten( colors, 0, WHITE_COUNT, sourceIndex, WHITE_COUNT );
    maybeFlatten( colors, WHITE_COUNT, WHITE_COUNT + LIGHT_COUNT, sourceIndex, LIGHT_COUNT );
    maybeFlatten( colors, WHITE_COUNT + LIGHT_COUNT, WHITE_COUNT + COLOR_COUNT + DARK_COUNT, sourceIndex, COLOR_COUNT );
    maybeFlatten( colors, WHITE_COUNT + COLOR_COUNT + DARK_COUNT, 12, sourceIndex, DARK_COUNT );

    // When the sourceIndex is in the most saturate area of the palette (medium signal)
    // Move it to the 8th position so the accent color comes from the dark colors area (high signal)
    let newSourceIndex = Math.floor( WHITE_COUNT / 2 );

    if ( sourceIndex > WHITE_COUNT - 1 ) {
      newSourceIndex = WHITE_COUNT + Math.floor( LIGHT_COUNT / 2 );
    }

    if ( sourceIndex > WHITE_COUNT + LIGHT_COUNT - 1 ) {
      newSourceIndex = WHITE_COUNT + LIGHT_COUNT + Math.floor( COLOR_COUNT / 2 );
    }

    if ( sourceIndex > WHITE_COUNT + LIGHT_COUNT + COLOR_COUNT - 1 ) {
      newSourceIndex = WHITE_COUNT + LIGHT_COUNT + COLOR_COUNT + Math.floor( DARK_COUNT / 2 );;
    }

    return {
      ...palette,
      colors,
      lightColorsCount: WHITE_COUNT + LIGHT_COUNT,
      sourceIndex: newSourceIndex,
    }
  }
}

const maybeFlatten = ( colors, start, end, sourceIndex, count ) => {
  let colorIndex = Math.floor( ( end - start ) * 0.5 ) + start;

  if ( start <= sourceIndex && end > sourceIndex ) {
    colorIndex = sourceIndex;
  }

  count = typeof count !== "undefined" ? count : end - start;

  const newColors = colors.slice( start, end ).reduce( ( res, current, index, array ) => {
    const { isSource, ...color } = array[ colorIndex - start ];

    if ( colorIndex === sourceIndex && index === sourceIndex - start ) {
      color.isSource = true;
    }

    return res.concat( color );
  }, [] );

  while ( count > newColors.length ) {
    newColors.push( newColors[0] );
  }

  colors.splice(start, end - start, ...newColors);
}

const mapCorrectLightness = ( { correctLightness, mode } ) => {

  if ( ! correctLightness ) {
    return noop;
  }

  return ( palette ) => {
    palette.colors = palette.colors.map( ( color, index ) => {
      const luminance = contrastToLuminance( contrastArray[ index ] );
      return chroma( color ).luminance( luminance, 'rgb' ).hex();
    } );
    return palette;
  }
}

const mapUpdateProps = ( palette ) => {
  palette.colors = palette.colors.map( ( color, index ) => {
    return Object.assign( {}, {
      value: color,
    } )
  } );

  return palette;
}

const mapUseSource = ( attributes ) => {
  const { useSources } = attributes;

  if ( ! useSources ) {
    return noop;
  }

  return ( palette ) => {
    const { source } = palette;
    const position = getBestPositionInPalette( source[0], palette.colors.map( color => color.value ), attributes );

    palette.colors.splice( position, 1, {
      value: source[0],
      isSource: true
    } );

    return palette;
  }
}

const getSourceIndex = ( palette ) => {
  return palette.colors.findIndex( color => color.value === palette.source )
}

const getBestPositionInPalette = ( color, colors, attributes, byColorDistance ) => {
  let min = Number.MAX_SAFE_INTEGER;
  let pos = -1;

  for ( let i = 0; i < colors.length - 1; i++ ) {
    let distance;

    if ( !! byColorDistance ) {
      distance = chroma.distance( colors[i], color, 'rgb' );
    } else {
      distance = Math.abs( chroma( colors[i] ).luminance() - chroma( color ).luminance() );
    }

    if ( distance < min ) {
      min = distance;
      pos = i;
    }
  }

  return pos;
}

const getTextColor = ( hex, position ) => {
  const luminance = contrastToLuminance( contrastArray[ position ] );
  const hpluv = hexToHpluv( hex );
  const h = Math.min( Math.max( hpluv[0], 0), 360 );
  const p = Math.min( Math.max( hpluv[1], 0), 100 );
  const l = Math.min( Math.max( hpluv[2], 0), 100 );
  const rgb = hpluvToRgb( [h, p, l] ).map( x => x * 255 );

  return chroma( rgb ).luminance( luminance ).hex();
}

const contrastToLuminance = ( contrast ) => {
  return 1.05 / contrast - 0.05;
}

const createAutoPalette = ( colors, attributes = {} ) => {
  const { mode, bezierInterpolation } = attributes;
  const newColors = colors.slice();

  newColors.splice( 0, 0, '#FFFFFF' );
  newColors.push( '#000000' );
  newColors.sort( ( c1, c2 ) => {
    return ( chroma( c1 ).luminance() > chroma( c2 ).luminance() ) ? -1 : ( ( chroma( c1 ).luminance() < chroma( c2 ).luminance() ) ? 1 : 0 );
  } );

  if ( !! bezierInterpolation ) {
    return chroma.bezier( newColors ).scale().mode( mode ).correctLightness().colors( 12 );
  } else {
    return chroma.scale( newColors ).mode( mode ).correctLightness().colors( 12 );
  }
}

const blend = ( functionalColor, brandColor, ratio = 1 ) => {
  const l1 = chroma( functionalColor ).get( 'hsl.s' );
  const l2 = chroma( brandColor ).get( 'hsl.s' );
  const l3 = l1 * ( 1 - 0.8 * ratio ) + l2 * 0.8 * ratio;

  return chroma( functionalColor ).mix( brandColor, 0.1 * ratio ).set( 'hsl.s', l3 ).hex();
}

export const getFunctionalColors = ( colorGroups ) => {

  if ( ! colorGroups?.length || ! colorGroups[0]?.sources?.length ) {
    return [];
  }

  const color = colorGroups[0].sources[0].value;
  const blue = blend( '#2E72D2', color );
  const red = blend( '#D82C0D', color );
  const yellow = blend( '#FFCC00', color, 0.5 );
  const green = blend( '#00703c', color, 0.75 );

  return [
    { sources: [ { value: blue, label: 'Info', id: '_info' } ] },
    { sources: [ { value: red, label: 'Error', id: '_error' } ] },
    { sources: [ { value: yellow, label: 'Warning', id: '_warning' } ] },
    { sources: [ { value: green, label: 'Success', id: '_success' } ] },
  ];
}

