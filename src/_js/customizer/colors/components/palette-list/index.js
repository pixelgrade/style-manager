import React from 'react';
import { normalizeCloudPresets } from './utils';
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
  const { textColor, stripes, quote, image, active } = props;

  return (
    <div className={ `sm-presets-preview ${ active ? 'sm-presets-preview--active' : '' }` } style={ { backgroundImage: `url(${ image })` } }>
      { quote && <div className="sm-presets-preview__quote" style={ { color: textColor } }>{ quote }</div> }
      <div className="sm-presets-preview__stripes">
        { stripes.map( ( stripe, index ) => {
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
