/* global jQuery */
import $ from 'jquery';
import _ from 'lodash';

/**
 * A standalone, minimal implementation of the `wp.customize` API surface the
 * Style Manager engine uses, so the original Customizer JS modules can run
 * unchanged outside customize.php (e.g. in the Site Editor).
 *
 * Covered surface:
 * - api( id ) / api( id, ...callbacks ) with deferred resolution
 * - callable Value instances: setting(), setting( value ), get/set/bind/unbind, _dirty
 * - api.bind / api.trigger for 'ready' / 'saved'
 * - api.settings (the _wpCustomizeSettings shape parts SM reads)
 * - api.section / api.panel / api.control registries with deferred callbacks
 * - api.previewer / api.previewedDevice stubs
 * - element <-> setting linking via [data-customize-setting-link]
 */

const createEmitter = () => {
  const topics = {};
  const fired = {};

  return {
    bind( topic, callback ) {
      if ( fired[ topic ] ) {
        try {
          callback.apply( null, fired[ topic ] );
        } catch ( e ) {
          console.error( e );
        }
      }
      topics[ topic ] = topics[ topic ] || [];
      topics[ topic ].push( callback );
    },
    unbind( topic, callback ) {
      if ( ! topics[ topic ] ) {
        return;
      }
      const index = topics[ topic ].indexOf( callback );
      if ( index > -1 ) {
        topics[ topic ].splice( index, 1 );
      }
    },
    trigger( topic, ...args ) {
      // 'ready'-like topics replay for late subscribers, mirroring how the
      // Customizer resolves its ready deferred.
      fired[ topic ] = args;
      ( topics[ topic ] || [] ).slice().forEach( callback => {
        try {
          callback.apply( null, args );
        } catch ( e ) {
          console.error( e );
        }
      } );
    },
  };
};

/**
 * Create the callback surface exposed by a core wp.customize.Value.
 */
const createCallbacks = () => {
  const items = [];

  return {
    add( ...callbacks ) {
      callbacks.filter( callback => 'function' === typeof callback ).forEach( callback => items.push( callback ) );
      return this;
    },
    remove( ...callbacks ) {
      callbacks.forEach( callback => {
        let index = items.indexOf( callback );
        while ( index > -1 ) {
          items.splice( index, 1 );
          index = items.indexOf( callback );
        }
      } );
      return this;
    },
    fireWith( context, args = [] ) {
      // Isolate listeners: one throwing callback must not silence the rest
      // (e.g. the engine's own field-sync listener vs the native-skin
      // re-render listener bound after it).
      items.slice().forEach( callback => {
        try {
          callback.apply( context, args );
        } catch ( e ) {
          console.error( e );
        }
      } );
      return this;
    },
  };
};

/**
 * Create a callable Value, compatible with wp.customize settings:
 * value() -> get, value( to ) -> set, plus get/set/bind/unbind/id/_dirty.
 */
export const createValue = ( id, initial, notify ) => {
  const callbacks = createCallbacks();
  let current = initial;

  const value = function( ...args ) {
    if ( args.length ) {
      return value.set( args[ 0 ] );
    }
    return value.get();
  };

  value.id = id;
  value._dirty = false;
  value.callbacks = callbacks;

  value.get = () => current;

  value.set = to => {
    const from = current;

    // Mirror wp.customize.Value.set(): bail when null or deep-equal —
    // object values (fonts) churn with fresh identities but equal content.
    if ( null === to || _.isEqual( from, to ) ) {
      return value;
    }

    current = to;
    value._dirty = true;

    callbacks.fireWith( value, [ to, from ] );

    if ( typeof notify === 'function' ) {
      notify( to, from );
    }

    return value;
  };

  value.bind = callback => {
    callbacks.add( callback );
    return value;
  };

  value.unbind = callback => {
    callbacks.remove( callback );
    return value;
  };

  return value;
};

/**
 * A callable registry for settings/sections/panels/controls:
 * registry( id ) -> instance | undefined
 * registry( id, ...callbacks ) -> defer until the instance is added.
 */
const createRegistry = () => {
  const instances = {};
  const pending = {};

  const registry = function( id, ...callbacks ) {
    if ( ! callbacks.length ) {
      return instances[ id ];
    }

    callbacks.forEach( callback => {
      if ( instances[ id ] ) {
        try {
          callback( instances[ id ] );
        } catch ( e ) {
          console.error( `Style Manager: error in deferred callback for "${ id }"`, e );
        }
      } else {
        pending[ id ] = pending[ id ] || [];
        pending[ id ].push( callback );
      }
    } );

    return instances[ id ];
  };

  registry.add = ( id, instance ) => {
    instances[ id ] = instance;
    ( pending[ id ] || [] ).forEach( callback => {
      try {
        callback( instance );
      } catch ( e ) {
        console.error( `Style Manager: error in deferred callback for "${ id }"`, e );
      }
    } );
    delete pending[ id ];
    return instance;
  };

  registry.has = id => !! instances[ id ];
  registry.each = callback => Object.keys( instances ).forEach( id => callback( instances[ id ], id ) );

  return registry;
};

/**
 * Create a lightweight section/panel object compatible with the bits SM uses:
 * expanded Value, expand/collapse, focus, container.
 */
