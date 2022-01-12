import $ from 'jquery';
import './style.scss';

import { initializeColors } from './colors';
import { initializeFonts } from './fonts';
import { initializeFontPalettes } from './font-palettes'

import * as globalService from "./global-service";
import * as resizer from './resizer';

import { handleColorSelectFields } from './fields/color-select';
import { handleRangeFields } from './fields/range';
import { handleTabs } from './fields/tabs';
import { handlePresets } from './fields/preset';

import { handleFoldingFields } from './folding-fields';
import { createResetButtons } from './create-reset-buttons';
import { initializeFeedbackModal } from './feedback-modal';
import { getSettingConfig } from "./global-service";

wp.customize.bind( 'ready', () => {
  globalService.loadSettings();

  const settings = globalService.getSettings();
  const settingIDs = Object.keys( settings );

  globalService.bindConnectedFields( settingIDs );

  createResetButtons();
  handleRangeFields();
  handleColorSelectFields();
  handleTabs();
  handlePresets();

  // @todo check reason for this timeout
  setTimeout( function () {
    handleFoldingFields();
  }, 1000 );

  // Initialize simple select2 fields.
  $( '.style-manager_select2' ).select2();

  initializeColors();
  initializeFonts();
  initializeFontPalettes();
  initializeFeedbackModal();
} );

// expose API on sm.customizer global object
export { getFontDetails, determineFontType, convertFontVariantToFVD } from './fonts/utils';
export { maybeFillPalettesArray, getCSSFromPalettes } from './utils';
export { resizer };
