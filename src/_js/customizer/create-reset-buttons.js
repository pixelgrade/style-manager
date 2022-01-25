import $ from 'jquery';
import { apiSetSettingValue } from './utils';

export const createResetButtons = () => {
  const $document = $( document );
  const showResetButtons = $( 'button[data-action="reset_style_manager"]' ).length > 0;

  if ( showResetButtons ) {
    createResetPanelButtons();
    createResetSectionButtons();

    $document.on( 'click', '.js-reset-panel', onResetPanel );
    $document.on( 'click', '.js-reset-section', onResetSection );
    $document.on( 'click', '#customize-control-reset_style_manager button', onReset )
  }
};

function createResetPanelButtons() {

  $( '.panel-meta' ).each( function( i, obj ) {
    const $this = $( obj );
    const container = $this.parents( '.control-panel' );
    let id = container.attr( 'id' );

    if ( typeof id !== 'undefined' ) {
      id = id.replace( 'sub-accordion-panel-', '' );
      id = id.replace( 'accordion-panel-', '' );
      const $buttonWrapper = $( '<li class="customize-control customize-control-reset"></li>' );
      const $button = $( '<button class="button js-reset-panel" data-panel="' + id + '"></button>' );

      $button.text( styleManager.l10n.panelResetButton ).appendTo( $buttonWrapper );
      $this.parent().append( $buttonWrapper )
    }
  } )
}

function createResetSectionButtons() {
  $( '.accordion-section-content' ).each( function( el, key ) {
    const $this = $( this );
    const sectionID = $this.attr( 'id' );

    if ( _.isUndefined( sectionID ) || sectionID.indexOf( styleManager.config.options_name ) === - 1 ) {
      return
    }

    const id = sectionID.replace( 'sub-accordion-section-', '' );
    const $button = $( '<button class="button js-reset-section" data-section="' + id + '"></button>' );
    const $buttonWrapper = $( '<li class="customize-control customize-control-reset"></li>' );

    $button.text( styleManager.l10n.sectionResetButton );
    $buttonWrapper.append( $button );

    $this.append( $buttonWrapper )
  } )
}

function onReset( ev ) {
  ev.preventDefault();

  const iAgree = confirm( styleManager.l10n.resetGlobalConfirmMessage );

  if ( !iAgree ) {
    return
  }

  $.each( api.settings.controls, function( key, ctrl ) {
    const settingID = key.replace( '_control', '' );
    const setting = styleManager.config.settings[settingID];

    if ( !_.isUndefined( setting ) && !_.isUndefined( setting.default ) ) {
      apiSetSettingValue( settingID, setting.default )
    }
  } );

  api.previewer.save()
}

function onResetPanel( e ) {
  e.preventDefault();

  const panelID = $( this ).data( 'panel' ),
    panel = api.panel( panelID ),
    sections = panel.sections(),
    iAgree = confirm( styleManager.l10n.resetPanelConfirmMessage );

  if ( !iAgree ) {
    return
  }
  if ( sections.length > 0 ) {
    $.each( sections, function() {
      const controls = this.controls();

      if ( controls.length > 0 ) {
        $.each( controls, function( key, ctrl ) {
          const settingID = ctrl.id.replace( '_control', '' ),
            setting = styleManager.config.settings[settingID];

          if ( !_.isUndefined( setting ) && !_.isUndefined( setting.default ) ) {
            apiSetSettingValue( settingID, setting.default )
          }
        } )
      }
    } )
  }
}

function onResetSection( e ) {
  e.preventDefault();

  const sectionID = $( this ).data( 'section' ),
    section = api.section( sectionID ),
    controls = section.controls();

  const iAgree = confirm( styleManager.l10n.resetSectionConfirmMessage );

  if ( !iAgree ) {
    return
  }

  if ( controls.length > 0 ) {
    $.each( controls, function( key, ctrl ) {
      const setting_id = ctrl.id.replace( '_control', '' ),
        setting = styleManager.config.settings[setting_id];

      if ( !_.isUndefined( setting ) && !_.isUndefined( setting.default ) ) {
        apiSetSettingValue( setting_id, setting.default )
      }
    } )
  }
}
