/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { PanelBody, RadioControl } from '@wordpress/components';
import { getSignalVariations } from '../state.js';

const signalOptions = [ 0, 1, 2, 3 ].map( ( value ) => ( {
  label: String( value ),
  value: String( value ),
} ) );

const parentOptions = [ 1, 5, 9 ].map( ( value ) => ( {
  label: String( value ),
  value: String( value ),
} ) );

export const ColorSignalPanel = ( { state, onChange } ) => (
  <PanelBody title="Color Signal" initialOpen={ false }>
    <RadioControl
      label="Parent variation"
      selected={ String( state.parentVariation ) }
      options={ parentOptions }
      onChange={ ( parentVariation ) => onChange( { parentVariation } ) }
    />
    <RadioControl
      label="Highlight signal"
      selected={ String( state.signal ) }
      options={ signalOptions }
      onChange={ ( signal ) => onChange( { signal } ) }
    />
    <dl className="sm-lab-readonly-list">
      { getSignalVariations( state.parentVariation ).map( ( item ) => (
        <div key={ item.signal }>
          <dt>Signal { item.signal }</dt>
          <dd>Variation { item.variation }</dd>
        </div>
      ) ) }
    </dl>
  </PanelBody>
);
