/*! For license information please see customizer-preview.js.LICENSE.txt */
(function(){"use strict";var __webpack_modules__={"./src/_js/customizer-preview/index.js":function(__unused_webpack_module,__webpack_exports__,__webpack_require__){eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils */ \"./src/_js/customizer-preview/utils.js\");\n\n;\n\n(function ($, window, document) {\n  var _window$top, _window$top$styleMana, _window$top$styleMana2;\n\n  //  if ( ! inPreviewIframe() ) {\n  //    return;\n  //  }\n  $(window).on('load', function () {\n    // We need to do this on window.load because on document.ready might be too early.\n    maybeLoadWebfontloaderScript();\n  });\n  window.fontsCache = [];\n  var settings = window === null || window === void 0 ? void 0 : (_window$top = window.top) === null || _window$top === void 0 ? void 0 : (_window$top$styleMana = _window$top.styleManager) === null || _window$top$styleMana === void 0 ? void 0 : (_window$top$styleMana2 = _window$top$styleMana.config) === null || _window$top$styleMana2 === void 0 ? void 0 : _window$top$styleMana2.settings;\n\n  var getStyleTagID = function getStyleTagID(settingID) {\n    return \"dynamic_style_\".concat(settingID.replace(/\\\\W/g, '_'));\n  };\n\n  var properKeys = Object.keys(settings).filter(function (settingID) {\n    var setting = settings[settingID];\n    return setting.type === 'font' || Array.isArray(setting.css) && setting.css.length;\n  });\n  properKeys.forEach(function (settingID) {\n    var style = document.createElement('style');\n    var idAttr = getStyleTagID(settingID);\n    style.setAttribute('id', idAttr);\n    document.body.appendChild(style);\n  }); // we create a queue of settingID => newValue pairs\n\n  var updateQueue = {}; // so we can update their respective style tags in only one pass\n  // and avoid multiple \"recalculate styles\" and all changes will appear\n  // at the same time in the customizer preview\n\n  var onChange = _.debounce(function () {\n    var queue = Object.assign({}, updateQueue);\n    updateQueue = {};\n    Object.keys(queue).forEach(function (settingID) {\n      var idAttr = getStyleTagID(settingID);\n      var style = document.getElementById(idAttr);\n      var newValue = queue[settingID];\n      var settingConfig = settings[settingID];\n      style.innerHTML = getSettingCSS(settingID, newValue, settingConfig);\n    });\n  }, 100);\n\n  properKeys.forEach(function (settingID) {\n    wp.customize(settingID, function (setting) {\n      setting.bind(function (newValue) {\n        updateQueue[settingID] = newValue;\n        onChange();\n      });\n    });\n  });\n})(jQuery, window, document);\n\nvar maybeLoadWebfontloaderScript = function maybeLoadWebfontloaderScript() {\n  if (typeof WebFont === 'undefined') {\n    var tk = document.createElement('script');\n    tk.src = parent.styleManager.config.webfontloader_url;\n    tk.type = 'text/javascript';\n    var s = document.getElementsByTagName('script')[0];\n    s.parentNode.insertBefore(tk, s);\n  }\n};\n\nvar defaultCallbackFilter = function defaultCallbackFilter(value, selector, property) {\n  var unit = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : '';\n  return \"\".concat(selector, \" { \").concat(property, \": \").concat(value).concat(unit, \"; }\");\n};\n\nvar getSettingCSS = function getSettingCSS(settingID, newValue, settingConfig) {\n  if (settingConfig.type === 'font') {\n    (0,_utils__WEBPACK_IMPORTED_MODULE_0__.maybeLoadFontFamily)(newValue, settingID);\n    var cssValue = (0,_utils__WEBPACK_IMPORTED_MODULE_0__.getFontFieldCSSValue)(settingID, newValue);\n    return (0,_utils__WEBPACK_IMPORTED_MODULE_0__.getFontFieldCSSCode)(settingID, cssValue, newValue);\n  }\n\n  if (!Array.isArray(settingConfig.css)) {\n    return '';\n  }\n\n  return settingConfig.css.reduce(function (acc, propertyConfig, index) {\n    var callback_filter = propertyConfig.callback_filter,\n        selector = propertyConfig.selector,\n        property = propertyConfig.property,\n        unit = propertyConfig.unit;\n    var settingCallback = callback_filter && typeof window[callback_filter] === \"function\" ? window[callback_filter] : defaultCallbackFilter;\n\n    if (!selector || !property) {\n      return acc;\n    }\n\n    return \"\".concat(acc, \"\\n      \").concat(settingCallback(newValue, selector, property, unit));\n  }, '');\n};\n\n//# sourceURL=webpack://sm.%5Bname%5D/./src/_js/customizer-preview/index.js?")},"./src/_js/customizer-preview/utils.js":function(__unused_webpack_module,__webpack_exports__,__webpack_require__){eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"getFontFieldCSSValue\": function() { return /* binding */ getFontFieldCSSValue; },\n/* harmony export */   \"getFontFieldCSSCode\": function() { return /* binding */ getFontFieldCSSCode; },\n/* harmony export */   \"getFieldUnit\": function() { return /* binding */ getFieldUnit; },\n/* harmony export */   \"maybeLoadFontFamily\": function() { return /* binding */ maybeLoadFontFamily; },\n/* harmony export */   \"inPreviewIframe\": function() { return /* binding */ inPreviewIframe; }\n/* harmony export */ });\n/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! jquery */ \"jquery\");\n/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! lodash */ \"lodash\");\n/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(lodash__WEBPACK_IMPORTED_MODULE_1__);\nfunction _typeof(obj) { \"@babel/helpers - typeof\"; if (typeof Symbol === \"function\" && typeof Symbol.iterator === \"symbol\") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === \"function\" && obj.constructor === Symbol && obj !== Symbol.prototype ? \"symbol\" : typeof obj; }; } return _typeof(obj); }\n\n\n // Mirror logic of server-side Utils\\Fonts::getCSSValue()\n\nvar getFontFieldCSSValue = function getFontFieldCSSValue(settingID, value) {\n  var CSSValue = {};\n\n  if (typeof value.font_family !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.font_family)) {\n    CSSValue['font-family'] = value.font_family; // \"Expand\" the font family by appending the fallback stack, if any is available.\n    // But only do this, if the value is not already a font stack!\n\n    if (CSSValue['font-family'].indexOf(',') === -1) {\n      var fallbackStack = getFontFamilyFallbackStack(CSSValue['font-family']);\n\n      if (fallbackStack.length) {\n        CSSValue['font-family'] += ',' + fallbackStack;\n      }\n    }\n\n    CSSValue['font-family'] = sanitizeFontFamilyCSSValue(CSSValue['font-family']);\n  }\n\n  if (typeof value.font_variant !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.font_variant)) {\n    var variant = value.font_variant;\n\n    if (lodash__WEBPACK_IMPORTED_MODULE_1___default().isString(variant)) {\n      // We may have a style in the variant; attempt to split.\n      if (variant.indexOf('italic') !== -1) {\n        CSSValue['font-style'] = 'italic';\n        variant = variant.replace('italic', '');\n      } else if (variant.indexOf('oblique') !== -1) {\n        CSSValue['font-style'] = 'oblique';\n        variant = variant.replace('oblique', '');\n      } // If anything remained, then we have a font weight also.\n\n\n      if (variant !== '') {\n        if (variant === 'regular' || variant === 'normal') {\n          variant = '400';\n        }\n\n        CSSValue['font-weight'] = variant;\n      }\n    } else if (lodash__WEBPACK_IMPORTED_MODULE_1___default().isNumber(variant)) {\n      CSSValue['font-weight'] = String(variant);\n    }\n  }\n\n  if (typeof value.font_size !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.font_size)) {\n    var fontSizeUnit = false;\n    CSSValue['font-size'] = value.font_size; // If the value already contains a unit (is not numeric), go with that.\n\n    if (isNaN(value.font_size)) {\n      // If we have a standardized value field (as array), use that.\n      if (typeof value.font_size.value !== 'undefined') {\n        CSSValue['font-size'] = value.font_size.value;\n\n        if (typeof value.font_size.unit !== 'undefined') {\n          fontSizeUnit = value.font_size.unit;\n        }\n      } else {\n        fontSizeUnit = getFieldUnit(settingID, 'font-size');\n      }\n    } else {\n      fontSizeUnit = getFieldUnit(settingID, 'font-size');\n    }\n\n    if (false !== fontSizeUnit) {\n      CSSValue['font-size'] += fontSizeUnit;\n    }\n  }\n\n  if (typeof value.letter_spacing !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.letter_spacing)) {\n    var letterSpacingUnit = false;\n    CSSValue['letter-spacing'] = value.letter_spacing; // If the value already contains a unit (is not numeric), go with that.\n\n    if (isNaN(value.letter_spacing)) {\n      // If we have a standardized value field (as array), use that.\n      if (typeof value.letter_spacing.value !== 'undefined') {\n        CSSValue['letter-spacing'] = value.letter_spacing.value;\n\n        if (typeof value.letter_spacing.unit !== 'undefined') {\n          letterSpacingUnit = value.letter_spacing.unit;\n        }\n      } else {\n        letterSpacingUnit = getFieldUnit(settingID, 'letter-spacing');\n      }\n    } else {\n      letterSpacingUnit = getFieldUnit(settingID, 'letter-spacing');\n    }\n\n    if (false !== letterSpacingUnit) {\n      CSSValue['letter-spacing'] += letterSpacingUnit;\n    }\n  }\n\n  if (typeof value.line_height !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.line_height)) {\n    var lineHeightUnit = false;\n    CSSValue['line-height'] = value.line_height; // If the value already contains a unit (is not numeric), go with that.\n\n    if (isNaN(value.line_height)) {\n      // If we have a standardized value field (as array), use that.\n      if (typeof value.line_height.value !== 'undefined') {\n        CSSValue['line-height'] = value.line_height.value;\n\n        if (!!value.line_height.unit !== 'undefined') {\n          lineHeightUnit = value.line_height.unit;\n        }\n      } else {\n        lineHeightUnit = getFieldUnit(settingID, 'line-height');\n      }\n    } else {\n      lineHeightUnit = getFieldUnit(settingID, 'line-height');\n    }\n\n    if (false !== lineHeightUnit) {\n      CSSValue['line-height'] += lineHeightUnit;\n    }\n  }\n\n  if (typeof value.text_align !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.text_align)) {\n    CSSValue['text-align'] = value.text_align;\n  }\n\n  if (typeof value.text_transform !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.text_transform)) {\n    CSSValue['text-transform'] = value.text_transform;\n  }\n\n  if (typeof value.text_decoration !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], value.text_decoration)) {\n    CSSValue['text-decoration'] = value.text_decoration;\n  }\n\n  return CSSValue;\n}; // Mirror logic of server-side Utils\\Fonts::getFontStyle()\n\nvar getFontFieldCSSCode = function getFontFieldCSSCode(settingID, cssValue, value) {\n  var styleManager = styleManager || parent.styleManager;\n  var fontConfig = styleManager.config.settings[settingID];\n  var prefix = typeof fontConfig.properties_prefix === 'undefined' ? '' : fontConfig.properties_prefix;\n  var output = '';\n\n  if (typeof window !== 'undefined' && typeof fontConfig.callback !== 'undefined' && typeof window[fontConfig.callback] === 'function') {\n    // The callbacks expect a string selector right now, not a standardized list.\n    // @todo Maybe migrate all callbacks to the new standardized data and remove all this.\n    var plainSelectors = [];\n\n    lodash__WEBPACK_IMPORTED_MODULE_1___default().each(fontConfig.selector, function (details, selector) {\n      plainSelectors.push(selector);\n    });\n\n    var adjustedFontConfig = jquery__WEBPACK_IMPORTED_MODULE_0___default().extend(true, {}, fontConfig);\n    adjustedFontConfig.selector = plainSelectors.join(', '); // Also, \"kill\" all fields unit since we pass final CSS values.\n    // @todo For some reason, the client-side Typeline cbs are not consistent and expect the font-size value with unit.\n\n    lodash__WEBPACK_IMPORTED_MODULE_1___default().each(adjustedFontConfig['fields'], function (fieldValue, fieldKey) {\n      if (typeof fieldValue.unit !== 'undefined') {\n        adjustedFontConfig['fields'][fieldKey]['unit'] = false;\n      }\n    }); // Callbacks want the value keys with underscores, not dashes.\n    // We will provide them in both versions for a smoother transition.\n\n\n    lodash__WEBPACK_IMPORTED_MODULE_1___default().each(cssValue, function (propertyValue, property) {\n      var newKey = property.replace(regexForMultipleReplace, '_');\n      cssValue[newKey] = propertyValue;\n    });\n\n    return window[fontConfig.callback](cssValue, adjustedFontConfig);\n  }\n\n  if (typeof fontConfig.selector === 'undefined' || lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(fontConfig.selector) || lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(cssValue)) {\n    return output;\n  } // The general CSS allowed properties.\n\n\n  var subFieldsCSSAllowedProperties = extractAllowedCSSPropertiesFromFontFields(fontConfig['fields']); // The selector is standardized to a list of simple string selectors, or a list of complex selectors with details.\n  // In either case, the actual selector is in the key, and the value is an array (possibly empty).\n  // Since we might have simple CSS selectors and complex ones (with special details),\n  // for cleanliness we will group the simple ones under a single CSS rule,\n  // and output individual CSS rules for complex ones.\n  // Right now, for complex CSS selectors we are only interested in the `properties` sub-entry.\n\n  var simpleCSSSelectors = [];\n  var complexCSSSelectors = {};\n\n  lodash__WEBPACK_IMPORTED_MODULE_1___default().each(fontConfig.selector, function (details, selector) {\n    if (lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(details.properties)) {\n      // This is a simple selector.\n      simpleCSSSelectors.push(selector);\n    } else {\n      complexCSSSelectors[selector] = details;\n    }\n  });\n\n  if (!lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(simpleCSSSelectors)) {\n    output += '\\n' + simpleCSSSelectors.join(', ') + ' {\\n';\n    output += getFontFieldCSSProperties(cssValue, subFieldsCSSAllowedProperties, prefix);\n    output += '}\\n';\n  }\n\n  if (!lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(complexCSSSelectors)) {\n    lodash__WEBPACK_IMPORTED_MODULE_1___default().each(complexCSSSelectors, function (details, selector) {\n      output += '\\n' + selector + ' {\\n';\n      output += getFontFieldCSSProperties(cssValue, details.properties, prefix);\n      output += '}\\n';\n    });\n  }\n\n  return output;\n}; // This is a mirror logic of the server-side Utils\\Fonts::getSubFieldUnit()\n\nvar getFieldUnit = function getFieldUnit(settingID, field) {\n  if (typeof styleManager.config.settings[settingID] === 'undefined' || typeof styleManager.config.settings[settingID].fields[field] === 'undefined') {\n    // These fields don't have an unit, by default.\n    if (lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['font-family', 'font-weight', 'font-style', 'line-height', 'text-align', 'text-transform', 'text-decoration'], field)) {\n      return false;\n    } // The rest of the subfields have pixels as default units.\n\n\n    return 'px';\n  }\n\n  if (typeof styleManager.config.settings[settingID].fields[field].unit !== 'undefined') {\n    // Make sure that we convert all falsy unit values to the boolean false.\n    return lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], styleManager.config.settings[settingID].fields[field].unit) ? false : styleManager.config.settings[settingID].fields[field].unit;\n  }\n\n  if (typeof styleManager.config.settings[settingID].fields[field][3] !== 'undefined') {\n    // Make sure that we convert all falsy unit values to the boolean false.\n    return lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(['', 'false', false], styleManager.config.settings[settingID].fields[field][3]) ? false : styleManager.config.settings[settingID].fields[field][3];\n  }\n\n  return 'px';\n}; // Mirror logic of server-side Utils\\Fonts::getCSSProperties()\n\nvar getFontFieldCSSProperties = function getFontFieldCSSProperties(cssValue) {\n  var allowedProperties = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;\n  var prefix = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : '';\n  var output = '';\n  jquery__WEBPACK_IMPORTED_MODULE_0___default().each(cssValue, function (property, propertyValue) {\n    // We don't want to output empty CSS rules.\n    if ('' === propertyValue || false === propertyValue) {\n      return;\n    } // If the property is not allowed, skip it.\n\n\n    if (!isCSSPropertyAllowed(property, allowedProperties)) {\n      return;\n    }\n\n    output += prefix + property + ': ' + propertyValue + ';\\n';\n  });\n  return output;\n}; // Mirror logic of server-side Utils\\Fonts::isCSSPropertyAllowed()\n\n\nvar isCSSPropertyAllowed = function isCSSPropertyAllowed(property) {\n  var allowedProperties = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;\n\n  // Empty properties are not allowed.\n  if (lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(property)) {\n    return false;\n  } // Everything is allowed if nothing is specified.\n\n\n  if (lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(allowedProperties)) {\n    return true;\n  } // For arrays\n\n\n  if (lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(allowedProperties, property)) {\n    return true;\n  } // For objects\n\n\n  if (lodash__WEBPACK_IMPORTED_MODULE_1___default().has(allowedProperties, property) && allowedProperties[property]) {\n    return true;\n  }\n\n  return false;\n};\n\nvar extractAllowedCSSPropertiesFromFontFields = function extractAllowedCSSPropertiesFromFontFields(subfields) {\n  // Nothing is allowed by default.\n  var allowedProperties = {\n    'font-family': false,\n    'font-weight': false,\n    'font-style': false,\n    'font-size': false,\n    'line-height': false,\n    'letter-spacing': false,\n    'text-align': false,\n    'text-transform': false,\n    'text-decoration': false\n  };\n\n  if (lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(subfields)) {\n    return allowedProperties;\n  } // We will match the subfield keys with the CSS properties, but only those that properties that are allowed.\n  // Maybe at some point some more complex matching would be needed here.\n\n\n  lodash__WEBPACK_IMPORTED_MODULE_1___default().each(subfields, function (value, key) {\n    if (typeof allowedProperties[key] !== 'undefined') {\n      // Convert values to boolean.\n      allowedProperties[key] = !!value; // For font-weight we want font-style to go the same way,\n      // since these two are generated from the same subfield: font-weight (actually holding the font variant value).\n\n      if ('font-weight' === key) {\n        allowedProperties['font-style'] = allowedProperties[key];\n      }\n    }\n  });\n\n  return allowedProperties;\n};\n\nvar maybeLoadFontFamily = function maybeLoadFontFamily(font, settingID) {\n  var styleManager = styleManager || parent.styleManager;\n\n  if (typeof font.font_family === 'undefined') {\n    return;\n  }\n\n  var fontConfig = styleManager.config.settings[settingID];\n  var family = font.font_family; // The font family may be a comma separated list like \"Roboto, sans\"\n\n  var fontType = parent.sm.customizer.determineFontType(family);\n\n  if ('system_font' === fontType) {\n    // Nothing to do for standard fonts\n    return;\n  }\n\n  var fontDetails = parent.sm.customizer.getFontDetails(family, fontType); // Handle theme defined fonts and cloud fonts together since they are very similar.\n\n  if (fontType === 'theme_font' || fontType === 'cloud_font') {\n    // Bail if we have no src.\n    if (_typeof(fontDetails.src) === undefined) {\n      return;\n    } // Handle the font variants.\n    // If there is a selected font variant and we haven't been instructed to load all, load only that,\n    // otherwise load all the available variants.\n\n\n    var variants = typeof font.font_variant !== 'undefined' && (typeof fontConfig['fields']['font-weight']['loadAllVariants'] === 'undefined' || !fontConfig['fields']['font-weight']['loadAllVariants']) && typeof fontDetails.variants !== 'undefined' // If the font has no variants, any variant value we may have received should be ignored.\n    && lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(fontDetails.variants, font.font_variant) // If the value variant is not amongst the available ones, load all available variants.\n    ? font.font_variant : typeof fontDetails.variants !== 'undefined' ? fontDetails.variants : [];\n\n    if (!lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(variants)) {\n      variants = standardizeToArray(variants);\n\n      if (!lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(variants)) {\n        family = family + ':' + variants.map(function (variant) {\n          return parent.sm.customizer.convertFontVariantToFVD(variant);\n        }).join(',');\n      }\n    }\n\n    if (fontsCache.indexOf(family) === -1) {\n      WebFont.load({\n        custom: {\n          families: [family],\n          urls: [fontDetails.src]\n        },\n        classes: false,\n        events: false\n      }); // Remember we've loaded this family (with it's variants) so we don't load it again.\n\n      fontsCache.push(family);\n    }\n  } // Handle Google fonts since Web Font Loader has a special module for them.\n  else if (fontType === 'google_font') {\n    // Handle the font variants\n    // If there is a selected font variant and we haven't been instructed to load all, load only that,\n    // otherwise load all the available variants.\n    var _variants = typeof font.font_variant !== 'undefined' && (typeof fontConfig['fields']['font-weight']['loadAllVariants'] === 'undefined' || !fontConfig['fields']['font-weight']['loadAllVariants']) && typeof fontDetails.variants !== 'undefined' // If the font has no variants, any variant value we may have received should be ignored.\n    && lodash__WEBPACK_IMPORTED_MODULE_1___default().includes(fontDetails.variants, font.font_variant) // If the value variant is not amongst the available ones, load all available variants.\n    ? font.font_variant : typeof fontDetails.variants !== 'undefined' ? fontDetails.variants : [];\n\n    if (!lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(_variants)) {\n      _variants = standardizeToArray(_variants);\n\n      if (!lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(_variants)) {\n        family = family + ':' + _variants.join(',');\n      }\n    }\n\n    if (fontsCache.indexOf(family) === -1) {\n      WebFont.load({\n        google: {\n          families: [family]\n        },\n        classes: false,\n        events: false\n      }); // Remember we've loaded this family (with it's variants) so we don't load it again.\n\n      fontsCache.push(family);\n    }\n  } else {// Maybe Typekit, Fonts.com or Fontdeck fonts\n  }\n}; // This is a mirror logic of the server-side Utils\\Fonts::getFontFamilyFallbackStack()\n\nvar getFontFamilyFallbackStack = function getFontFamilyFallbackStack(fontFamily) {\n  var styleManager = styleManager || parent.styleManager;\n  var fallbackStack = '';\n  var fontDetails = parent.sm.customizer.getFontDetails(fontFamily);\n\n  if (typeof fontDetails.fallback_stack !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(fontDetails.fallback_stack)) {\n    fallbackStack = fontDetails.fallback_stack;\n  } else if (typeof fontDetails.category !== 'undefined' && !lodash__WEBPACK_IMPORTED_MODULE_1___default().isEmpty(fontDetails.category)) {\n    var category = fontDetails.category; // Search in the available categories for a match.\n\n    if (typeof styleManager.fonts.categories[category] !== 'undefined') {\n      // Matched by category ID/key\n      fallbackStack = typeof styleManager.fonts.categories[category].fallback_stack !== 'undefined' ? styleManager.fonts.categories[category].fallback_stack : '';\n    } else {\n      // We need to search for aliases.\n      lodash__WEBPACK_IMPORTED_MODULE_1___default().find(styleManager.fonts.categories, function (categoryDetails) {\n        if (typeof categoryDetails.aliases !== 'undefined') {\n          var aliases = maybeImplodeList(categoryDetails.aliases);\n\n          if (aliases.indexOf(category) !== -1) {\n            // Found it.\n            fallbackStack = typeof categoryDetails.fallback_stack !== 'undefined' ? categoryDetails.fallback_stack : '';\n            return true;\n          }\n        }\n\n        return false;\n      });\n    }\n  }\n\n  return fallbackStack;\n}; // Mirror logic of server-side Utils\\Fonts::sanitizeFontFamilyCSSValue()\n\n\nvar sanitizeFontFamilyCSSValue = function sanitizeFontFamilyCSSValue(value) {\n  // Since we might get a stack, attempt to treat is a comma-delimited list.\n  var fontFamilies = maybeExplodeList(value);\n\n  if (!fontFamilies.length) {\n    return '';\n  }\n\n  lodash__WEBPACK_IMPORTED_MODULE_1___default().each(fontFamilies, function (fontFamily, key) {\n    // Make sure that the font family is free from \" or ' or whitespace, at the front.\n    fontFamily = fontFamily.replace(new RegExp(/^\\s*[\"'‘’“”]*\\s*/), ''); // Make sure that the font family is free from \" or ' or whitespace, at the back.\n\n    fontFamily = fontFamily.replace(new RegExp(/\\s*[\"'‘’“”]*\\s*$/), '');\n\n    if ('' === fontFamily) {\n      delete fontFamilies[key];\n      return;\n    } // Now, if the font family contains spaces, wrap it in \".\n\n\n    if (fontFamily.indexOf(' ') !== -1) {\n      fontFamily = '\"' + fontFamily + '\"';\n    } // Finally, put it back.\n\n\n    fontFamilies[key] = fontFamily;\n  });\n\n  return maybeImplodeList(fontFamilies);\n};\n\nvar standardizeToArray = function standardizeToArray(value) {\n  if (typeof value === 'string' || typeof value === 'number') {\n    value = [value];\n  } else if (_typeof(value) === 'object') {\n    value = Object.values(value);\n  }\n\n  return value;\n};\n\nvar maybeExplodeList = function maybeExplodeList(str) {\n  var delimiter = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : ',';\n\n  if (_typeof(str) === 'object') {\n    str = standardizeToArray(str);\n  } // If by any chance we are given an array, just return it\n\n\n  if (Array.isArray(str)) {\n    return str;\n  } // Anything else we coerce to a string\n\n\n  if (typeof str !== 'string') {\n    str = String(str);\n  } // Make sure we trim it\n\n\n  str = str.trim(); // Bail on empty string\n\n  if (!str.length) {\n    return [];\n  } // Return the whole string as an element if the delimiter is missing\n\n\n  if (str.indexOf(delimiter) === -1) {\n    return [str];\n  } // Explode it and return it\n\n\n  return explode(delimiter, str);\n};\n\nvar maybeImplodeList = function maybeImplodeList(value) {\n  var glue = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : ',';\n\n  // If by any chance we are given a string, just return it\n  if (typeof value === 'string' || typeof value === 'number') {\n    return String(value);\n  }\n\n  if (_typeof(value) === 'object') {\n    value = standardizeToArray(value);\n  }\n\n  if (Array.isArray(value)) {\n    return implode(glue, value);\n  } // For anything else we return an empty string.\n\n\n  return '';\n};\n\nvar explode = function explode(delimiter, string, limit) {\n  //  discuss at: https://locutus.io/php/explode/\n  // original by: Kevin van Zonneveld (https://kvz.io)\n  //   example 1: explode(' ', 'Kevin van Zonneveld')\n  //   returns 1: [ 'Kevin', 'van', 'Zonneveld' ]\n  if (arguments.length < 2 || typeof delimiter === 'undefined' || typeof string === 'undefined') {\n    return null;\n  }\n\n  if (delimiter === '' || delimiter === false || delimiter === null) {\n    return false;\n  }\n\n  if (typeof delimiter === 'function' || _typeof(delimiter) === 'object' || typeof string === 'function' || _typeof(string) === 'object') {\n    return {\n      0: ''\n    };\n  }\n\n  if (delimiter === true) {\n    delimiter = '1';\n  } // Here we go...\n\n\n  delimiter += '';\n  string += '';\n  var s = string.split(delimiter);\n  if (typeof limit === 'undefined') return s; // Support for limit\n\n  if (limit === 0) limit = 1; // Positive limit\n\n  if (limit > 0) {\n    if (limit >= s.length) {\n      return s;\n    }\n\n    return s.slice(0, limit - 1).concat([s.slice(limit - 1).join(delimiter)]);\n  } // Negative limit\n\n\n  if (-limit >= s.length) {\n    return [];\n  }\n\n  s.splice(s.length + limit);\n  return s;\n};\n\nvar implode = function implode(glue, pieces) {\n  //  discuss at: https://locutus.io/php/implode/\n  // original by: Kevin van Zonneveld (https://kvz.io)\n  // improved by: Waldo Malqui Silva (https://waldo.malqui.info)\n  // improved by: Itsacon (https://www.itsacon.net/)\n  // bugfixed by: Brett Zamir (https://brett-zamir.me)\n  //   example 1: implode(' ', ['Kevin', 'van', 'Zonneveld'])\n  //   returns 1: 'Kevin van Zonneveld'\n  //   example 2: implode(' ', {first:'Kevin', last: 'van Zonneveld'})\n  //   returns 2: 'Kevin van Zonneveld'\n  var i = '';\n  var retVal = '';\n  var tGlue = '';\n\n  if (arguments.length === 1) {\n    pieces = glue;\n    glue = '';\n  }\n\n  if (_typeof(pieces) === 'object') {\n    if (Object.prototype.toString.call(pieces) === '[object Array]') {\n      return pieces.join(glue);\n    }\n\n    for (i in pieces) {\n      retVal += tGlue + pieces[i];\n      tGlue = glue;\n    }\n\n    return retVal;\n  }\n\n  return pieces;\n};\n\nvar inPreviewIframe = function inPreviewIframe() {\n  try {\n    return window.self !== window.top;\n  } catch (e) {\n    return true;\n  }\n};\n\n//# sourceURL=webpack://sm.%5Bname%5D/./src/_js/customizer-preview/utils.js?")},jquery:function(e){e.exports=window.jQuery},lodash:function(e){e.exports=window.lodash}},__webpack_module_cache__={};function __webpack_require__(e){var n=__webpack_module_cache__[e];if(void 0!==n)return n.exports;var t=__webpack_module_cache__[e]={exports:{}};return __webpack_modules__[e](t,t.exports,__webpack_require__),t.exports}__webpack_require__.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return __webpack_require__.d(n,{a:n}),n},__webpack_require__.d=function(e,n){for(var t in n)__webpack_require__.o(n,t)&&!__webpack_require__.o(e,t)&&Object.defineProperty(e,t,{enumerable:!0,get:n[t]})},__webpack_require__.o=function(e,n){return Object.prototype.hasOwnProperty.call(e,n)},__webpack_require__.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})};var __webpack_exports__=__webpack_require__("./src/_js/customizer-preview/index.js");(window.sm=window.sm||{}).customizerPreview=__webpack_exports__})();