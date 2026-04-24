/** @jsx createElement */
import classnames from 'classnames';
import { createElement, useEffect, useMemo, useState } from '@wordpress/element';

import {
  getInitialHoverVariation,
  getUserPalettes,
  isSourceVariation,
  normalizePreviewIndex,
} from './utils.js';

import './style.scss';

const defaultStrings = {
  palettePreviewTitle: '',
  palettePreviewDesc: '',
  palettePreviewListDesc: '',
  palettePreviewSwatchSurfaceText: '',
  palettePreviewSwatchAccentText: '',
  palettePreviewSwatchForegroundText: '',
};

export const ColorSystemPreview = ( {
  palettes = [],
  isDark = false,
  siteVariation = 1,
  strings = {},
} ) => {
  const labels = {
    ...defaultStrings,
    ...strings,
  };
  const userPalettes = useMemo( () => getUserPalettes( palettes ), [ palettes ] );
  const [ activePalette, setActivePalette ] = useState( null );

  useEffect( () => {
    if ( userPalettes.length ) {
      setActivePalette( userPalettes[0].id );
    }
  }, [ userPalettes ] );

  return (
    <div className={ `palette-preview-wrap ${ isDark ? 'is-dark' : '' }` }>
      <div className="palette-preview-header sm-palette-1 sm-palette--shifted sm-variation-1">
        <div className="sm-overlay__wrap">
          <div className="sm-overlay__container">
            <div className="palette-preview-header-wrap">
              <h1 className="palette-preview-title">{ labels.palettePreviewTitle }</h1>
              <p className="palette-preview-description">{ labels.palettePreviewDesc }</p>
            </div>
          </div>
        </div>
      </div>
      { userPalettes.map( ( palette, index ) => (
        <PalettePreview
          key={ palette.id }
          isActive={ activePalette === palette.id }
          setActivePalette={ setActivePalette }
          palette={ {
            description: index === 0 ? labels.palettePreviewListDesc : '',
            ...palette,
          } }
          isDark={ isDark }
          siteVariation={ siteVariation }
          strings={ labels }
        />
      ) ) }
    </div>
  );
};

const PalettePreview = ( {
  palette,
  isActive,
  setActivePalette,
  isDark,
  siteVariation,
  strings,
} ) => {
  const {
    id,
    source = [],
    sourceIndex = 0,
  } = palette;
  const variations = Array.isArray( isDark ? palette.darkVariations : palette.variations )
    ? ( isDark ? palette.darkVariations : palette.variations )
    : [];
  const [ lastHover, setLastHover ] = useState( getInitialHoverVariation( sourceIndex ) );

  useEffect( () => {
    setLastHover( getInitialHoverVariation( sourceIndex ) );
  }, [ palette.colors, sourceIndex ] );

  return (
    <div className={ `palette-preview sm-palette-${ id } ${ lastHover !== false ? `sm-variation-${ lastHover }` : '' }` }>
      <div className="sm-overlay__wrap">
        <div className="sm-overlay__container">
          <div className="palette-preview-set">
            { variations.map( ( variation, index ) => {
              const workingIndex = normalizePreviewIndex( index, siteVariation );
              const passedProps = {
                isSource: isSourceVariation( {
                  variations,
                  workingIndex,
                  source,
                } ),
                showCard: isActive && index + 1 === lastHover,
                strings,
              };

              return (
                <div
                  key={ index + 1 }
                  className={ `palette-preview-swatches sm-variation-${ index + 1 }` }
                  onMouseEnter={ () => {
                    setActivePalette( id );
                    setLastHover( index + 1 );
                  } }
                >
                  <PalettePreviewGrade { ...passedProps } />
                </div>
              );
            } ) }
          </div>
        </div>
      </div>
    </div>
  );
};

const PalettePreviewGrade = ( {
  isSource,
  showCard,
  strings,
} ) => {
  const className = classnames(
    'palette-preview-swatches__wrap',
    {
      'is-source': isSource,
      'show-card': showCard,
    }
  );

  return (
    <div className={ className }>
      <div className="palette-preview-swatches__wrap-surface">
        <div className="palette-preview-swatches__text">{ strings.palettePreviewSwatchSurfaceText }</div>
        <PalettePreviewGradeCard />
      </div>
      <div className="palette-preview-swatches__wrap-background" style={ { color: 'var(--sm-current-bg-color)' } } />
      <div className="palette-preview-swatches__wrap-accent" style={ { color: 'var(--sm-current-bg-color)' } }>
        <div className="palette-preview-swatches__source-badge" />
        <div className="palette-preview-swatches__text">{ strings.palettePreviewSwatchAccentText }</div>
      </div>
      <div className="palette-preview-swatches__wrap-foreground" style={ { color: 'var(--sm-current-fg1-color)' } }>
        <div className="palette-preview-swatches__text">{ strings.palettePreviewSwatchForegroundText }</div>
      </div>
    </div>
  );
};

const PalettePreviewGradeCard = () => (
  <div className="palette-preview-swatches__card">
    <div className="palette-preview-swatches__card-content">
      <div className="palette-preview-swatches__source-badge" />
      <div className="palette-preview-swatches__title">Text</div>
      <div className="palette-preview-swatches__body">
        <div className="palette-preview-swatches__row" />
        <div className="palette-preview-swatches__row" />
      </div>
      <div className="palette-preview-swatches__buttons">
        <div className="palette-preview-swatches__button">&rarr;</div>
        <div className="palette-preview-swatches__button palette-preview-swatches__button--style-2">&rarr;</div>
        <div className="palette-preview-swatches__button palette-preview-swatches__button--style-3">&rarr;</div>
      </div>
    </div>
  </div>
);

export default ColorSystemPreview;
