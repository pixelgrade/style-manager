import { Fragment, useEffect, useState, useMemo, useCallback } from "react";

import { getFontFieldCSSValue } from "../../../utils";

import * as globalService from "../../global-service";

import useCustomizeSettingCallback from "../../hooks/use-customize-setting-callback";
import { getConnectedFieldFontData } from "../../fonts/connected-fields";
import { getConnectedFieldsIDs, getSetting } from "../../global-service";
import { Overlay } from "../index";
import './style.scss';
import elements from "./elements";

const TypographyOverlay = ( props ) => {
  const { show } = props;

  return (
    <Overlay show={ show }>
      <TypographyPreview />
    </Overlay>
  )
}

const TypographyPreview = () => {
  const settingIDs = styleManager.fontPalettes.masterSettingIds;

  return (
    <div className="sm-typography-preview">
      <Cell name="category" isHead>
        { styleManager.l10n.colorPalettes.typographyPreviewHeadCategoryLabel }
      </Cell>
      <Cell name="preview" isHead>
        { styleManager.l10n.colorPalettes.typographyPreviewHeadPreviewLabel }
      </Cell>
      <Cell name="size" isHead>
        { styleManager.l10n.colorPalettes.typographyPreviewHeadSizeLabel }
      </Cell>
      { elements.map( ( element, index ) => {
        const classNameBase = 'sm-typography-preview__separator';
        const classNames = [ classNameBase ];

        if ( index === 0 ) {
          classNames.push( `${ classNameBase }--head` );
        }

        return (
          <Fragment>
            <div className={ classNames.join( '  ' ) } />
            <Element { ...element } />
          </Fragment>
        )
      } ) }
    </div>
  )
}

const Cell = ( props ) => {
  const { isHead, name, children, id} = props;
  const classNameBase = 'sm-typography-preview__cell';
  const classNames = [
    classNameBase,
    `${ classNameBase }--${ name }`,
    id
  ]

  if ( isHead ) {
    classNames.push( `${ classNameBase }--head` );
  }

  return (
    <div className={ classNames.join( '  ' ) }>{ children }</div>
  )
}

const convertCSSValuesToStrings = style => {
  return Object.keys( style ).reduce( ( obj, key ) => {
    const value = `${ style[ key ] }`;
    const alteredValue = key === 'font-size' ? `${ value }px` : value;
    return { ...obj, [key]: alteredValue };
  }, {} )
}

const Element = ( props ) => {
  const { children, id } = props;
  const [ size, setSize ] = useState( null );
  const [ category, setCategory ] = useState( null );
  const config = globalService.getSettingConfig( 'sm_fonts_connected_fields_preset' );
  const connectedSettingID = useMemo( () => `${ styleManager.config.options_name }[${ id }]`, [ id ] );
  const [ style, setStyle ] = useState( {} );

  const onConnectedFieldsPresetChange = useCallback( newValue => {
    const newValueConfig = config.choices[ newValue ].config;

    Object.keys( newValueConfig ).forEach( settingID => {
      const connectedFields = newValueConfig[ settingID ];

      if ( connectedFields.some( connectedField => connectedField.includes( id ) ) ) {
        setCategory( settingID );
      }
    } );
  }, [] )

  const updateSize = useCallback( () => {
    wp.customize( category, setting => {
      const fontsLogic = setting();
      const styles = {};

      wp.customize( connectedSettingID, connectedSetting => {
        const value = connectedSetting();
        const FontFieldCSSValue = getFontFieldCSSValue( connectedSettingID, value );
        const StringCSSValue = convertCSSValuesToStrings( FontFieldCSSValue );

        Object.assign( styles, StringCSSValue );
      } );

      wp.customize( `${ category }_elevation`, elevationSetting => {
        wp.customize( `${ category }_pitch`, pitchSetting => {
          const elevation = elevationSetting();
          const pitch = pitchSetting();
          const connectedFieldFontData = getConnectedFieldFontData( connectedSettingID, category, fontsLogic, elevation, pitch );
          const FontFieldCSSValue = getFontFieldCSSValue( connectedSettingID, connectedFieldFontData );
          const StringCSSValue = convertCSSValuesToStrings( FontFieldCSSValue );

          setSize( parseInt( connectedFieldFontData?.font_size?.value, 10 ) );

          Object.assign( styles, StringCSSValue );
        } );
      } );

      if ( category === 'sm_font_accent' ) {
        Object.assign( styles, {
          'font-size': '60px'
        } );
      }

      setStyle( styles );
    } );
  }, [ category ] );

  useEffect( () => {
    const settingIDs = styleManager.fontPalettes.masterSettingIds;

    settingIDs.forEach( settingID => {
      const connectedFields = getConnectedFieldsIDs( settingID );

      if ( connectedFields.some( connectedFieldID => connectedFieldID.includes( id ) ) ) {
        setCategory( settingID );
      }
    } );

  }, [ id ] );

  useEffect( () => {
    wp.customize( 'sm_fonts_connected_fields_preset', setting => {
      const value = setting();
      onConnectedFieldsPresetChange( value );
    } );
  }, [] );

  useCustomizeSettingCallback( 'sm_fonts_connected_fields_preset', onConnectedFieldsPresetChange, [] );
  useCustomizeSettingCallback( connectedSettingID, updateSize, [ category ] );
  useEffect( updateSize, [ category ] );

  return (
    <Fragment>
      <Cell name="category">
        <Category id={ category } />
      </Cell>
      <Cell name="preview" id={ id }>
        <div style={ style }>
          { children }
        </div>
      </Cell>
      <Cell name="size">
        { ! isNaN( size ) ? size : null }
      </Cell>
    </Fragment>
  )
}

const Category = ( props ) => {
  const { id } = props;

  const categories = [ {
    id: 'sm_font_primary',
    label: styleManager.l10n.colorPalettes.typographyPreviewPrimaryShortLabel,
  }, {
    id: 'sm_font_secondary',
    label: styleManager.l10n.colorPalettes.typographyPreviewSecondaryShortLabel,
  }, {
    id: 'sm_font_body',
    label: styleManager.l10n.colorPalettes.typographyPreviewBodyShortLabel,
  }, {
    id: 'sm_font_accent',
    label: styleManager.l10n.colorPalettes.typographyPreviewAccentShortLabel,
  } ];

  const current = categories.find( category => category.id === id );

  if ( ! current ) {
    return null;
  }

  return (
    <span className={ id }>{ current.label }</span>
  )
}

export default TypographyOverlay;
