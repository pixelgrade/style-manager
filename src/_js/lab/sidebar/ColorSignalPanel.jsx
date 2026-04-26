/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { PanelBody } from '@wordpress/components';
import { getSignalVariations } from '../state.js';

const signalOptions = [
  { label: 'None', value: 0 },
  { label: 'Low', value: 1 },
  { label: 'Medium', value: 2 },
  { label: 'High', value: 3 },
];

const SignalBars = ( { value } ) => (
  <span className="sm-lab-signal-control__bars" aria-hidden="true">
    { [ 1, 2, 3 ].map( ( bar ) => (
      <span key={ bar } className={ bar <= value ? 'is-active' : '' } />
    ) ) }
  </span>
);

export const ColorSignalPanel = ( { state, onChange } ) => (
  <PanelBody title="Block Color Signal" initialOpen={ false }>
    <div className="sm-lab-signal-control">
      <p className="sm-lab-signal-control__label">Signal strength</p>
      <div className="sm-lab-signal-options">
        { signalOptions.map( ( option ) => (
          <button
            key={ option.value }
            type="button"
            aria-pressed={ Number( state.signal ) === option.value }
            onClick={ () => onChange( { signal: option.value } ) }
          >
            <SignalBars value={ option.value } />
            <span>{ option.label }</span>
          </button>
        ) ) }
      </div>
    </div>
    <dl className="sm-lab-readonly-list">
      <div>
        <dt>Inherited variation</dt>
        <dd>Variation { state.variation }</dd>
      </div>
      { getSignalVariations( state.variation ).map( ( item ) => (
        <div key={ item.signal }>
          <dt>Signal { item.signal }</dt>
          <dd>Variation { item.variation }</dd>
        </div>
      ) ) }
    </dl>
  </PanelBody>
);
