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
        <div className="sm-native-range">
          <RangeControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={ config.label }
            help={ stripHtml( config.desc ) || undefined }
            value={ value === '' || value === undefined ? undefined : Number( value ) }
            onChange={ onChange }
            min={ attrs.min !== undefined ? Number( attrs.min ) : 0 }
            max={ attrs.max !== undefined ? Number( attrs.max ) : 100 }
            step={ attrs.step !== undefined ? Number( attrs.step ) : 1 }
            withInputField
          />
        </div>
      ) }
    </BoundControl>
  );
};

/**
 * --- Section reset menu (core's Query Loop / ToolsPanel pattern) ---
 * A 3-dot menu per section panel: each resettable field shows RESET when
 * modified (click to reset) or a check at default, plus Reset all.
 */
const looseEquals = ( a, b ) => {
  const noValue = v => false === v || null === v || undefined === v || '' === v || ( 'number' === typeof v && isNaN( v ) );
  if ( noValue( a ) && noValue( b ) ) {
    return true;
  }
  if ( a === b ) {
    return true;
  }
  if ( ( 'boolean' === typeof a || 'boolean' === typeof b ) && !! a === !! ( '0' === b ? false : b ) && !! b === !! ( '0' === a ? false : a ) ) {
    return true;
  }
  if ( '' !== a && '' !== b && isFinite( Number( a ) ) && isFinite( Number( b ) ) && Number( a ) === Number( b ) ) {
    return true;
  }
  return String( a ) === String( b );
};

const RESETTABLE_TYPES = [ 'range', 'sm_toggle', 'sm_switch', 'radio', 'sm_radio', 'select', 'select_color', 'radio_html' ];

export const getResettableSettings = section => {
  return section.controls
    .map( control => controlToSettingId( control.id ) )
    .map( settingId => ( { settingId, config: getConfig( settingId ) } ) )
    .filter( ( { config } ) => config
      && RESETTABLE_TYPES.includes( config.type )
      && config.default !== undefined
      && config.label )
    .map( ( { settingId, config } ) => ( {
      settingId,
      label: stripHtml( String( config.label ) ),
      defaultValue: config.default,
    } ) );
};

export const PanelResetMenu = ( { items, groupLabel } ) => {
  const { DropdownMenu, MenuGroup, MenuItem } = wp.components;
  const { useState, useEffect } = wp.element;
  const { __ } = wp.i18n;
  const [ , setTick ] = useState( 0 );

  useEffect( () => {
    const listener = () => setTick( tick => tick + 1 );
    wp.customize.bind( 'sm:setting-change', listener );
    return () => wp.customize.unbind( 'sm:setting-change', listener );
  }, [] );

  const entries = items.map( item => {
    const setting = wp.customize( item.settingId );
    return {
      ...item,
      modified: setting ? ! looseEquals( setting(), item.defaultValue ) : false,
    };
  } );
  const anyModified = entries.some( entry => entry.modified );

  const reset = entry => {
    wp.customize( entry.settingId, setting => setting.set( entry.defaultValue ) );
  };

  // Core's moreVertical icon (the Query Loop ToolsPanel uses this).
  const moreVerticalIcon = (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
      <path d="M13 19h-2v-2h2v2zm0-6h-2v-2h2v2zm0-6h-2V5h2v2z" />
    </svg>
  );

  return (
    <DropdownMenu
      icon={ moreVerticalIcon }
      label={ __( 'Section options', '__plugin_txtd' ) }
      className="sm-se-panel-tools__toggle"
      popoverProps={ { placement: 'left-start', offset: 12 } }
    >
      { ( { onClose } ) => (
        <>
          <MenuGroup label={ groupLabel }>
            { entries.map( entry => (
              <MenuItem
                key={ entry.settingId }
                onClick={ () => {
                  if ( entry.modified ) {
                    reset( entry );
                  }
                } }
              >
                <span className="sm-reset-menu__row">
                  <span>{ entry.label }</span>
                  { entry.modified
                    ? <span className="sm-reset-menu__action">{ __( 'Reset', '__plugin_txtd' ) }</span>
                    : <span className="dashicons dashicons-yes sm-reset-menu__check" /> }
                </span>
              </MenuItem>
            ) ) }
          </MenuGroup>
          <MenuGroup>
            <MenuItem
              disabled={ ! anyModified }
              onClick={ () => {
                entries.forEach( entry => entry.modified && reset( entry ) );
                onClose();
              } }
            >
              { __( 'Reset all', '__plugin_txtd' ) }
            </MenuItem>
          </MenuGroup>
        </>
      ) }
    </DropdownMenu>
  );
};

