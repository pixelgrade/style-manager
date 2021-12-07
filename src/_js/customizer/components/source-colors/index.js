import React, { Fragment, useContext, useEffect, useCallback, useState, useRef } from 'react';

import {
  useActivePreset,
  useOutsideClick,
  useUpdateSourceSetting
} from '../../hooks';

import {
  ConfigContext,
  ContextualMenu
} from '../../components';

import { ColorPicker } from './color-picker';

import {
  addNewColorGroup,
  addNewColorToGroup,
  deleteColor,
  updateColor,
} from "./utils";

import './style.scss';

export const SourceColors = ( props ) => {
  const { config } = useContext( ConfigContext );
  const updateSourceSetting = useUpdateSourceSetting();
  const setConfig = updateSourceSetting;

  useEffect( () => {

    if ( ! config.length ) {
      setConfig( addNewColorGroup( config ) );
      return;
    }

    if ( ! config.filter( group => { return !! group.sources.length } ).length ) {
      setConfig( [] );
    }

  }, [ config ] );

  return (
    <div className="c-palette-builder__source-list">
      { config.map( ( group, groupIndex ) => {

        return (
          <SourceColorsGroup
            key={ group.uid }
            sources={ group.sources }
            index={ groupIndex }
          />
        );
      } ) }
    </div>
  )
}

const SourceColorsGroup = ( props ) => {
  const { uid, sources } = props;
  const groupIndex = props.index;

  const style = {
    '--sm-source-main-color': sources[0].value,
  }

  return (
    <div key={ uid } className="c-palette-builder__source-group" style={ style }>
      { sources.map( ( color, index ) => (
        <SourceColorControl
          key={ color.uid }
          groupIndex={ groupIndex }
          index={ index }
          color={ color }
          showPicker={ color.showPicker }
        />
      ) ) }
    </div>
  )
}

const SourceColorControl = ( props ) => {

  const {
    color,
    index,
    groupIndex
  } = props;

  const [ active, setActive ] = useState( false );
  const [ hover, setHover ] = useState( false );
  const [ menuIsOpen, setMenuIsOpen ] = useState( false );
  const [ editable, setEditable ] = useState( false );
  const [ showPicker, setShowPicker ] = useState();

  const { config } = useContext( ConfigContext );
  const updateSourceSetting = useUpdateSourceSetting();
  const setConfig = updateSourceSetting;

  const [ activePreset, setActivePreset ] = useActivePreset();

  const onChange = useCallback(( color ) => {
    const newConfig = updateColor( config, groupIndex, index, color );
    setConfig( newConfig );
    setActivePreset( '' );
  }, [config, groupIndex, index]);

  const interpolateColor = useCallback( () => {
    setConfig( addNewColorToGroup( config, groupIndex, index ) );
    setActivePreset( '' );
  }, [config, groupIndex, index] );

  const addColor = useCallback( () => {
    setConfig( addNewColorGroup( config, groupIndex ) );
    setActivePreset( '' );
  }, [config, groupIndex] );

  const renameColor = useCallback( () => { setEditable( true ) }, [] );

  const removeColor = useCallback( () => {
    setConfig( deleteColor( config, groupIndex, index ) );
    resetActivePreset();
  }, [config, groupIndex, index] );

  const actions = [
    { label: 'Interpolate Color', callback: interpolateColor },
    { label: 'Add Color', callback: addColor },
    { label: 'Rename Color', callback: renameColor },
    { label: 'Remove Color', callback: removeColor, className: 'c-contextual-menu__list-item--danger' },
  ];

  const inputRef = useRef( null );
  const pickerRef = useRef( null );

  useOutsideClick( pickerRef, () => {
    setShowPicker( false );
  } );

  // delay setting showPicker with one render cycle in order to show fadein animation
  useEffect( () => {
    if ( typeof showPicker === "undefined" && typeof props.showPicker !== "undefined" ) {
      setShowPicker( props.showPicker );
    }
  }, [ showPicker ] );

  useEffect( () => {
    setActive( hover || menuIsOpen );
  }, [ hover, menuIsOpen ] )

  useEffect( () => {
    if ( editable ) {
      inputRef.current.focus();
    }
  }, [ editable ] );

  const onLabelBlur = e => {
    setEditable( false );
  };

  return (
    <div
      onMouseEnter={ () => { setHover( true ) } }
      onMouseLeave={ () => { setHover( false ) } }
      onClick={ () => { setShowPicker( ! showPicker ) } }
      ref={ pickerRef }
      className={ `c-palette-builder__source-item ${ active ? 'c-palette-builder__source-item--active' : '' }` }>
      <ColorPicker hex={ color.value } onChange={ ( hex ) => { onChange( { value: hex } ) } } isOpen={ showPicker } />
      { ! editable && <div className="c-palette-builder__source-item-label">{ color.label }</div> }
      { editable &&
        <input type="text"
               ref={ inputRef }
               value={ color.label }
               className="c-palette-builder__source-item-label"
               onChange={ e => { onChange( { label: e.target.value } ) } }
               onBlur={ onLabelBlur } />
      }
      <ContextualMenu actions={ actions } onToggle={ setMenuIsOpen } onClick={ ( event ) => {
        event.stopPropagation();
        setShowPicker( false );
      } } />
    </div>
  );
}
