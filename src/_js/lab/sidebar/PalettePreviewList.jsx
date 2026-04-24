/** @jsx createElement */
import { createElement } from '@wordpress/element';

const getPaletteLabel = ( palette ) => palette.label || palette.name || palette.id;

const getVariationColor = ( variation ) => variation?.bg || variation?.background || '#f0f0f1';

export const PalettePreviewList = ( {
  palettes,
  activePalette,
  contextualPalette,
  onSelect,
} ) => {
  const contextualRows = contextualPalette ? [ {
    ...contextualPalette,
    id: 'contextual-lab',
    label: contextualPalette.label || 'Contextual Lab',
    description: 'Generated from scoped source',
  } ] : [];

  return (
    <div className="sm-lab-palette-list">
      { [ ...palettes, ...contextualRows ].map( ( palette ) => (
        <button
          key={ palette.id }
          type="button"
          className="sm-lab-palette-row"
          aria-pressed={ String( palette.id ) === String( activePalette ) }
          onClick={ () => onSelect( String( palette.id ) ) }
        >
          <span className="sm-lab-palette-row__label">{ getPaletteLabel( palette ) }</span>
          { palette.description ? <span className="sm-lab-palette-row__description">{ palette.description }</span> : null }
          <span className="sm-lab-palette-row__rail" aria-hidden="true">
            { ( palette.variations || [] ).slice( 0, 12 ).map( ( variation, index ) => (
              <span key={ index } style={ { background: getVariationColor( variation ) } } />
            ) ) }
          </span>
        </button>
      ) ) }
    </div>
  );
};