export const createContainerObject = ( id, params = {} ) => {
  const instance = {
    id,
    params,
    container: params.container || null,
    contentContainer: params.container || null,
    expanded: createValue( `${ id }::expanded`, !! params.expanded ),
    active: createValue( `${ id }::active`, true ),
  };

  instance.expand = () => instance.expanded.set( true );
  instance.collapse = () => instance.expanded.set( false );
  instance.focus = () => {
    instance.expanded.set( true );
    if ( typeof params.onFocus === 'function' ) {
      params.onFocus( instance );
    }
  };

  return instance;
};

/**
 * Build the api object.
 *
 * @param {Object} customizeSettings The `_wpCustomizeSettings`-shaped data:
 *                                   { settings: { id: { value, transport, type, connected_fields } } }
 */
export const createCustomizeApi = customizeSettings => {
  const emitter = createEmitter();
  const settingsRegistry = createRegistry();

  const api = function( id, ...callbacks ) {
    return settingsRegistry( id, ...callbacks );
  };

  api.bind = emitter.bind;
  api.unbind = emitter.unbind;
  api.trigger = emitter.trigger;

  // The parts of _wpCustomizeSettings the SM engine reads (settings configs).
  // Note: no `google_fonts_opts` here — the Customizer inlines a ~96 KB
  // server-rendered <option> blob to fill every font select with the full
  // Google catalog, but the Site Editor's native font picker reads the
  // styleManager.fonts catalog directly, so the selects stay lean.
  api.settings = customizeSettings;

  api.add = ( id, initialValue ) => settingsRegistry.add(
    id,
    createValue( id, initialValue, ( to, from ) => emitter.trigger( 'sm:setting-change', id, to, from ) )
  );
  api.has = settingsRegistry.has;
  api.each = settingsRegistry.each;

  // Create every setting upfront from the server-provided data.
  Object.keys( customizeSettings.settings || {} ).forEach( id => {
    api.add( id, customizeSettings.settings[ id ].value );
  } );

  api.section = createRegistry();
  api.panel = createRegistry();
  api.control = createRegistry();

  // Preview machinery does not exist outside the Customizer: stub it.
  api.previewer = {
    bind() {},
    unbind() {},
    send() {},
    refresh() {},
    trigger() {},
  };
  api.previewedDevice = createValue( 'previewedDevice', 'desktop' );

  /**
   * Collect dirty setting values (mirrors wp.customize.dirtyValues()).
   */
  api.dirtyValues = () => {
    const dirty = {};
    settingsRegistry.each( ( setting, id ) => {
      if ( setting._dirty ) {
        dirty[ id ] = setting.get();
      }
    } );
    return dirty;
  };

  /**
   * Mark every setting clean (after a successful save).
   */
  api.markClean = () => {
    settingsRegistry.each( setting => {
      setting._dirty = false;
    } );
  };

  /**
   * Two-way link all [data-customize-setting-link] elements under `root` to
   * their settings — the role customize-controls' Element/links() plays in
   * the Customizer pane.
   *
   * Uses jQuery events so jQuery-triggered changes (select2, SM controls)
   * are caught as well.
   */
  api.linkElements = root => {
    const $root = $( root );

    $root.find( '[data-customize-setting-link]' ).each( ( i, element ) => {
      const $element = $( element );

      if ( $element.data( 'smSettingLinked' ) ) {
        return;
      }
      $element.data( 'smSettingLinked', true );

      const settingId = $element.attr( 'data-customize-setting-link' );

      api( settingId, setting => {
        const type = ( $element.attr( 'type' ) || '' ).toLowerCase();

        if ( 'radio' === type ) {
          // Initialize element from setting.
          $element.prop( 'checked', String( setting.get() ) === $element.val() );

          $element.on( 'change.smSettingLink', () => {
            if ( $element.prop( 'checked' ) ) {
              setting.set( $element.val() );
            }
          } );

          setting.bind( newValue => {
            const checked = String( newValue ) === $element.val();
            if ( $element.prop( 'checked' ) !== checked ) {
              $element.prop( 'checked', checked );
            }
          } );

          return;
        }

        if ( 'checkbox' === type ) {
          $element.prop( 'checked', !! setting.get() );

          $element.on( 'change.smSettingLink', () => {
            setting.set( $element.prop( 'checked' ) );
          } );

          setting.bind( newValue => {
            if ( $element.prop( 'checked' ) !== !! newValue ) {
              $element.prop( 'checked', !! newValue );
            }
          } );

          return;
        }

        // Selects, textareas, text/number/hidden/range inputs.
        const settingValue = setting.get();
        if ( null !== settingValue && undefined !== settingValue && 'object' !== typeof settingValue ) {
          $element.val( String( settingValue ) );
        }

        $element.on( 'change.smSettingLink input.smSettingLink', () => {
          const current = setting.get();
          // Object values (font configs) are serialized by their own control
          // JS (selfUpdateValue) — never overwrite them with an element string.
          if ( null !== current && 'object' === typeof current ) {
            return;
          }
          setting.set( $element.val() );
        } );

        setting.bind( newValue => {
          if ( 'object' === typeof newValue || $element.is( ':focus' ) ) {
            // Complex values (font configs) are managed by their own control JS;
            // and never fight the user while they type.
            return;
          }
          // Mirror wp.customize.Element: update without re-triggering 'change'.
          if ( $element.val() !== String( newValue ) ) {
            $element.val( newValue );
          }
        } );
      } );
    } );
  };

  return api;
};
