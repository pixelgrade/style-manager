/* global wp, WebFont */
/**
 * Native skin for the font fields in the Site Editor sidebar.
 *
 * Same contract as native-controls.js: the PHP-rendered font control stays in
 * the DOM (hidden) as the engine's source of truth — the family select, the
 * variant select and every subfield input keep their jQuery bindings
 * (onFontFamilyChange, selfUpdateValue, loadFontValue). The native component
 * two-way binds to the same setting and drives those hidden inputs, so it
 * swaps the skin, not the wiring.
 *
 * The font family picker is fully data-driven from the styleManager.fonts
 * catalog (~2,000 families): a searchable, windowed list grouped by source,
 * with lazy per-font previews loaded through the Web Font Loader. No select2,
 * and none of the ~2,000 <option> elements per field the Customizer carries.
 */
import React from 'react';
import $ from 'jquery';

import { getFontDetails, determineFontType } from '../customizer/fonts/utils';
import { ensureFontFamilyOption } from '../customizer/fonts/native-ui';
import { applyFontFamilySelection } from './font-setting-adapter';
import { getStaffPicksCollections } from './font-staff-picks';

const HEADER_HEIGHT = 34;
const ITEM_HEIGHT = 40;
const LIST_HEIGHT = 312;
const OVERSCAN_PX = 200;

const getConfig = settingId => window.styleManager?.config?.settings?.[ settingId ] || {};

/** Families whose preview face was already requested (persists across opens). */
const requestedPreviews = new Set();

/**
 * The last-used filter, shared by every font field and kept for the session —
 * browsing "Staff Picks · Headings" across several fields shouldn't mean
 * re-picking the filter on every open.
 */
const FILTER_STORAGE_KEY = 'sm-font-picker-filter';

const getStoredFilter = () => {
  try {
    return window.sessionStorage.getItem( FILTER_STORAGE_KEY ) || '';
  } catch ( e ) {
    return '';
  }
};

const storeFilter = value => {
  try {
    window.sessionStorage.setItem( FILTER_STORAGE_KEY, value );
  } catch ( e ) {}
};

