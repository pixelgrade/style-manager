/* global wp */
/**
 * Re-skin selected Customizer controls with the editor's native components.
 *
 * The PHP-rendered inputs stay in the DOM (hidden) as the engine's source of
 * truth — element links, connected fields, active-states and change detection
 * keep working untouched. The native component two-way binds to the same
 * shim setting, so this swaps the skin, not the wiring.
 *
 * Per review: range -> RangeControl (reset + input field), sm_toggle ->
 * ToggleControl, plain radio -> ToggleGroupControl (<=4 choices) /
 * RadioControl, select -> SelectControl. Kept as-is: sm_radio pills,
 * font palettes, the palette builder, font fields.
 */
import React from 'react';
import ReactDOM from 'react-dom';

const stripHtml = html => {
  const div = document.createElement( 'div' );
  div.innerHTML = html || '';
  return div.textContent.trim();
};

const getConfig = settingId => window.styleManager?.config?.settings?.[ settingId ] || {};

/**
 * Generic two-way bound wrapper around a native component.
 */
const BoundControl = ( { settingId, children } ) => {
  const { useState, useEffect } = wp.element;
  const setting = wp.customize( settingId );
  const [ value, setValue ] = useState( setting ? setting() : undefined );

  useEffect( () => {
    if ( ! setting ) {
      return undefined;
    }
    const listener = newValue => setValue( newValue );
    setting.bind( listener );
    return () => setting.unbind( listener );
  }, [] );

  const onChange = newValue => {
    setValue( newValue );
    if ( setting ) {
      setting.set( newValue );
    }
  };

  return children( value, onChange );
};

const NativeRange = ( { settingId } ) => {
  const { RangeControl } = wp.components;
  const config = getConfig( settingId );
  const attrs = config.input_attrs || {};

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => (
        <RangeControl
          __nextHasNoMarginBottom
          label={ config.label }
          help={ stripHtml( config.desc ) || undefined }
          value={ value === '' || value === undefined ? undefined : Number( value ) }
          onChange={ onChange }
          min={ attrs.min !== undefined ? Number( attrs.min ) : 0 }
          max={ attrs.max !== undefined ? Number( attrs.max ) : 100 }
          step={ attrs.step !== undefined ? Number( attrs.step ) : 1 }
          allowReset
          resetFallbackValue={ config.default !== undefined ? Number( config.default ) : undefined }
          withInputField
        />
      ) }
    </BoundControl>
  );
};

const NativeToggle = ( { settingId } ) => {
  const { ToggleControl } = wp.components;
  const config = getConfig( settingId );

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => (
        <ToggleControl
          __nextHasNoMarginBottom
          label={ config.label }
          help={ stripHtml( config.desc ) || undefined }
          checked={ !! value && '0' !== value }
          onChange={ onChange }
        />
      ) }
    </BoundControl>
  );
};

const NativeRadio = ( { settingId } ) => {
  const { RadioControl } = wp.components;
  const ToggleGroupControl = wp.components.__experimentalToggleGroupControl || wp.components.ToggleGroupControl;
  const ToggleGroupControlOption = wp.components.__experimentalToggleGroupControlOption || wp.components.ToggleGroupControlOption;
  const config = getConfig( settingId );
  const choices = Object.entries( config.choices || {} ).map( ( [ v, label ] ) => ( { value: v, label: stripHtml( String( label ) ) } ) );
  const useGroup = ToggleGroupControl && choices.length <= 4;

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => useGroup ? (
        <ToggleGroupControl
          __nextHasNoMarginBottom
          isBlock
          label={ config.label }
          help={ stripHtml( config.desc ) || undefined }
          value={ String( value ) }
          onChange={ v => onChange( v ) }
        >
          { choices.map( choice => (
            <ToggleGroupControlOption key={ choice.value } value={ choice.value } label={ choice.label } />
          ) ) }
        </ToggleGroupControl>
      ) : (
        <RadioControl
          label={ config.label }
          help={ stripHtml( config.desc ) || undefined }
          selected={ String( value ) }
          options={ choices }
          onChange={ onChange }
        />
      ) }
    </BoundControl>
  );
};

const NativeSelect = ( { settingId, li } ) => {
  const { SelectControl } = wp.components;
  const config = getConfig( settingId );
  // Choices from the rendered select (labels may be filtered server-side).
  const select = li.querySelector( 'select' );
  const options = select
    ? Array.from( select.options ).map( o => ( { value: o.value, label: o.textContent } ) )
    : Object.entries( config.choices || {} ).map( ( [ v, label ] ) => ( { value: v, label: stripHtml( String( label ) ) } ) );

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => (
        <SelectControl
          __nextHasNoMarginBottom
          label={ config.label }
          help={ stripHtml( config.desc ) || undefined }
          value={ String( value ) }
          options={ options }
          onChange={ onChange }
        />
      ) }
    </BoundControl>
  );
};

const COMPONENTS = {
  range: NativeRange,
  sm_toggle: NativeToggle,
  radio: NativeRadio,
  select: NativeSelect,
};

/**
 * The setting id for a control id ({setting}_control suffix).
 */
const controlToSettingId = controlId => controlId.replace( /_control$/, '' );

export const mountNativeControls = ( eng, payload ) => {
  payload.structure.sections.forEach( section => {
    section.controls.forEach( control => {
      const Component = COMPONENTS[ control.type ];
      if ( ! Component ) {
        return;
      }

      const settingId = controlToSettingId( control.id );
      if ( ! wp.customize( settingId ) || ! getConfig( settingId ) ) {
        return;
      }

      const liId = `customize-control-${ control.id.replace( /\[/g, '-' ).replace( /\]/g, '' ) }`;
      const li = eng.root.querySelector( `#${ CSS.escape( liId ) }` );
      if ( ! li || li.querySelector( '.sm-native-control' ) ) {
        return;
      }

      Array.from( li.children ).forEach( child => {
        child.style.display = 'none';
      } );

      const target = document.createElement( 'div' );
      target.className = 'sm-native-control';
      li.insertBefore( target, li.firstChild );

      ReactDOM.render( <Component settingId={ settingId } li={ li } />, target );
    } );
  } );
};
