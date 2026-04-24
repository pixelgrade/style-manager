/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { PanelBody } from '@wordpress/components';

const renderColorValue = ( label, value ) => (
  <div key={ label }>
    <dt>{ label }</dt>
    <dd>
      <span className="sm-lab-admin-swatch" style={ { background: value } } />
      <code>{ value || '-' }</code>
    </dd>
  </div>
);

export const RuntimeInspectorPanel = ( { state, readback } ) => {
  const contextual = readback.contextualReadout || {};

  return (
    <PanelBody title="Inspect Resolved Runtime" initialOpen={ false }>
      <dl className="sm-lab-readonly-list sm-lab-readonly-list--compact">
        <div>
          <dt>Palette</dt>
          <dd><code>{ state.palette }</code></dd>
        </div>
        <div>
          <dt>Variation</dt>
          <dd><code>{ state.variation }</code></dd>
        </div>
        <div>
          <dt>Dark</dt>
          <dd><code>{ state.dark ? 'on' : 'off' }</code></dd>
        </div>
        <div>
          <dt>Shifted</dt>
          <dd><code>{ state.shifted ? 'on' : 'off' }</code></dd>
        </div>
        <div>
          <dt>Contextual source</dt>
          <dd><code>{ state.contextual || 'off' }</code></dd>
        </div>
      </dl>
      <p className="sm-lab-inspector-heading">Body roles</p>
      <dl className="sm-lab-readonly-list">
        { Object.entries( readback.colors ).map( ( [ token, value ] ) => renderColorValue( token, value ) ) }
      </dl>
      <p className="sm-lab-inspector-heading">Scoped contextual roles</p>
      <dl className="sm-lab-readonly-list">
        { [ 'surface', 'accent', 'text' ].map( ( token ) => renderColorValue( token, contextual[ token ] || '' ) ) }
        <div>
          <dt>Accent contrast</dt>
          <dd><code>{ contextual.accentRatio || '-' }</code></dd>
        </div>
        <div>
          <dt>Text contrast</dt>
          <dd><code>{ contextual.textRatio || '-' }</code></dd>
        </div>
      </dl>
    </PanelBody>
  );
};
