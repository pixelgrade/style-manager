/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { PanelBody, RangeControl, SelectControl, ToggleControl } from '@wordpress/components';

const getPaletteOptions = ( palettes ) => [
  ...palettes.map( ( palette ) => ( {
    label: palette.label || palette.name || palette.id,
    value: String( palette.id ),
  } ) ),
  {
    label: 'Contextual',
    value: 'contextual-lab',
  },
];

export const PalettePanel = ( { palettes, state, onChange } ) => (
  <PanelBody title="Palette" initialOpen={ true }>
    <SelectControl
      label="Palette"
      value={ state.palette }
      options={ getPaletteOptions( palettes ) }
      onChange={ ( palette ) => onChange( { palette } ) }
    />
    <RangeControl
      label="Variation"
      value={ state.variation }
      min={ 1 }
      max={ 12 }
      onChange={ ( variation ) => onChange( { variation } ) }
    />
    <ToggleControl
      label="Dark mode"
      checked={ state.dark }
      onChange={ ( dark ) => onChange( { dark } ) }
    />
    <ToggleControl
      label="Shifted"
      checked={ state.shifted }
      onChange={ ( shifted ) => onChange( { shifted } ) }
    />
  </PanelBody>
);