const NativeToggle = ( { settingId, overrides } ) => {
  const { ToggleControl } = wp.components;
  const config = getConfig( settingId );
  const label = overrides?.label || config.label;
  const help = overrides?.help !== undefined ? overrides.help : ( stripHtml( config.desc ) || undefined );

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => (
        <ToggleControl
          __nextHasNoMarginBottom
          label={ label }
          help={ help }
          checked={ !! value && '0' !== value }
          onChange={ onChange }
        />
      ) }
    </BoundControl>
  );
};

// A master on/off switch backed by a setting whose "on"/"off" are arbitrary
// values rather than a boolean — e.g. Site Frame's style (editorial / none).
const NativeMasterToggle = ( { settingId, label, onValue, offValue } ) => {
  const { ToggleControl } = wp.components;

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => (
        <ToggleControl
          __nextHasNoMarginBottom
          label={ label }
          checked={ String( value ) === String( onValue ) }
          onChange={ checked => onChange( checked ? onValue : offValue ) }
        />
      ) }
    </BoundControl>
  );
};

const NativeRadio = ( { settingId, overrides } ) => {
  const { RadioControl } = wp.components;
  const ToggleGroupControl = wp.components.__experimentalToggleGroupControl || wp.components.ToggleGroupControl;
  const ToggleGroupControlOption = wp.components.__experimentalToggleGroupControlOption || wp.components.ToggleGroupControlOption;
  const config = { ...getConfig( settingId ) };
  if ( overrides?.label ) {
    config.label = overrides.label;
  }
  if ( overrides?.help !== undefined ) {
    config.desc = overrides.help;
  }
  const choices = Object.entries( config.choices || {} ).map( ( [ v, label ] ) => ( { value: v, label: stripHtml( String( label ) ) } ) );
  const useGroup = ToggleGroupControl && choices.length <= 4;

  return (
    <BoundControl settingId={ settingId }>
      { ( value, onChange ) => useGroup ? (
        <ToggleGroupControl
          __nextHasNoMarginBottom
          __next40pxDefaultSize
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
          __next40pxDefaultSize
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

/**
 * "Find by voice" — the voice tuner as a compact core-style filter panel
 * attached to the font palette list (replaces the floating accordion in the
 * Site Editor; the engine — scheduler, fit plan, badges — stays untouched
 * and reacts to the same four settings).
 */
const VOICE_DIMENSIONS = [ 'sm_voice_formality', 'sm_voice_energy', 'sm_voice_warmth', 'sm_voice_tradition' ];

export const VoiceTunerPanel = () => {
  const { Button } = wp.components;
  const ToggleGroupControl = wp.components.__experimentalToggleGroupControl || wp.components.ToggleGroupControl;
  const ToggleGroupControlOption = wp.components.__experimentalToggleGroupControlOption || wp.components.ToggleGroupControlOption;
  const { useState, useEffect } = wp.element;
  const { __ } = wp.i18n;
  const [ open, setOpen ] = useState( false );
  const [ , setTick ] = useState( 0 );

  useEffect( () => {
    const listener = () => setTick( tick => tick + 1 );
    wp.customize.bind( 'sm:setting-change', listener );
    return () => wp.customize.unbind( 'sm:setting-change', listener );
  }, [] );

  const entries = VOICE_DIMENSIONS
    .map( id => ( { id, config: getConfig( id ) } ) )
    .filter( entry => entry.config && wp.customize( entry.id ) )
    .map( entry => ( { ...entry, value: String( wp.customize( entry.id )() || 'balanced' ) } ) );

  if ( ! entries.length || ! ToggleGroupControl ) {
    return null;
  }

  const tuned = entries.some( entry => 'balanced' !== entry.value );

  return (
    <div className="sm-voice-panel">
      <button type="button" className="sm-voice-panel__header" onClick={ () => setOpen( ! open ) } aria-expanded={ open }>
        <span>{ __( 'Tune by voice', '__plugin_txtd' ) }</span>
        <span className={ `dashicons dashicons-arrow-${ open ? 'up' : 'down' }-alt2` } aria-hidden="true" />
      </button>
      { open && (
        <div className="sm-voice-panel__body">
          { entries.map( entry => (
            <div className="sm-voice-panel__row" key={ entry.id }>
              <span className="sm-voice-panel__label">{ stripHtml( String( entry.config.label || '' ) ) }</span>
              <ToggleGroupControl
                __nextHasNoMarginBottom
                __next40pxDefaultSize
                hideLabelFromVision
                label={ stripHtml( String( entry.config.label || '' ) ) }
                value={ entry.value }
                isBlock
                onChange={ value => wp.customize( entry.id, setting => setting.set( value ) ) }
              >
                { Object.entries( entry.config.choices || {} ).map( ( [ value, label ] ) => (
                  <ToggleGroupControlOption key={ value } value={ value } label={ stripHtml( String( label ) ) } />
                ) ) }
              </ToggleGroupControl>
            </div>
          ) ) }
        </div>
      ) }
      { tuned && (
        <div className="sm-voice-panel__hint">
          { __( 'Palettes are sorted by voice fit.', '__plugin_txtd' ) }
          <Button
            variant="link"
            size="small"
            onClick={ () => entries.forEach( entry => wp.customize( entry.id, setting => setting.set( 'balanced' ) ) ) }
          >
            { __( 'Reset', '__plugin_txtd' ) }
          </Button>
        </div>
      ) }
    </div>
  );
};

const COMPONENTS = {
  range: NativeRange,
  sm_toggle: NativeToggle,
  radio: NativeRadio,
  select: NativeSelect,
};

/**
 * Site Editor copy polish (presentation only — source strings stay in the
 * theme/plugin config and are batched separately).
 */
const getCopyOverride = settingId => {
  const { __ } = wp.i18n;
  const overrides = {
    sm_collection_title_position: {
      label: __( 'Collection title position', '__plugin_txtd' ),
      help: __( '“Sideways” rotates collection titles along the left edge for an editorial look.', '__plugin_txtd' ),
    },
    sm_collection_hover_effect: {
      label: __( 'Collection hover effect', '__plugin_txtd' ),
      help: __( 'The effect shown when hovering a collection card’s media.', '__plugin_txtd' ),
    },
  };

  return overrides[ settingId ] || null;
};

/**
 * The setting id for a control id ({setting}_control suffix).
 */
const controlToSettingId = controlId => controlId.replace( /_control$/, '' );

const controlLiId = controlId => `customize-control-${ controlId.replace( /\[/g, '-' ).replace( /\]/g, '' ) }`;

/**
 * Render a master on/off switch up into a section's title-row actions and hide
 * the control's own row. The React root just moves (the binding survives), and
 * a conditional Preview button is shown/hidden with the on/off state.
 */
const renderMasterSwitchInHeader = ( introLi, li, switchElement, settingId, isOn ) => {
  const headerActions = introLi?.querySelector( '.sm-se-section-head__actions' );
  if ( ! headerActions ) {
    return false;
  }

  const target = document.createElement( 'div' );
  target.className = 'sm-native-control sm-se-section-head__switch';
  headerActions.appendChild( target );
  ReactDOM.render( switchElement, target );

  li.style.display = 'none';

  const conditionalPreview = headerActions.querySelector( '.sm-se-group__preview--conditional' );
  if ( conditionalPreview ) {
    const setting = wp.customize( settingId );
    const sync = value => {
      conditionalPreview.style.display = isOn( value ) ? '' : 'none';
    };
    setting.bind( sync );
    sync( setting() );
  }

  return true;
};

export const mountNativeControls = ( eng, payload ) => {
  // Master toggles backed by a non-boolean setting (e.g. Site Frame's style):
  // setting id -> { on, off, introControlId }.
  const masterToggleMap = {};
  Object.values( payload.sectionGroupHeaders || {} ).forEach( markers => {
    ( markers || [] ).forEach( marker => {
      if ( marker.toggle && marker.toggle.setting ) {
        masterToggleMap[ marker.toggle.setting ] = {
          on: marker.toggle.on,
          off: marker.toggle.off,
          introControlId: `${ marker.before }_control`,
        };
      }
    } );
  } );

  payload.structure.sections.forEach( section => {
    section.controls.forEach( ( control, index ) => {
      const settingId = controlToSettingId( control.id );
      const li = eng.root.querySelector( `#${ CSS.escape( controlLiId( control.id ) ) }` );
      if ( ! li || li.querySelector( '.sm-native-control' ) || ! wp.customize( settingId ) ) {
        return;
      }

      // Master switch backed by a mapped (non-boolean) setting — e.g. Site
      // Frame's style. Handled before the component lookup so it applies even
      // to control types native-controls otherwise leaves alone (sm_radio):
      // render a mapped on/off switch into the title row and drop this row.
      const mapped = masterToggleMap[ settingId ];
      if ( mapped ) {
        const introLi = eng.root.querySelector( `#${ CSS.escape( controlLiId( mapped.introControlId ) ) }` );
        const introTitle = introLi?.querySelector( '.sm-se-section-head__title' )?.textContent.trim() || '';
        renderMasterSwitchInHeader(
          introLi,
          li,
          <NativeMasterToggle settingId={ settingId } label={ introTitle } onValue={ mapped.on } offValue={ mapped.off } />,
          settingId,
          value => String( value ) === String( mapped.on )
        );
        return;
      }

      const Component = COMPONENTS[ control.type ];
      if ( ! Component || ! getConfig( settingId ) ) {
        return;
      }

      let overrides = getCopyOverride( settingId );

      // A toggle that follows an html intro is the "intro card + Enable X"
      // pattern. Three treatments:
      //   • intro is a group header (sm-se-group-head) → this is the section's
      //     master switch: render it (label hidden) up on the title row and
      //     drop this row (see markGroupSections).
      //   • intro heads several sibling toggles (e.g. "Color promotion" → Pure
      //     white + Pure black) → it's a plain sub-header, not a single toggle's
      //     label: leave the intro visible and keep each toggle's own label.
      //   • otherwise → collapse the two into one core-style row: the intro
      //     hands its title + description to the toggle and disappears.
      if ( 'sm_toggle' === control.type && index > 0 && 'html' === section.controls[ index - 1 ].type ) {
        const introLi = eng.root.querySelector( `#${ CSS.escape( controlLiId( section.controls[ index - 1 ].id ) ) }` );
        // The title may have been wrapped in a head-row span (which excludes the
        // actions like the Preview button) — read that so the label is clean.
        const introTitleEl = introLi?.querySelector( '.sm-se-section-head__title' )
          || introLi?.querySelector( '.customize-control-title' );
        const introTitle = introTitleEl?.textContent.trim();
        // When the intro is immediately followed by more than one toggle, it
        // heads a group rather than labelling a single Enable toggle.
        const introHeadsGroup = 'sm_toggle' === section.controls[ index + 1 ]?.type;

        if ( introLi && introLi.classList.contains( 'sm-se-group-head' ) ) {
          renderMasterSwitchInHeader(
            introLi,
            li,
            <Component settingId={ settingId } li={ li } overrides={ { label: introTitle || '', help: undefined } } />,
            settingId,
            value => !! value && '0' !== value
          );
          return;
        } else if ( introTitle && ! introHeadsGroup ) {
          overrides = {
            label: introTitle,
            help: introLi.querySelector( '.customize-control-description' )?.textContent.trim() || undefined,
          };
          introLi.classList.add( 'sm-se-control-merged' );
        }
      }

      Array.from( li.children ).forEach( child => {
        child.style.display = 'none';
      } );

      const target = document.createElement( 'div' );
      target.className = 'sm-native-control';
      li.insertBefore( target, li.firstChild );

      ReactDOM.render( <Component settingId={ settingId } li={ li } overrides={ overrides } />, target );
    } );
  } );
};
