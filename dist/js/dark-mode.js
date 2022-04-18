(()=>{"use strict";var e={d:(t,i)=>{for(var n in i)e.o(i,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:i[n]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t),r:e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})}},t={};e.r(t),e.d(t,{default:()=>o});function i(e,t){for(var i=0;i<t.length;i++){var n=t[i];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}var n="color-scheme-dark-temp";const o=new(function(){function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),this.initialize()}var t,o,a;return t=e,(o=[{key:"initialize",value:function(){var e,t,i=this;this.darkModeSetting=null===(e=window.document.documentElement.dataset)||void 0===e?void 0:e.darkModeAdvanced,this.matchMedia=window.matchMedia("(prefers-color-scheme: dark)"),this.storageItemKey="color-scheme-dark",this.callbacks=[],t=function(){var e,t,o;(function(){try{return window.self!==window.top}catch(e){return!0}}()&&(null===(e=window)||void 0===e||null===(t=e.parent)||void 0===t||null===(o=t.wp)||void 0===o?void 0:o.customize)||window.document.body.classList.contains("logged-in"))&&(localStorage.removeItem(n),i.storageItemKey=n),i.initializeCustomizePreview(),i.bindEvents(),i.update()},"loading"!=document.readyState?t():document.addEventListener("DOMContentLoaded",t)}},{key:"initializeCustomizePreview",value:function(){var e,t,i,o=this,a=(null===(e=window.wp)||void 0===e?void 0:e.customize)||(null===(t=window.parent)||void 0===t||null===(i=t.wp)||void 0===i?void 0:i.customize);a&&a("sm_dark_mode_advanced",(function(e){o.darkModeSetting=e(),e.bind((function(e){o.darkModeSetting=e,localStorage.removeItem(n),o.update()}))}))}},{key:"bindEvents",value:function(){var e,t,i,o,a=this;e=document.documentElement,t="click",i=".js-sm-dark-mode-toggle",o=this.onClick.bind(this),e.addEventListener(t,(function(e){for(var t=e.target;t&&t!=this;t=t.parentNode)if(t.matches(i)){o.call(t,e);break}}),!1),this.matchMedia.addEventListener("change",(function(){localStorage.removeItem(n),a.update()}))}},{key:"bind",value:function(e){var t=this.callbacks.indexOf(e);"function"==typeof e&&-1===t&&this.callbacks.push(e)}},{key:"unbind",value:function(e){var t=this.callbacks.indexOf(e);t>-1&&this.callbacks.splice(t,1)}},{key:"onClick",value:function(e){e.preventDefault(),localStorage.setItem(this.storageItemKey,this.isCompiledDark()?"light":"dark"),this.update()}},{key:"isSystemDark",value:function(){var e="on"===this.darkModeSetting;return"auto"===this.darkModeSetting&&this.matchMedia.matches&&(e=!0),e}},{key:"isCompiledDark",value:function(){var e=this.isSystemDark(),t=localStorage.getItem(this.storageItemKey);return null!==t&&(e="dark"===t),e}},{key:"update",value:function(){var e=this.isCompiledDark();this.callbacks.forEach((function(t){t(e)})),e?window.document.documentElement.classList.add("is-dark"):window.document.documentElement.classList.remove("is-dark")}}])&&i(t.prototype,o),a&&i(t,a),Object.defineProperty(t,"prototype",{writable:!1}),e}());(window.sm=window.sm||{}).darkMode=t})();