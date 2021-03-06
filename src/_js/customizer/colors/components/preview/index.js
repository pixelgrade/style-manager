import classnames from 'classnames';
import React, { Fragment, useEffect, useState } from 'react';

import './style.scss';

const Preview = ( props ) => {

  return (
    <Fragment>
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
      <PalettePreviewList { ...props } />
    </Fragment>
  )
}

const PalettePreviewList = ( props ) => {

  const { palettes } = props;

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
        palette={ { description, ...palette } } />
    );
  } )
}

const PalettePreview = ( props ) => {
  const { palette, isActive, setActivePalette } = props;
  const { id, colors, textColors, lightColorsCount, sourceIndex } = palette;
  const [ lastHover, setLastHover ] = useState( sourceIndex );

  const siteVariationSetting = wp.customize( 'sm_site_color_variation' );
  const [ siteVariation, setSiteVariation ] = useState( parseInt( siteVariationSetting(), 10 ) );

  const onSiteVariationChange = ( newValue ) => {
    setSiteVariation( parseInt( newValue, 10 ) );
  }

  useEffect( () => {
    setLastHover( sourceIndex );
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
    <div className={ `palette-preview sm-palette-${ id } ${ lastHover !== false ? `sm-variation-${ lastHover + 1 }` : '' }` }>
      <div className={ `sm-overlay__wrap` }>
        <div className={ `sm-overlay__container` }>
          <div className={ `palette-preview-set` }>
            { colors.map( ( color, index ) => {

              const variation = index + 1;
              const showLightForeground = normalize( index ) === 0;
              const showDarkForeground = normalize( index ) === 9;
              const foregroundToShow = normalize( lastHover ) >= lightColorsCount ? showLightForeground : showDarkForeground;

              const passedProps = {
                isSource: normalize( index ) === sourceIndex,
                showCard: isActive && index === lastHover,
                showAccent: isActive && ( lastHover !== false ) && ( index === ( lastHover + 6 ) % 12 ),
                showForeground: isActive && ( lastHover !== false ) && foregroundToShow,
                textColor: normalize( index ) >= lightColorsCount ? textColors[0].value : '#FFFFFF',
                variation,
              }

              return (
                <div key={ index } className={ `palette-preview-swatches sm-variation-${ variation }` }
                     onMouseEnter={ () => {
                       setActivePalette( id );
                       setLastHover( index );
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
  return variation > 10 ? variation - 2 : variation + 2;
}

const PalettePreviewGrade = ( props ) => {

  const {
    isSource,
    showCard,
    showAccent,
    showForeground,
    textColor,
    variation,
  } = props;

  const className = classnames(
    'palette-preview-swatches__wrap',
    {
      'is-source': isSource,
      'show-card': showCard,
      'show-accent': showAccent,
      'show-fg': showForeground,
    }
  )

  return (
    <div className={ className }>
      <div className="palette-preview-swatches__wrap-surface">
        <div className="palette-preview-swatches__text">{ styleManager.l10n.colorPalettes.palettePreviewSwatchSurfaceText }</div>
        <PalettePreviewGradeCard variation={ variation } />
      </div>
      <div className="palette-preview-swatches__wrap-background" style={ { color: 'var(--sm-current-bg-color)' } } />
      <div className="palette-preview-swatches__wrap-accent" style={ { color: 'var(--sm-current-bg-color)' } }>
        <div className={ `palette-preview-swatches__source-badge sm-variation-${ getStarVariation( variation ) }` } />
        <div className="palette-preview-swatches__text">{ styleManager.l10n.colorPalettes.palettePreviewSwatchAccentText }</div>
      </div>
      <div className="palette-preview-swatches__wrap-foreground"  style={ { color: textColor } }>
        <div className="palette-preview-swatches__text">{ styleManager.l10n.colorPalettes.palettePreviewSwatchForegroundText }</div>
      </div>
    </div>
  );
}

const PalettePreviewGradeCard = ( props ) => {

  const { variation } = props;
  const buttonVariation = ( variation - 1 + 6 ) % 12 + 1;

  return (
    <div className={ `palette-preview-swatches__card` }>
      <div className={ `palette-preview-swatches__card-content` }>
        <div className={ `palette-preview-swatches__source-badge sm-variation-${ getStarVariation( variation ) }` } />
        <h2 className="palette-preview-swatches__title">Text</h2>
        <div className="palette-preview-swatches__body">
          <div className="palette-preview-swatches__row" />
          <div className="palette-preview-swatches__row" />
        </div>
        <div className={ `palette-preview-swatches__button sm-variation-${ buttonVariation }` }>&rarr;</div>
      </div>
    </div>
  )
}

export default Preview;
