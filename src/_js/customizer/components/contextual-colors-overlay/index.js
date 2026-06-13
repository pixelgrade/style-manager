import React from 'react';

import { Overlay } from '../index';

import './style.scss';

/**
 * The Contextual Colors board (premium). Content Colors gives each piece of
 * content — post, page, project, product — its own colour, drawn from its
 * cover image and expanded into a full accessible palette that tints that
 * item's accents (card hover border, reading bar, page transition).
 *
 * It's set per item (post meta), so the Site Editor only holds the global
 * on/off — this board *demonstrates the concept* with illustrative entries
 * rather than reading real per-post colours. The 12-grade strip uses the same
 * ramp the real generator does, so it is truthful, not decorative.
 */

const parseHex = hex => {
  const h = String( hex ).replace( '#', '' );
  return {
    r: parseInt( h.slice( 0, 2 ), 16 ),
    g: parseInt( h.slice( 2, 4 ), 16 ),
    b: parseInt( h.slice( 4, 6 ), 16 ),
  };
};

const toHex = ( r, g, b ) =>
  '#' + [ r, g, b ].map( x => Math.max( 0, Math.min( 255, Math.round( x ) ) ).toString( 16 ).padStart( 2, '0' ) ).join( '' );

// Mix `hex` toward `mixHex` by `ratio` (0..1 = amount of mixHex).
const mix = ( hex, mixHex, ratio ) => {
  const a = parseHex( hex );
  const b = parseHex( mixHex );
  return toHex( a.r + ( b.r - a.r ) * ratio, a.g + ( b.g - a.g ) * ratio, a.b + ( b.b - a.b ) * ratio );
};

// The real generator's ramp: 6 mixes toward white, the source, 5 toward black.
const WHITE_MIX = [ 0.92, 0.84, 0.72, 0.6, 0.4, 0.2 ];
const BLACK_MIX = [ 0.18, 0.34, 0.5, 0.66, 0.82 ];

const grades = source => [
  ...WHITE_MIX.map( ratio => mix( source, '#ffffff', ratio ) ),
  source,
  ...BLACK_MIX.map( ratio => mix( source, '#000000', ratio ) ),
];

// A photo-like cover swatch whose dominant colour is the entry colour.
const cover = color =>
  `linear-gradient(135deg, ${ mix( color, '#ffffff', 0.4 ) } 0%, ${ color } 62%, ${ mix( color, '#000000', 0.22 ) } 100%)`;

const ENTRIES = [
  { type: 'Post', color: '#C2613D' },
  { type: 'Page', color: '#2E8B83' },
  { type: 'Project', color: '#4B4FA6' },
  { type: 'Product', color: '#C99A3A' },
  { type: 'Post', color: '#8B4A6B' },
  { type: 'Project', color: '#3E7A4E' },
];

const ContextualColorsOverlay = props => {
  const { show } = props;

  return (
    <Overlay show={ show }>
      <ContextualColorsPreview key={ 'overlay_contextual_colors_preview' } />
    </Overlay>
  );
};

