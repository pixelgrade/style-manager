/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

;// CONCATENATED MODULE: external "jQuery"
var external_jQuery_namespaceObject = window["jQuery"];
var external_jQuery_default = /*#__PURE__*/__webpack_require__.n(external_jQuery_namespaceObject);
;// CONCATENATED MODULE: ./src/_js/settings/index.js

external_jQuery_default()(document).ready(function () {
  external_jQuery_default()('#reset_customizer_settings').on('click', function () {
    var confirm = window.confirm('Are you sure you want to do this?');

    if (!confirm) {
      return false;
    }

    external_jQuery_default().ajax({
      url: styleManager.config.wp_rest.root + 'style_manager/v1/delete_customizer_settings',
      method: 'POST',
      beforeSend: function beforeSend(xhr) {
        xhr.setRequestHeader('X-WP-Nonce', styleManager.config.wp_rest.nonce);
      },
      data: {
        'style_manager_settings_nonce': styleManager.config.wp_rest.style_manager_settings_nonce
      }
    }).done(function (response) {
      if (response.success) {
        alert('Success: ' + response.data);
      } else {
        alert('Unfortunately, no luck: ' + response.data);
      }
    }).error(function (e) {
      console.log(e);
    });
  });
});
(window.sm = window.sm || {}).settings = __webpack_exports__;
/******/ })()
;