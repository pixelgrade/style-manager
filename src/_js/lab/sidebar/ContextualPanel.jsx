/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { Button, ColorPicker, PanelBody, TextControl } from '@wordpress/components';

export const ContextualPanel = ( { state, readback, contextualPalette, onChange } ) => {
  const paletteJson = JSON.stringify( contextualPalette || {
    id: 'contextual-lab',
    label: 'Contextual Lab',
    source: state.contextual ? [ state.contextual ] : [],
    readback,
  }, null, 2 );

  const copyJson = () => {
    if ( window.navigator?.clipboard ) {
      window.navigator.clipboard.writeText( paletteJson );
    }
  };

  return (
    <PanelBody title="Contextual Palette" initialOpen={ state.palette === 'contextual-lab' }>
      <TextControl
        label="Source color"
        value={ state.contextual }
        placeholder="#3366ff"
        onChange={ ( contextual ) => onChange( { contextual, palette: 'contextual-lab' } ) }
      />
      <ColorPicker
        color={ state.contextual || '#3366ff' }
        onChange={ ( contextual ) => onChange( { contextual, palette: 'contextual-lab' } ) }
        enableAlpha={ false }
      />
      <dl className="sm-lab-readonly-list">
        { Object.entries( readback ).map( ( [ token, value ] ) => (
          <div key={ token }>
            <dt>{ token }</dt>
            <dd>
              <span className="sm-lab-admin-swatch" style={ { background: value } } />
              <code>{ value || '-' }</code>
            </dd>
          </div>
        ) ) }
      </dl>
      <Button variant="secondary" onClick={ copyJson }>Copy as palette JSON</Button>
    </PanelBody>
  );
};
