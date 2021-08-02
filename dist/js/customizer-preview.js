!function(){"use strict";var t={n:function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,{a:n}),n},d:function(e,n){for(var i in n)t.o(n,i)&&!t.o(e,i)&&Object.defineProperty(e,i,{enumerable:!0,get:n[i]})},o:function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},r:function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})}},e={};t.r(e);var n=window.jQuery,i=t.n(n),o=window.lodash,r=t.n(o);function a(t){return(a="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t})(t)}var s=function(t,e){return void 0===styleManager.config.settings[t]||void 0===styleManager.config.settings[t].fields[e]?!r().includes(["font-family","font-weight","font-style","line-height","text-align","text-transform","text-decoration"],e)&&"px":void 0!==styleManager.config.settings[t].fields[e].unit?!r().includes(["","false",!1],styleManager.config.settings[t].fields[e].unit)&&styleManager.config.settings[t].fields[e].unit:void 0!==styleManager.config.settings[t].fields[e][3]?!r().includes(["","false",!1],styleManager.config.settings[t].fields[e][3])&&styleManager.config.settings[t].fields[e][3]:"px"},f=function(t){var e=arguments.length>1&&void 0!==arguments[1]&&arguments[1],n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"",o="";return i().each(t,(function(t,i){""!==i&&!1!==i&&l(t,e)&&(o+=n+t+": "+i+";\n")})),o},l=function(t){var e=arguments.length>1&&void 0!==arguments[1]&&arguments[1];return!r().isEmpty(t)&&(!!r().isEmpty(e)||(!!r().includes(e,t)||!(!r().has(e,t)||!e[t])))},c=function(t){var e={"font-family":!1,"font-weight":!1,"font-style":!1,"font-size":!1,"line-height":!1,"letter-spacing":!1,"text-align":!1,"text-transform":!1,"text-decoration":!1};return r().isEmpty(t)||r().each(t,(function(t,n){void 0!==e[n]&&(e[n]=!!t,"font-weight"===n&&(e["font-style"]=e[n]))})),e},u=function(t){var e=e||parent.styleManager,n="",i=parent.sm.customizer.getFontDetails(t);if(void 0===i.fallback_stack||r().isEmpty(i.fallback_stack)){if(void 0!==i.category&&!r().isEmpty(i.category)){var o=i.category;void 0!==e.fonts.categories[o]?n=void 0!==e.fonts.categories[o].fallback_stack?e.fonts.categories[o].fallback_stack:"":r().find(e.fonts.categories,(function(t){if(void 0!==t.aliases&&-1!==y(t.aliases).indexOf(o))return n=void 0!==t.fallback_stack?t.fallback_stack:"",!0;return!1}))}}else n=i.fallback_stack;return n},d=function(t){var e=v(t);return e.length?(r().each(e,(function(t,n){""!==(t=(t=t.replace(new RegExp(/^\s*["'‘’“”]*\s*/),"")).replace(new RegExp(/\s*["'‘’“”]*\s*$/),""))?(-1!==t.indexOf(" ")&&(t='"'+t+'"'),e[n]=t):delete e[n]})),y(e)):""},g=function(t){return"string"==typeof t||"number"==typeof t?t=[t]:"object"===a(t)&&(t=Object.values(t)),t},v=function(t){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:",";return"object"===a(t)&&(t=g(t)),Array.isArray(t)?t:("string"!=typeof t&&(t=String(t)),(t=t.trim()).length?-1===t.indexOf(e)?[t]:p(e,t):[])},y=function(t){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:",";return"string"==typeof t||"number"==typeof t?String(t):("object"===a(t)&&(t=g(t)),Array.isArray(t)?h(e,t):"")},p=function(t,e,n){if(arguments.length<2||void 0===t||void 0===e)return null;if(""===t||!1===t||null===t)return!1;if("function"==typeof t||"object"===a(t)||"function"==typeof e||"object"===a(e))return{0:""};!0===t&&(t="1");var i=(e+="").split(t+="");return void 0===n?i:(0===n&&(n=1),n>0?n>=i.length?i:i.slice(0,n-1).concat([i.slice(n-1).join(t)]):-n>=i.length?[]:(i.splice(i.length+n),i))},h=function(t,e){var n="",i="",o="";if(1===arguments.length&&(e=t,t=""),"object"===a(e)){if("[object Array]"===Object.prototype.toString.call(e))return e.join(t);for(n in e)i+=o+e[n],o=t;return i}return e},m=function(){try{return window.self!==window.top}catch(t){return!0}};!function(t,e,n){var i,o,r;if(m()){t(e).on("load",(function(){b()})),e.fontsCache=[];var a=null==e||null===(i=e.parent)||void 0===i||null===(o=i.styleManager)||void 0===o||null===(r=o.config)||void 0===r?void 0:r.settings,s=function(t){return"dynamic_style_".concat(t.replace(/\\W/g,"_"))},f=Object.keys(a).filter((function(t){var e=a[t];return"font"===e.type||Array.isArray(e.css)&&e.css.length}));f.forEach((function(t){var e=n.createElement("style"),i=s(t);e.setAttribute("id",i),n.body.appendChild(e)}));var l={},c=_.debounce((function(){var t=Object.assign({},l);l={},Object.keys(t).forEach((function(e){var i=s(e),o=n.getElementById(i),r=t[e],f=a[e];o.innerHTML=x(e,r,f)}))}),100);f.forEach((function(t){wp.customize(t,(function(e){e.bind((function(e){l[t]=e,c()}))}))}))}}(jQuery,window,document);var b=function(){if("undefined"==typeof WebFont){var t=document.createElement("script");t.src=parent.styleManager.config.webfontloader_url,t.type="text/javascript";var e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(t,e)}},w=function(t,e,n){var i=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"";return"".concat(e," { ").concat(n,": ").concat(t).concat(i,"; }")},x=function(t,e,n){return"font"===n.type?(function(t,e){var n=n||parent.styleManager;if(void 0!==t.font_family){var i=n.config.settings[e],o=t.font_family,s=parent.sm.customizer.determineFontType(o);if("system_font"!==s){var f=parent.sm.customizer.getFontDetails(o,s);if("theme_font"===s||"cloud_font"===s){if(void 0===a(f.src))return;var l=void 0===t.font_variant||void 0!==i.fields["font-weight"].loadAllVariants&&i.fields["font-weight"].loadAllVariants||void 0===f.variants||!r().includes(f.variants,t.font_variant)?void 0!==f.variants?f.variants:[]:t.font_variant;r().isEmpty(l)||(l=g(l),r().isEmpty(l)||(o=o+":"+l.map((function(t){return parent.sm.customizer.convertFontVariantToFVD(t)})).join(","))),-1===fontsCache.indexOf(o)&&(WebFont.load({custom:{families:[o],urls:[f.src]},classes:!1,events:!1}),fontsCache.push(o))}else if("google_font"===s){var c=void 0===t.font_variant||void 0!==i.fields["font-weight"].loadAllVariants&&i.fields["font-weight"].loadAllVariants||void 0===f.variants||!r().includes(f.variants,t.font_variant)?void 0!==f.variants?f.variants:[]:t.font_variant;r().isEmpty(c)||(c=g(c),r().isEmpty(c)||(o=o+":"+c.join(","))),-1===fontsCache.indexOf(o)&&(WebFont.load({google:{families:[o]},classes:!1,events:!1}),fontsCache.push(o))}}}}(e,t),function(t,e,n){var o=o||parent.styleManager,a=o.config.settings[t],s=void 0===a.properties_prefix?"":a.properties_prefix,l="";if("undefined"!=typeof window&&void 0!==a.callback&&"function"==typeof window[a.callback]){var u=[];r().each(a.selector,(function(t,e){u.push(e)}));var d=i().extend(!0,{},a);return d.selector=u.join(", "),r().each(d.fields,(function(t,e){void 0!==t.unit&&(d.fields[e].unit=!1)})),r().each(e,(function(t,n){var i=n.replace(regexForMultipleReplace,"_");e[i]=t})),window[a.callback](e,d)}if(void 0===a.selector||r().isEmpty(a.selector)||r().isEmpty(e))return l;var g=c(a.fields),v=[],y={};return r().each(a.selector,(function(t,e){r().isEmpty(t.properties)?v.push(e):y[e]=t})),r().isEmpty(v)||(l+="\n"+v.join(", ")+" {\n",l+=f(e,g,s),l+="}\n"),r().isEmpty(y)||r().each(y,(function(t,n){l+="\n"+n+" {\n",l+=f(e,t.properties,s),l+="}\n"})),l}(t,function(t,e){var n={};if(void 0!==e.font_family&&!r().includes(["","false",!1],e.font_family)){if(n["font-family"]=e.font_family,-1===n["font-family"].indexOf(",")){var i=u(n["font-family"]);i.length&&(n["font-family"]+=","+i)}n["font-family"]=d(n["font-family"])}if(void 0!==e.font_variant&&!r().includes(["","false",!1],e.font_variant)){var o=e.font_variant;r().isString(o)?(-1!==o.indexOf("italic")?(n["font-style"]="italic",o=o.replace("italic","")):-1!==o.indexOf("oblique")&&(n["font-style"]="oblique",o=o.replace("oblique","")),""!==o&&("regular"!==o&&"normal"!==o||(o="400"),n["font-weight"]=o)):r().isNumber(o)&&(n["font-weight"]=String(o))}if(void 0!==e.font_size&&!r().includes(["","false",!1],e.font_size)){var a=!1;n["font-size"]=e.font_size,isNaN(e.font_size)&&void 0!==e.font_size.value?(n["font-size"]=e.font_size.value,void 0!==e.font_size.unit&&(a=e.font_size.unit)):a=s(t,"font-size"),!1!==a&&(n["font-size"]+=a)}if(void 0!==e.letter_spacing&&!r().includes(["","false",!1],e.letter_spacing)){var f=!1;n["letter-spacing"]=e.letter_spacing,isNaN(e.letter_spacing)&&void 0!==e.letter_spacing.value?(n["letter-spacing"]=e.letter_spacing.value,void 0!==e.letter_spacing.unit&&(f=e.letter_spacing.unit)):f=s(t,"letter-spacing"),!1!==f&&(n["letter-spacing"]+=f)}if(void 0!==e.line_height&&!r().includes(["","false",!1],e.line_height)){var l=!1;n["line-height"]=e.line_height,isNaN(e.line_height)&&void 0!==e.line_height.value?(n["line-height"]=e.line_height.value,void 0!==e.line_height.unit&&(l=e.line_height.unit)):l=s(t,"line-height"),!1!==l&&(n["line-height"]+=l)}return void 0===e.text_align||r().includes(["","false",!1],e.text_align)||(n["text-align"]=e.text_align),void 0===e.text_transform||r().includes(["","false",!1],e.text_transform)||(n["text-transform"]=e.text_transform),void 0===e.text_decoration||r().includes(["","false",!1],e.text_decoration)||(n["text-decoration"]=e.text_decoration),n}(t,e))):Array.isArray(n.css)?n.css.reduce((function(t,n,i){var o=n.callback_filter,r=n.selector,a=n.property,s=n.unit,f=o&&"function"==typeof window[o]?window[o]:w;return r&&a?"".concat(t,"\n      ").concat(f(e,r,a,s)):t}),""):""};(window.sm=window.sm||{}).customizerPreview=e}();