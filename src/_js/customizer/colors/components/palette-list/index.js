import React, { useContext } from 'react';

import { getTextHex } from "../../utils";
import ConfigContext from "../../context";
import { normalizeCloudPresets } from './utils';
import { getPalettesFromColors } from "../builder";
import getRandomStripes from "./get-random-stripes";
import './style.scss';

const presets = normalizeCloudPresets( styleManager.colorPalettes.palettes );

const PresetsList = ( props ) => {

  const noop = () => {};
  const onChange = props.onChange || noop;

  return (
    <div className={ 'sm-presets-list' }>
      {
        presets.map( preset => {
          return (
            <PaletteListItem preset={ preset } key={ preset.uid } active={ preset.uid === props.active } onChange={ onChange } />
          );
        } )
      }
    </div>
  )
}

const PaletteListItem = ( props ) => {

  const { preset, active } = props;

  const noop = () => {};
  const onChange = props.onChange || noop;

  return (
    <div className={ `sm-presets-list__item` } onClick={ () => { onChange( preset ) } }>
      <PresetPreview { ...preset } active={ active } />
    </div>
  );
}

export const PresetPreview = ( props ) => {

  const { options } = useContext( ConfigContext );
  const { config, quote, image, active } = props;

  const palettes = getPalettesFromColors( config, options ).filter( palette => {
    const id = `${ palette.id }`;
    return id.charAt( 0 ) !== '_';
  } );

  const sources = palettes.reduce( ( acc, palette ) => acc.concat( palette.source ), [] );
  const colors = palettes.reduce( ( acc, palette ) => acc.concat( palette.colors ), [] ).slice( 0, 5 );

  colors.sort( ( c1, c2 ) => {
    let min1 = 21;
    let min2 = 21;
    sources.forEach( source => {
      const contrast1 = chroma.contrast( source, c1.value );
      const contrast2 = chroma.contrast( source, c2.value );
      min1 = contrast1 < min1 ? contrast1 : min1;
      min2 = contrast2 < min2 ? contrast2 : min2;
    } );
    return c1 - c2;
  } );

  const stripes = getRandomStripes();

  const filledStripes = stripes.map( ( stripe, index ) => {
    const random = Math.floor( Math.random() * colors.length );
    const color = index > colors.length - 1 ? colors[ random ].value : colors[ index ].value;
    return { ...stripe, color }
  } );

  const textColor = getTextHex( palettes[0], colors[0], options );

  return (
    <div className={ `sm-presets-preview ${ active ? 'sm-presets-preview--active' : '' }` } style={ { backgroundImage: `url(${ image })` } }>
      { quote && <div className="sm-presets-preview__quote" style={ { color: textColor } }>{ quote }</div> }
      <div className="sm-presets-preview__stripes">
        { filledStripes.map( ( stripe, index ) => {
          return (
            <div key={ index } className={ `sm-presets-preview__stripe sm-presets-preview__stripe-w${ stripe.width } sm-presets-preview__stripe-p${ stripe.pos }` }>
              <div className="sm-presets-preview__pixel" style={ { color: stripe.color } } />
            </div>
          );
        } ) }
      </div>
    </div>
  )
}

export default PresetsList;
