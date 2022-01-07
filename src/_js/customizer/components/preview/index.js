import classnames from 'classnames';
import chroma from 'chroma-js';
import React, { Fragment, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import DarkMode from '../../../dark-mode';
import { useCustomizeSettingCallback } from "../../hooks";

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

  const { isDark } = props;
  const [ palettes, setPalettes ] = useState( [] );
  const [ active, setActive ] = useState( null );

  const userPalettes = useMemo( () => {
    return palettes.filter( palette => {
      const { id } = palette;
      return ! ( typeof id === 'string' && id.charAt(0) === '_' );
    } );
  }, [ palettes ] );

  useEffect( () => {
    wp.customize( 'sm_advanced_palette_output', setting => {
      const value = setting();
      setPalettes( JSON.parse( value ) );
    } );
  }, [] );

  useEffect( () => {
    if ( userPalettes.length ) {
      setActive( userPalettes[0].id );
    }
  }, [ userPalettes ] )

  useCustomizeSettingCallback( 'sm_advanced_palette_output', newValue => {
    setPalettes( JSON.parse( newValue ) );
  } );

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

  const onSiteVariationChange = useCallback( newValue => {
    setSiteVariation( parseInt( newValue, 10 ) );
  }, [] );

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

  const normalize = useCallback( index => ( index + siteVariation - 1 + 12 ) % 12, [ siteVariation ] );

  return (
    <div className={ `palette-preview sm-palette-${ id } ${ lastHover !== false ? `sm-variation-${ lastHover }` : '' }` }>
      <div className={ `sm-overlay__wrap` }>
        <div className={ `sm-overlay__container` }>
          <div className={ `palette-preview-set` }>
            { variations.map( ( variation, index ) => {

              const workingIndex = normalize( index );
              const isSource = palette.source.findIndex( hex => chroma.distance( variations[workingIndex].bg, hex ) === 0  ) > -1 &&
                               variations.findIndex( v => chroma.distance( variations[workingIndex].bg, v.bg ) === 0 ) === workingIndex;

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
        <div className="palette-preview-swatches__buttons">
          <div className={ `palette-preview-swatches__button` }>&rarr;</div>
          <div className={ `palette-preview-swatches__button  palette-preview-swatches__button--style-2` }>&rarr;</div>
          <div className={ `palette-preview-swatches__button  palette-preview-swatches__button--style-3` }>&rarr;</div>
        </div>
      </div>
    </div>
  )
}

export default Preview;
