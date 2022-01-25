import $ from 'jquery';

const COLOR_SCHEME_BUTTON_SELECTOR = '.js-sm-dark-mode-toggle';
const STORAGE_ITEM = 'color-scheme-dark';
const TEMP_STORAGE_ITEM = 'color-scheme-dark-temp';

class DarkMode {

  constructor() {
    this.initialize();
  }

  initialize() {
    this.darkModeSetting = window.document.documentElement.dataset?.darkModeAdvanced;
    this.matchMedia = window.matchMedia( '(prefers-color-scheme: dark)' );
    this.storageItemKey = STORAGE_ITEM;
    this.callbacks = [];

    onReady( () => {

      if ( isCustomizePreview() || isLoggedIn() ) {
        localStorage.removeItem( TEMP_STORAGE_ITEM );
        this.storageItemKey = TEMP_STORAGE_ITEM;
      }

      this.initializeCustomizePreview();

      this.bindEvents();
      this.update();
    } );

  }

  initializeCustomizePreview() {

    const api = window.wp?.customize || window.parent?.wp?.customize;

    if ( ! api ) {
      return;
    }

    api( 'sm_dark_mode_advanced', setting => {
      setting.bind( ( newValue ) => {
        this.darkModeSetting = newValue;
        localStorage.removeItem( TEMP_STORAGE_ITEM );
        this.update();
      } );
    } );

  }

  bindEvents() {
    const toggles = window.document.querySelectorAll( COLOR_SCHEME_BUTTON_SELECTOR );

    toggles.forEach( toggle => {
      toggle.addEventListener( 'click', this.onClick.bind( this ) );
    } );

    this.matchMedia.addEventListener( 'change', () => {
      localStorage.removeItem( TEMP_STORAGE_ITEM );
      this.update();
    } );
  }

  bind( callback ) {
    const index = this.callbacks.indexOf( callback );

    if ( typeof callback !== "function" ) {
      return;
    }

    if ( index === -1 ) {
      this.callbacks.push( callback );
    }
  }

  unbind( callback ) {
    const index = this.callbacks.indexOf( callback );

    if ( index > -1 ) {
      this.callbacks.splice( index, 1 );
    }
  }

  onClick( event ) {
    event.preventDefault();
    localStorage.setItem( this.storageItemKey, !! this.isCompiledDark() ? 'light' : 'dark' );
    this.update();
  };

  isSystemDark() {
    let isDark = this.darkModeSetting === 'on';

    if ( this.darkModeSetting === 'auto' && this.matchMedia.matches ) {
      isDark = true;
    }

    return isDark;
  }

  isCompiledDark() {
    let isDark = this.isSystemDark();
    let colorSchemeStorageValue = localStorage.getItem( this.storageItemKey );

    if ( colorSchemeStorageValue !== null ) {
      isDark = colorSchemeStorageValue === 'dark';
    }

    return isDark;
  }

  update() {
    const isDark = this.isCompiledDark();

    this.callbacks.forEach( callback => {
      callback( isDark );
    } );

    if ( isDark ) {
      window.document.documentElement.classList.add( 'is-dark' );
    } else {
      window.document.documentElement.classList.remove( 'is-dark' );
    }
  }
}

function onReady( fn ) {
  if ( document.readyState != 'loading' ) {
    fn();
  } else {
    document.addEventListener( 'DOMContentLoaded', fn );
  }
}

function inIframe() {
  try {
    return window.self !== window.top;
  } catch (e) {
    return true;
  }
}

function isLoggedIn() {
  return window.document.body.classList.contains( 'logged-in' );
}

function isCustomizePreview() {
  return inIframe() && window?.parent?.wp?.customize;
}

export default new DarkMode();
