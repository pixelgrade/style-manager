/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { PalettePreviewList } from './PalettePreviewList.jsx';

export const PalettePanel = ( { palettes, state, contextualPalette, onChange } ) => (
  <PanelBody title="Active Runtime Context" initialOpen={ true }>
    <PalettePreviewList
      palettes={ palettes }
      activePalette={ state.palette }
      contextualPalette={ contextualPalette }
      onSelect={ ( palette ) => onChange( { palette } ) }
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