const quoteFamily = family => /[\s"']/.test( family ) ? `"${ family.replace( /"/g, '' ) }"` : family;

/**
 * The CSS font-family stack used to preview a catalog entry.
 */
const previewFontStack = font => {
  if ( 'system' === font.group ) {
    // System entries are stack aliases (e.g. system-font-serif-times) — the
    // stack itself is the real family list.
    return font.fallback || 'inherit';
  }
  return quoteFamily( font.family ) + ( font.fallback ? `, ${ font.fallback }` : ', sans-serif' );
};

/**
 * Queue preview font loading for the given catalog entries (debounced by the
 * caller). Google fonts batch through the WebFont google module; cloud/theme
 * fonts load their own stylesheet. Best effort: no WebFont, no previews.
 */
const loadPreviewFonts = fonts => {
  if ( typeof WebFont === 'undefined' ) {
    return;
  }

  const fresh = fonts.filter( font => ! requestedPreviews.has( font.family ) && 'system' !== font.group );
  fresh.forEach( font => requestedPreviews.add( font.family ) );

  const google = fresh.filter( font => 'google' === font.group ).map( font => `${ font.family }:regular` );
  if ( google.length ) {
    try {
      WebFont.load( { google: { families: google }, classes: false, events: false } );
    } catch ( e ) {}
  }

  fresh.filter( font => font.src ).forEach( font => {
    try {
      WebFont.load( { custom: { families: [ font.family ], urls: [ font.src ] }, classes: false, events: false } );
    } catch ( e ) {}
  } );
};

/**
 * Build the flat font catalog from styleManager.fonts, grouped by source.
 */
let catalogCache = null;
const getCatalog = () => {
  if ( catalogCache ) {
    return catalogCache;
  }

  const fonts = window.styleManager?.fonts || {};
  const normalize = ( dict, group ) => Object.keys( dict || {} ).map( key => {
    const details = dict[ key ] || {};
    const family = details.family || key;
    return {
      family,
      display: details.family_display || family,
      category: details.category || '',
      fallback: details.fallback_stack || '',
      src: details.src || false,
      group,
    };
  } ).filter( font => !! font.family );

  catalogCache = {
    third: normalize( fonts.third_party_fonts, 'third' ),
    theme: normalize( fonts.theme_fonts, 'theme' ),
    cloud: normalize( fonts.cloud_fonts, 'cloud' ),
    system: normalize( fonts.system_fonts, 'system' ),
    google: normalize( fonts.google_fonts, 'google' ),
  };

  return catalogCache;
};

const GROUP_ORDER = [ 'recommended', 'third', 'theme', 'cloud', 'system', 'google' ];

const getGroupLabels = () => {
  const { __ } = wp.i18n;
  return {
    recommended: __( 'Recommended', '__plugin_txtd' ),
    third: __( 'Third-Party Fonts', '__plugin_txtd' ),
    theme: __( 'Theme Fonts', '__plugin_txtd' ),
    cloud: __( 'Cloud Fonts', '__plugin_txtd' ),
    system: __( 'System Fonts', '__plugin_txtd' ),
    google: __( 'Google Fonts', '__plugin_txtd' ),
  };
};

/**
 * Human labels for font variants (core "Appearance" style).
 */
const variantLabel = variant => {
  const { __ } = wp.i18n;
  const value = String( variant ).trim();

  if ( '' === value ) {
    return window.styleManager?.l10n?.fonts?.variantAutoText || __( 'Auto', '__plugin_txtd' );
  }

  const weightNames = {
    100: __( 'Thin', '__plugin_txtd' ),
    200: __( 'Extra Light', '__plugin_txtd' ),
    300: __( 'Light', '__plugin_txtd' ),
    400: __( 'Regular', '__plugin_txtd' ),
    500: __( 'Medium', '__plugin_txtd' ),
    600: __( 'Semi Bold', '__plugin_txtd' ),
    700: __( 'Bold', '__plugin_txtd' ),
    800: __( 'Extra Bold', '__plugin_txtd' ),
    900: __( 'Black', '__plugin_txtd' ),
  };

  const match = value.toLowerCase().match( /^(\d{3})?\s*(italic|regular)?$/ );
  if ( ! match ) {
    return value;
  }

  const weight = match[ 1 ] ? weightNames[ Number( match[ 1 ] ) ] : null;
  const isItalic = 'italic' === match[ 2 ];
  const parts = [];

  if ( weight ) {
    parts.push( `${ weight } ${ match[ 1 ] }` );
  } else if ( 'regular' === match[ 2 ] ) {
    parts.push( weightNames[ 400 ] );
  }
  if ( isItalic ) {
    parts.push( __( 'Italic', '__plugin_txtd' ) );
  }

  return parts.length ? parts.join( ' ' ) : value;
};

const chevronIcon = (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
    <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z" />
  </svg>
);

const checkIcon = (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
    <path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4 7.2-9.6-1.2-1z" />
  </svg>
);

/**
 * The searchable, windowed font family list rendered inside the popover.
 */
const FontFamilyList = ( { selected, recommended, onPick } ) => {
  const { SearchControl, SelectControl } = wp.components;
  const { useState, useMemo, useEffect, useRef } = wp.element;
  const { __, sprintf } = wp.i18n;

  const [ search, setSearch ] = useState( '' );
  const [ category, setCategoryState ] = useState( getStoredFilter );
  const [ scrollTop, setScrollTop ] = useState( 0 );
  const [ activeIndex, setActiveIndex ] = useState( -1 );
  const listRef = useRef( null );
  const loadTimer = useRef( null );

  const catalog = useMemo( getCatalog, [] );
  const groupLabels = useMemo( getGroupLabels, [] );

  // Family -> catalog entry index (first source wins, mirroring GROUP_ORDER).
  const byFamily = useMemo( () => {
    const index = {};
    GROUP_ORDER.forEach( group => ( catalog[ group ] || [] ).forEach( font => {
      if ( ! index[ font.family ] ) {
        index[ font.family ] = font;
      }
    } ) );
    return index;
  }, [ catalog ] );

  // Staff Picks collections, narrowed to families this site actually has.
  const collections = useMemo( () => {
    return getStaffPicksCollections()
      .map( collection => ( {
        ...collection,
        fonts: collection.families.map( family => byFamily[ family ] ).filter( Boolean ),
      } ) )
      .filter( collection => collection.fonts.length );
  }, [ byFamily ] );

  const staffPickFamilies = useMemo( () => {
    const picks = new Set();
    collections.forEach( collection => collection.fonts.forEach( font => picks.add( font.family ) ) );
    return picks;
  }, [ collections ] );

  const categories = useMemo( () => {
    const found = new Set();
    Object.keys( catalog ).forEach( group => catalog[ group ].forEach( font => {
      if ( font.category ) {
        found.add( font.category );
      }
    } ) );
    const labels = window.styleManager?.fonts?.categories || {};
    return Array.from( found ).sort().map( value => ( {
      value,
      label: labels[ value ]?.name || value.replace( /(^|-)([a-z])/g, ( m, sep, chr ) => ( sep ? ' ' : '' ) + chr.toUpperCase() ),
    } ) );
  }, [ catalog ] );

  const setCategory = value => {
    setCategoryState( value );
    storeFilter( value );
  };

  // A stored filter may not resolve on this site (collection without local
  // families, categories from another catalog) — fall back to "All fonts".
  const categoryIsValid = ! category || ( category.startsWith( 'picks:' )
    ? collections.some( collection => `picks:${ collection.key }` === category )
    : categories.some( entry => entry.value === category ) );
  const effectiveCategory = categoryIsValid ? category : '';

  // Group -> filtered fonts, flattened into windowable rows.
  const rows = useMemo( () => {
    const term = search.trim().toLowerCase();
    const matchesTerm = font => ! term
      || font.display.toLowerCase().includes( term )
      || font.family.toLowerCase().includes( term );

    // A Staff Picks collection renders as one flat, curated-order group.
    if ( effectiveCategory.startsWith( 'picks:' ) ) {
      const collection = collections.find( entry => `picks:${ entry.key }` === effectiveCategory );
      const fonts = ( collection ? collection.fonts : [] ).filter( matchesTerm );
      if ( ! fonts.length ) {
        return [];
      }
      return [
        { type: 'header', label: collection.label, count: fonts.length },
        ...fonts.map( font => ( { type: 'font', font } ) ),
      ];
    }

    const matches = font => {
      if ( effectiveCategory && font.category !== effectiveCategory ) {
        return false;
      }
      return matchesTerm( font );
    };

    const groups = { ...catalog };
    if ( recommended.length ) {
      groups.recommended = recommended.map( family => byFamily[ family ] ).filter( Boolean )
        .map( font => ( { ...font, group: 'recommended' } ) );
    }

    const flat = [];
    GROUP_ORDER.forEach( group => {
      const fonts = ( groups[ group ] || [] ).filter( matches );
      if ( ! fonts.length ) {
        return;
      }
      flat.push( { type: 'header', label: groupLabels[ group ], count: fonts.length } );
      fonts.forEach( font => flat.push( { type: 'font', font } ) );
    } );

    return flat;
  }, [ catalog, byFamily, collections, groupLabels, recommended, search, effectiveCategory ] );

  // Row offsets for the windowed rendering (headers and items differ in height).
  const { offsets, totalHeight } = useMemo( () => {
    let y = 0;
    const measured = rows.map( row => {
      const height = 'header' === row.type ? HEADER_HEIGHT : ITEM_HEIGHT;
      const top = y;
      y += height;
      return { top, height };
    } );
    return { offsets: measured, totalHeight: y };
  }, [ rows ] );

  const visibleRange = useMemo( () => {
    const from = Math.max( 0, scrollTop - OVERSCAN_PX );
    const to = scrollTop + LIST_HEIGHT + OVERSCAN_PX;
    let start = offsets.findIndex( offset => offset.top + offset.height >= from );
    if ( start < 0 ) {
      start = 0;
    }
    let end = start;
    while ( end < rows.length && offsets[ end ].top <= to ) {
      end++;
    }
    return [ start, end ];
  }, [ offsets, rows.length, scrollTop ] );

  // Reset the scroll and keyboard cursor when the filters change.
  useEffect( () => {
    if ( listRef.current ) {
      listRef.current.scrollTop = 0;
    }
    setScrollTop( 0 );
    setActiveIndex( -1 );
  }, [ search, effectiveCategory ] );

  // On mount, bring the selected family into view.
  useEffect( () => {
    const index = rows.findIndex( row => 'font' === row.type && row.font.family === selected );
    if ( index >= 0 && listRef.current ) {
      const top = Math.max( 0, offsets[ index ].top - LIST_HEIGHT / 2 + ITEM_HEIGHT );
      listRef.current.scrollTop = top;
      setScrollTop( top );
      setActiveIndex( index );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [] );

  // Lazy-load preview faces for the rendered slice, debounced against scroll.
  useEffect( () => {
    window.clearTimeout( loadTimer.current );
    loadTimer.current = window.setTimeout( () => {
      const slice = rows.slice( visibleRange[ 0 ], visibleRange[ 1 ] )
        .filter( row => 'font' === row.type )
        .map( row => row.font );
      loadPreviewFonts( slice );
    }, 120 );
    return () => window.clearTimeout( loadTimer.current );
  }, [ rows, visibleRange ] );

  const scrollRowIntoView = index => {
    const list = listRef.current;
    if ( ! list || ! offsets[ index ] ) {
      return;
    }
    const { top, height } = offsets[ index ];
    if ( top < list.scrollTop ) {
      list.scrollTop = top;
    } else if ( top + height > list.scrollTop + LIST_HEIGHT ) {
      list.scrollTop = top + height - LIST_HEIGHT;
    }
  };

  const moveActive = direction => {
    let index = activeIndex;
    for ( let i = 0; i < rows.length; i++ ) {
      index += direction;
      if ( index < 0 || index >= rows.length ) {
        return;
      }
      if ( 'font' === rows[ index ].type ) {
        setActiveIndex( index );
        scrollRowIntoView( index );
        return;
      }
    }
  };

  const onSearchKeyDown = event => {
    if ( 'ArrowDown' === event.key ) {
      event.preventDefault();
      moveActive( 1 );
    } else if ( 'ArrowUp' === event.key ) {
      event.preventDefault();
      moveActive( -1 );
    } else if ( 'Enter' === event.key ) {
      event.preventDefault();
      const row = rows[ activeIndex ];
      if ( row && 'font' === row.type ) {
        onPick( row.font.family );
      }
    }
  };

  return (
    <div className="sm-font-picker">
      <div className="sm-font-picker__filters">
        <SearchControl
          __nextHasNoMarginBottom
          label={ __( 'Search fonts', '__plugin_txtd' ) }
          placeholder={ __( 'Search fonts', '__plugin_txtd' ) }
          value={ search }
          onChange={ setSearch }
          onKeyDown={ onSearchKeyDown }
        />
        { ( categories.length > 1 || collections.length > 0 ) && (
          <SelectControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            aria-label={ __( 'Filter fonts', '__plugin_txtd' ) }
            value={ effectiveCategory }
            onChange={ setCategory }
          >
            <option value="">{ __( 'All fonts', '__plugin_txtd' ) }</option>
            { collections.length > 0 && (
              <optgroup label={ __( 'Staff Picks', '__plugin_txtd' ) }>
                { collections.map( collection => (
                  <option key={ collection.key } value={ `picks:${ collection.key }` }>
                    { collection.label }
                  </option>
                ) ) }
              </optgroup>
            ) }
            { categories.length > 1 && (
              <optgroup label={ __( 'Styles', '__plugin_txtd' ) }>
                { categories.map( entry => (
                  <option key={ entry.value } value={ entry.value }>{ entry.label }</option>
                ) ) }
              </optgroup>
            ) }
          </SelectControl>
        ) }
      </div>
      <div
        className="sm-font-picker__list"
        style={ { height: LIST_HEIGHT } }
        ref={ listRef }
        onScroll={ event => setScrollTop( event.currentTarget.scrollTop ) }
        role="listbox"
        aria-label={ __( 'Font families', '__plugin_txtd' ) }
      >
        { rows.length ? (
          <div style={ { height: totalHeight, position: 'relative' } }>
            { rows.slice( visibleRange[ 0 ], visibleRange[ 1 ] ).map( ( row, sliceIndex ) => {
              const index = visibleRange[ 0 ] + sliceIndex;
              const style = {
                position: 'absolute',
                top: offsets[ index ].top,
                height: offsets[ index ].height,
                left: 0,
                right: 0,
              };

              if ( 'header' === row.type ) {
                return (
                  <div key={ `header-${ row.label }` } className="sm-font-picker__group" style={ style }>
                    <span>{ row.label }</span>
                    <span className="sm-font-picker__group-count">{ row.count }</span>
                  </div>
                );
              }

              const isSelected = row.font.family === selected;
              return (
                <div
                  key={ `${ row.font.group }-${ row.font.family }` }
                  className={ `sm-font-picker__item${ isSelected ? ' is-selected' : '' }${ index === activeIndex ? ' is-active' : '' }` }
                  style={ style }
                  role="option"
                  aria-selected={ isSelected }
                  onMouseDown={ event => {
                    event.preventDefault();
                    onPick( row.font.family );
                  } }
                  onMouseMove={ () => index !== activeIndex && setActiveIndex( index ) }
                >
                  <span className="sm-font-picker__preview" style={ { fontFamily: previewFontStack( row.font ) } }>
                    { row.font.display }
                  </span>
                  { staffPickFamilies.has( row.font.family ) && (
                    <span className="sm-font-picker__pick" title={ __( 'Staff pick', '__plugin_txtd' ) } aria-hidden="true">★</span>
                  ) }
                  { isSelected && <span className="sm-font-picker__check">{ checkIcon }</span> }
                </div>
              );
            } ) }
          </div>
        ) : (
          <div className="sm-font-picker__empty">{ __( 'No fonts found.', '__plugin_txtd' ) }</div>
        ) }
      </div>
    </div>
  );
};

/**
 * The font family selector: a select-like button opening the picker popover.
 */
export const FontFamilyControl = ( { label, family, recommended, onPick } ) => {
  const { Popover } = wp.components;
  const { useState, useEffect, useRef } = wp.element;
  const [ isOpen, setIsOpen ] = useState( false );
  const buttonRef = useRef( null );

  const details = family ? getFontDetails( family ) : false;
  const display = ( details && details.family_display ) || family || '';
  const fontType = family ? determineFontType( family ) : 'system_font';
  const stack = 'system_font' === fontType
    ? ( ( details && details.fallback_stack ) || 'inherit' )
    : quoteFamily( family || '' ) + ( details && details.fallback_stack ? `, ${ details.fallback_stack }` : '' );

  // Make sure the button's own preview face is available.
  useEffect( () => {
    if ( family && details ) {
      loadPreviewFonts( [ {
        family,
        group: fontType.replace( '_font', '' ),
        src: details.src || false,
        fallback: details.fallback_stack || '',
      } ] );
    }
  }, [ family ] );

  return (
    <div className="sm-native-font__family">
      { label && <span className="sm-native-font__family-label">{ label }</span> }
      <button
        type="button"
        className="sm-native-font__family-button"
        onClick={ () => setIsOpen( ! isOpen ) }
        aria-expanded={ isOpen }
        ref={ buttonRef }
      >
        <span className="sm-native-font__family-name" style={ { fontFamily: stack } }>{ display }</span>
        { chevronIcon }
      </button>
      { isOpen && (
        <Popover
          className="sm-font-picker__popover"
          anchor={ buttonRef.current }
          placement="bottom-start"
          offset={ 4 }
          focusOnMount="firstElement"
          onClose={ () => setIsOpen( false ) }
        >
          <FontFamilyList
            selected={ family }
            recommended={ recommended }
            onPick={ picked => {
              setIsOpen( false );
              onPick( picked );
            } }
          />
        </Popover>
      ) }
    </div>
  );
};

/**
 * Select a family through a rendered legacy font control. Both the Usage skin
 * and block-inspector shortcuts call this function, so normalization and live
 * preview stay on the original engine path.
 */
export const pickFontFamily = ( root, settingId, family ) => applyFontFamilySelection( {
  root,
  settingId,
  family,
  ensureOption: ensureFontFamilyOption,
  dispatchChange: ( select, picked ) => {
    const $select = $( select );
    $select.val( picked ).data( 'touched', true );
    $select.trigger( 'change' );
  },
} );

/**
 * Read the current setting value entry, unwrapping { value, unit } shapes.
 */
const numericValue = raw => {
  if ( raw === undefined || raw === null || raw === '' ) {
    return undefined;
  }
  const value = 'object' === typeof raw ? raw.value : raw;
  const parsed = Number( value );
  return isNaN( parsed ) ? undefined : parsed;
};

/**
 * Collect the hidden subfield inputs of a legacy font control, keyed by their
 * value entry, together with the PHP-rendered labels/attributes.
 */
const readSubfields = li => {
  const subfields = {};

  li.querySelectorAll( '.font-options__options-list [data-value_entry]' ).forEach( input => {
    const entry = input.getAttribute( 'data-value_entry' );
    if ( 'font_family' === entry ) {
      return;
    }

    const row = input.closest( 'li' );
    const label = row?.querySelector( 'label' )?.textContent.trim() || entry;

    if ( 'range' === input.type ) {
      subfields[ entry ] = {
        kind: 'range',
        input,
        label,
        min: input.min !== '' ? Number( input.min ) : 0,
        max: input.max !== '' ? Number( input.max ) : 100,
        step: input.step !== '' ? Number( input.step ) : 1,
        unit: input.getAttribute( 'unit' ) || '',
      };
    } else if ( 'SELECT' === input.tagName && 'font_variant' !== entry ) {
      subfields[ entry ] = {
        kind: 'select',
        input,
        label,
        options: Array.from( input.options ).map( option => ( { value: option.value, label: option.textContent } ) ),
      };
    } else if ( 'font_variant' === entry ) {
      subfields[ entry ] = { kind: 'variant', input, label };
    }
  } );

  return subfields;
};

const SUBFIELD_ORDER = [ 'font_variant', 'font_size', 'line_height', 'letter_spacing', 'text_align', 'text_transform', 'text_decoration' ];

/**
 * The native font control: family picker + native subfield controls, driving
 * the hidden legacy inputs.
 */
export const NativeFont = ( { settingId, li } ) => {
  const { RangeControl, SelectControl } = wp.components;
  const { useState, useEffect, useMemo } = wp.element;
  const { __ } = wp.i18n;

  const config = getConfig( settingId );
  const setting = wp.customize( settingId );
  const [ value, setValue ] = useState( setting ? { ...setting() } : {} );
  const [ isOpen, setIsOpen ] = useState( false );

  const subfields = useMemo( () => readSubfields( li ), [ li ] );
  const hasSubfields = Object.keys( subfields ).length > 0;

  const recommended = useMemo( () => {
    const groups = li.querySelectorAll( 'select.style-manager_font_family optgroup' );
    const group = Array.from( groups ).find( optgroup => 'recommended' === optgroup.label.trim().toLowerCase() );
    return group ? Array.from( group.querySelectorAll( 'option' ) ).map( option => option.value ) : [];
  }, [ li ] );

  const help = useMemo( () => {
    return li.querySelector( ':scope > .description' )?.textContent.trim() || '';
  }, [ li ] );

  useEffect( () => {
    if ( ! setting ) {
      return undefined;
    }
    const listener = newValue => setValue( { ...( newValue || {} ) } );
    setting.bind( listener );

    // The hidden select carries no Google options — give the current family a home.
    ensureFontFamilyOption( li.querySelector( 'select.style-manager_font_family' ), ( setting() || {} ).font_family );

    return () => setting.unbind( listener );
  }, [] );

  const family = value.font_family || '';
  const details = family ? getFontDetails( family ) : false;
  const familyDisplay = ( details && details.family_display ) || family;

  const pickFamily = picked => {
    // The legacy change pipeline refreshes the variant options, serializes the
    // value onto the setting and reaches the preview + webfont loading.
    pickFontFamily( li, settingId, picked );
  };

  const applySubfield = ( entry, newValue ) => {
    const subfield = subfields[ entry ];
    if ( ! subfield ) {
      return;
    }
    const $input = $( subfield.input );
    $input.val( newValue === undefined ? '' : newValue ).data( 'touched', true );
    $input.trigger( 'change' );
  };

  const variants = ( details && Array.isArray( details.variants ) ) ? details.variants.map( String ) : [];
  const showVariant = !! subfields.font_variant && variants.length > 1;

  const renderSubfield = entry => {
    const subfield = subfields[ entry ];
    if ( ! subfield ) {
      return null;
    }

    if ( 'variant' === subfield.kind ) {
      if ( ! showVariant ) {
        return null;
      }
      return (
        <SelectControl
          key={ entry }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={ __( 'Appearance', '__plugin_txtd' ) }
          value={ String( value.font_variant ?? '' ) }
          options={ [
            { value: '', label: variantLabel( '' ) },
            ...variants.map( variant => ( { value: variant, label: variantLabel( variant ) } ) ),
          ] }
          onChange={ newValue => applySubfield( entry, newValue ) }
        />
      );
    }

    if ( 'range' === subfield.kind ) {
      const current = numericValue( value[ entry ] );
      const label = subfield.unit ? `${ subfield.label } (${ subfield.unit })` : subfield.label;
      return (
        <RangeControl
          key={ entry }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
          label={ label }
          value={ current }
          min={ subfield.min }
          max={ subfield.max }
          step={ subfield.step }
          withInputField
          onChange={ newValue => applySubfield( entry, newValue ) }
        />
      );
    }

    return (
      <SelectControl
        key={ entry }
        __nextHasNoMarginBottom
        __next40pxDefaultSize
        label={ subfield.label }
        value={ String( value[ entry ] ?? subfield.input.value ?? '' ) }
        options={ subfield.options }
        onChange={ newValue => applySubfield( entry, newValue ) }
      />
    );
  };

  // Family-only fields (the Fine-tune tab) render as a single picker row;
  // fields with subfields (the Usage tab) get a disclosure that expands the
  // full editing panel inline — no floating popup.
  if ( ! hasSubfields ) {
    return (
      <div className="sm-native-font sm-native-font--family-only">
        <FontFamilyControl label={ config.label } family={ family } recommended={ recommended } onPick={ pickFamily } />
        { help && <p className="sm-native-font__help">{ help }</p> }
      </div>
    );
  }

  return (
    <div className={ `sm-native-font${ isOpen ? ' is-open' : '' }` }>
      <button
        type="button"
        className="sm-native-font__row"
        onClick={ () => setIsOpen( ! isOpen ) }
        aria-expanded={ isOpen }
      >
        <span className="sm-native-font__label">{ config.label }</span>
        <span className="sm-native-font__value">{ familyDisplay }</span>
        { chevronIcon }
      </button>
      { isOpen && (
        <div className="sm-native-font__panel">
          <FontFamilyControl label={ __( 'Font', '__plugin_txtd' ) } family={ family } recommended={ recommended } onPick={ pickFamily } />
          { SUBFIELD_ORDER.map( renderSubfield ) }
        </div>
      ) }
      { help && <p className="sm-native-font__help">{ help }</p> }
    </div>
  );
};
