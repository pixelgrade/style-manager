import classnames from 'classnames';
import React, { Fragment, useEffect, useState } from 'react';
import { useCustomizeSettingCallback } from "../../hooks";
import DarkMode from '../../../dark-mode';

import './style.scss';

const Preview = ( props ) => {

  const [ isDark, setDark ] = useState( DarkMode.isCompiledDark() );

  useEffect( () => {
    DarkMode.bind( setDark );

    return () => {
      DarkMode.unbind( setDark );
    }
  }, [] );

  return (
    <div className={ `palette-preview-wrap ${ isDark ? 'is-dark' : '' }` }>
      <div className={ `palette-preview-header sm-palette-1 sm-palette--shifted sm-variation-1` }>
        <div className={ `sm-overlay__wrap` }>
          <div className={ `sm-overlay__container` }>
            <div className={ `palette-preview-header-wrap` }>
              <h1 className={ `palette-preview-title` }>{ styleManager.l10n.colorPalettes.palettePreviewTitle }</h1>
              <p className={ `palette-preview-description` }>{ styleManager.l10n.colorPalettes.palettePreviewDesc }</p>
            </div>
          </div>
        </div>
      </div>
      <PalettePreviewList { ...props } isDark={ isDark } />
    </div>
  )
}

const PalettePreviewList = ( props ) => {

  const { palettes, isDark } = props;

  const userPalettes = palettes.filter( palette => {
    const { id } = palette;
    return ! ( typeof id === 'string' && id.charAt(0) === '_' );
  } );

  if ( ! userPalettes.length ) {
    return null;
  }

  const [ active, setActive ] = useState( userPalettes[0].id );

  return userPalettes.map( ( palette, index ) => {
    const description = index === 0 ? styleManager.l10n.colorPalettes.palettePreviewListDesc : '';

    return (
      <PalettePreview
        key={ palette.id }
        isActive={ active === palette.id }
        setActivePalette={ setActive }
        palette={ { description, ...palette } }
        isDark={ isDark }
      />
    );
  } )
}

const PalettePreview = ( props ) => {
  const { palette, isActive, setActivePalette, isDark } = props;
  const { id, colors, sourceIndex } = palette;
  const variations = isDark ? palette.darkVariations : palette.variations;
  const [ lastHover, setLastHover ] = useState( sourceIndex + 1 );

  const siteVariationSetting = wp.customize( 'sm_site_color_variation' );
  const [ siteVariation, setSiteVariation ] = useState( parseInt( siteVariationSetting(), 10 ) );

  const onSiteVariationChange = ( newValue ) => {
    setSiteVariation( parseInt( newValue, 10 ) );
  }

  useEffect( () => {
    setLastHover( sourceIndex + 1 );
  }, [ colors ] );

  useEffect( () => {
    // Attach the listeners on component mount.
    siteVariationSetting.bind( onSiteVariationChange );

    // Detach the listeners on component unmount.
    return () => {
      siteVariationSetting.unbind( onSiteVariationChange );
    }
  }, [] );

  const normalize = index => {
    return ( index + siteVariation - 1 + 12 ) % 12;
  }

  return (
    <div className={ `palette-preview sm-palette-${ id } ${ lastHover !== false ? `sm-variation-${ lastHover }` : '' }` }>
      <div className={ `sm-overlay__wrap` }>
        <div className={ `sm-overlay__container` }>
          <div className={ `palette-preview-set` }>
            { variations.map( ( variation, index ) => {

              const workingIndex = ( index + siteVariation - 1 + 12 ) % 12;
              const isSource = palette.source.findIndex( hex => variations[workingIndex].background === hex ) > -1 &&
                               variations.findIndex( v => variations[workingIndex].background === v.background ) === workingIndex;

              const passedProps = {
                isSource: isSource,
                showCard: isActive && index + 1 === lastHover,
              }

              return (
                <div key={ index + 1 } className={ `palette-preview-swatches sm-variation-${ index + 1 }` }
                     onMouseEnter={ () => {
                       setActivePalette( id );
                       setLastHover( index + 1 );
                     } }>
                  <PalettePreviewGrade { ...passedProps } />
                </div>
              )
            } ) }
          </div>
        </div>
      </div>
    </div>
  )
}

const getStarVariation = ( variation ) => {
  return ( variation + 6 - 1 ) % 12 + 1;
}

const PalettePreviewGrade = ( props ) => {

  const {
    isSource,
    showCard,
  } = props;

  const className = classnames(
    'palette-preview-swatches__wrap',
    {
      'is-source': isSource,
      'show-card': showCard,
    }
  )

  return (
    <div className={ className }>
      <div className="palette-preview-swatches__wrap-surface">
        <div className="palette-preview-swatches__text">{ styleManager.l10n.colorPalettes.palettePreviewSwatchSurfaceText }</div>
        <PalettePreviewGradeCard />
      </div>
      <div className="palette-preview-swatches__wrap-background" style={ { color: 'var(--sm-current-bg-color)' } } />
      <div className="palette-preview-swatches__wrap-accent" style={ { color: 'var(--sm-current-bg-color)' } }>
        <div className={ `palette-preview-swatches__source-badge` } />
        <div className="palette-preview-swatches__text">{ styleManager.l10n.colorPalettes.palettePreviewSwatchAccentText }</div>
      </div>
      <div className="palette-preview-swatches__wrap-foreground"  style={ { color: 'var(--sm-current-fg1-color)' } }>
        <div className="palette-preview-swatches__text">{ styleManager.l10n.colorPalettes.palettePreviewSwatchForegroundText }</div>
      </div>
    </div>
  );
}

const PalettePreviewGradeCard = () => {

  return (
    <div className={ `palette-preview-swatches__card` }>
      <div className={ `palette-preview-swatches__card-content` }>
        <div className={ `palette-preview-swatches__source-badge` } />
        <div className="palette-preview-swatches__title">Text</div>
        <div className="palette-preview-swatches__body">
          <div className="palette-preview-swatches__row" />
          <div className="palette-preview-swatches__row" />
        </div>
        <div className={ `palette-preview-swatches__button` }>&rarr;</div>
      </div>
    </div>
  )
}

export default Preview;