const ContextualColorsPreview = () => {
  const { __ } = wp.i18n;

  const hero = ENTRIES[ 0 ];
  const heroGrades = grades( hero.color );

  return (
    <div className="sm-contextual-colors-preview">
      <div className="sm-contextual-colors-preview__header">
        <h1>
          { __( 'Contextual Colors', '__plugin_txtd' ) }
          <span className="sm-contextual-colors-preview__premium">{ __( 'Premium', '__plugin_txtd' ) }</span>
        </h1>
        <p>
          { __( 'Give each post, page, project or product its own colour — picked by hand or pulled automatically from its cover image — then expanded into a full, accessible palette for the accents that belong to it.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-contextual-colors-preview__section">
        <h2>{ __( 'From cover to palette', '__plugin_txtd' ) }</h2>
        <div className="sm-contextual-colors-preview__engine">
          <span className="sm-contextual-colors-preview__cover" style={ { backgroundImage: cover( hero.color ) } } aria-hidden="true" />
          <span className="sm-contextual-colors-preview__arrow" aria-hidden="true">→</span>
          <span className="sm-contextual-colors-preview__chip">
            <span className="sm-contextual-colors-preview__dot" style={ { background: hero.color } } />
            { hero.color }
          </span>
          <span className="sm-contextual-colors-preview__arrow" aria-hidden="true">→</span>
          <span className="sm-contextual-colors-preview__grades">
            { heroGrades.map( ( g, i ) => (
              <span key={ i } className="sm-contextual-colors-preview__grade" style={ { background: g } } title={ g } />
            ) ) }
          </span>
        </div>
        <p className="sm-contextual-colors-preview__caption">
          { __( 'Pull the colour from the cover image — or pick it by hand — and it becomes 12 contrast-checked grades, readable on any background.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-contextual-colors-preview__section">
        <h2>{ __( 'Every item, its own', '__plugin_txtd' ) }</h2>
        <div className="sm-contextual-colors-preview__gallery">
          { ENTRIES.map( ( entry, i ) => (
            <div className="sm-contextual-colors-preview__card" key={ i } style={ { '--entry': entry.color } }>
              <span className="sm-contextual-colors-preview__card-cover" style={ { backgroundImage: cover( entry.color ) } } aria-hidden="true" />
              <div className="sm-contextual-colors-preview__card-body">
                <span className="sm-contextual-colors-preview__card-type">{ entry.type }</span>
                <span className="sm-contextual-colors-preview__card-line" />
                <span className="sm-contextual-colors-preview__card-line sm-contextual-colors-preview__card-line--short" />
                <span className="sm-contextual-colors-preview__card-meta">
                  <span className="sm-contextual-colors-preview__dot" style={ { background: entry.color } } />
                  { entry.color }
                </span>
              </div>
            </div>
          ) ) }
        </div>
        <p className="sm-contextual-colors-preview__caption">
          { __( 'Drawn from each item’s cover image — across posts, pages, projects and products.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-contextual-colors-preview__section">
        <h2>{ __( 'Where it lands', '__plugin_txtd' ) }</h2>
        <div className="sm-contextual-colors-preview__lands" style={ { '--entry': hero.color } }>
          <div className="sm-contextual-colors-preview__land">
            <span className="sm-contextual-colors-preview__land-demo sm-contextual-colors-preview__land-demo--titles" aria-hidden="true">
              <span className="sm-contextual-colors-preview__demo-title" />
              <span className="sm-contextual-colors-preview__demo-line" />
              <span className="sm-contextual-colors-preview__demo-line sm-contextual-colors-preview__demo-line--short" />
            </span>
            <span className="sm-contextual-colors-preview__land-label">{ __( 'Titles', '__plugin_txtd' ) }</span>
          </div>
          <div className="sm-contextual-colors-preview__land">
            <span className="sm-contextual-colors-preview__land-demo sm-contextual-colors-preview__land-demo--buttons" aria-hidden="true">
              <span className="sm-contextual-colors-preview__demo-btn sm-contextual-colors-preview__demo-btn--filled" />
              <span className="sm-contextual-colors-preview__demo-btn sm-contextual-colors-preview__demo-btn--outline" />
            </span>
            <span className="sm-contextual-colors-preview__land-label">{ __( 'Buttons', '__plugin_txtd' ) }</span>
          </div>
          <div className="sm-contextual-colors-preview__land">
            <span className="sm-contextual-colors-preview__land-demo sm-contextual-colors-preview__land-demo--transition" aria-hidden="true" />
            <span className="sm-contextual-colors-preview__land-label">{ __( 'Page transitions', '__plugin_txtd' ) }</span>
          </div>
        </div>
        <p className="sm-contextual-colors-preview__caption">
          { __( 'The item’s colour flows into the accents that belong to it — its titles, its buttons, and the transition to the next item.', '__plugin_txtd' ) }
        </p>
      </div>
    </div>
  );
};

export default ContextualColorsOverlay;
