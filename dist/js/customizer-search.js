window.styleManager=window.styleManager||parent.styleManager||{},function(e,n,t){var a,i,s,c,o,r,l,u,d,m,p,h,f;void 0===n.search&&(n.search={}),_.extend(n.search,(a=t.customize,i="#accordion-section-style-manager-customizer-search",s="#style-manager-customizer-search-input",c=null,o=null,r=function(){var t=_.map(a.settings.controls,(function(e,t){if("string"!=typeof t&&(t=String(t)),void 0===_.find(n.search.excludedControls,(function(e){return-1!==t.indexOf(e)}))){var i={label:void 0===e.label||_.isEmpty(e.label)?"":e.label,description:void 0===e.description||_.isEmpty(e.description)?"":e.description,panelName:"",sectionName:"",panel:null,section:e.section};return _.map(a.settings.sections,(function(n,t){e.section===n.id&&_.map(_wpCustomizeSettings.panels,(function(e,t){""===n.panel&&(i.panelName=n.title),n.panel===e.id&&(i.sectionName=n.title,i.panel=n.panel,i.panelName=e.title)}))})),i}})).filter((function(e){return void 0!==e}));o=new Fuse(t,{includeScore:!0,includeMatches:!0,shouldSort:!0,minMatchCharLength:2,threshold:.3,keys:[{name:"label",weight:1},{name:"description",weight:.8},{name:"panelName",weight:.4},{name:"sectionName",weight:.4}]});var i=e("#customize-info");(c=e("#customize-theme-controls")).after('<div id="style-manager-search-results"></div>'),i.on("keyup",s,(function(n){n.preventDefault();var t=e(s).val();t.length>2?u(t):0===t.length&&f()})),i.on("click",".clear-search",(function(e){f()})),i.on("click",".close-search",(function(e){p()})),i.on("click",".customize-search-toggle",(function(e){p()})),a.previewer.targetWindow.bind(m),a.state("expandedSection").bind(l),a.state("expandedPanel").bind(l)},l=function(){if(!a.state("expandedSection").get()&&!a.state("expandedPanel").get()){var n=e(s).val();n.length>2&&setTimeout((function(){u(n)}),400)}},u=function(t){var a=o.search(t);if(0!==a.length){var i=a.map((function(t,a){if(!_.isEmpty(t.matches)&&""!==t.item.label){var i=e.extend(!0,{},t);_.each(t.matches,(function(e){void 0===e.indices||_.isEmpty(e.indices)||(i.item[e.key]=d(e.value,e.indices))}));var s=i.item.panelName;return""!==i.item.sectionName&&(s="".concat(s," ▸ ").concat(i.item.sectionName)),'\n                <li id="accordion-section-'.concat(t.item.section,'" class="accordion-section control-section control-section-default customizer-search-results" aria-owns="sub-accordion-section-').concat(t.item.section,'" data-section="').concat(t.item.section,'">\n                    <h3 class="accordion-section-title" tabindex="0">\n                        ').concat(i.item.label,'\n                        <span class="screen-reader-text">').concat(n.l10n.search.resultsSectionScreenReaderText,'</span>\n                    </h3>\n                    <span class="search-setting-path">').concat(s,"</i></span>\n                </li>\n                ")}})).join("");c.addClass("search-found"),document.getElementById("style-manager-search-results").innerHTML="<ul>".concat(i,"</ul>"),document.querySelectorAll("#style-manager-search-results .accordion-section").forEach((function(e){return e.addEventListener("click",h)}))}else c.removeClass("search-found")},d=function(e,n){if(!n)return e;for(var t=[],a=n.shift(),i=0;i<e.length;i++){var s=e.charAt(i);a&&i==a[0]&&t.push('<span class="hl">'),t.push(s),a&&i==a[1]&&(t.push("</span>"),a=n.shift())}return t.join("")},m=function(){var n=t.template("style-manager-search-button");0===e("#customize-info .accordion-section-title .customize-search-toggle").length&&e("#customize-info .accordion-section-title").append(n()),n=t.template("style-manager-search-form"),0===e("#customize-info "+i).length&&e("#customize-info .customize-panel-description").after(n())},p=function(){var n=e(i);n.hasClass("open")?(n.removeClass("open"),n.slideUp("fast"),f()):(e(".customize-panel-description").removeClass("open"),e(".customize-panel-description").slideUp("fast"),n.addClass("open"),n.slideDown("fast"),e(s).focus())},h=function(n){var t=this.getAttribute("data-section"),i=a.section(t);c.removeClass("search-found"),document.getElementById("style-manager-search-results").innerHTML="",e(s).focus(),i.expand()},f=function(){c.removeClass("search-found"),document.getElementById("style-manager-search-results").innerHTML="",document.getElementById("style-manager-customizer-search-input").value="",e(s).focus()},a.bind("ready",r),{init:r}))}(jQuery,styleManager,wp),(window.sm=window.sm||{}).customizerSearch={};