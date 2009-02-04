<!--
var DevblocksAppPath = '{$smarty.const.DEVBLOCKS_WEBPATH}';

{literal}
var DevblocksUrl = function() {
{/literal}

this.base_url = '{devblocks_url}{/devblocks_url}';
this.rewrite = {if $smarty.const.DEVBLOCKS_REWRITE}true{else}false{/if};

{literal}
	this.vars = new Array();

	this.addVar = function(v) {
		this.vars[this.vars.length] = v;
	}
	
	// [JAS]: Write our URL using either Apache Rewrite or Query String
	this.getUrl = function() {
		var url = this.base_url + this.vars.join('/');
		return url;
	}
};

var DevblocksClass = function() {
	/* Source: http://bytes.com/forum/thread90068.html */
	this.getSelectedText = function() {
		if (window.getSelection) { // recent Mozilla
			var selectedString = window.getSelection();
		} else if (document.all) { // MSIE 4+
			var selectedString = document.selection.createRange().text;
		} else if (document.getSelection) { //older Mozilla
			var selectedString = document.getSelection();
		};
		
		return selectedString;
	}
	
	this.getFormEnabledCheckboxValues = function(form_id,element_name) {
		// Make sure the view form exists
		var viewForm = document.getElementById(form_id);
		if(null == viewForm) return;

		// Make sure the element is present in the form

		var elements = viewForm.elements[element_name];
		if(null == elements) return;

		var len = elements.length;
		var ids = new Array();

		if(null == len && null != elements.value) { // single element
			if(elements.checked)
				ids[0] = elements.value;

		} else { // array
			for(var x=len-1;x>=0;x--) {
				if(elements[x].checked) {
					ids[ids.length] = elements[x].value;
				}
			}
		}

		return ids.join(',');
	}

	this.resetSelectElements = function(form_id,element_name) {
		// Make sure the view form exists
		var viewForm = document.getElementById(form_id);
		if(null == viewForm) return;

		// Make sure the element is present in the form

		var elements = viewForm.elements[element_name];
		if(null == elements) return;

		var len = elements.length;
		var ids = new Array();
		
		if(null == len && null != elements.selectedIndex) {
			elements.selectedIndex = 0;

		} else {
			for(var x=len-1;x>=0;x--) {
				elements[x].selectedIndex = 0;
			}
		}
	}
	
};
var Devblocks = new DevblocksClass();

function selectValue(e) {
	return e.options[e.selectedIndex].value;
}

function radioValue(e) {
	var	numBoxes = e.length;

	if(null == e.length) { // single
		return e.value;
	
	} else { // multi
		for(x=0;x<numBoxes;x++) {
			if(e[x].checked)
				return e[x].value;
		}
	}

	return null;
}

function clearDiv(divName) {
	var div = document.getElementById(divName);
	if(null == div) return;
	
	div.innerHTML = '';
}

function interceptInputCRLF(e,cb) {
	var code = (window.Event) ? e.which : event.keyCode;
	
	if(null != cb && code == 13) {
		try { cb(); } catch(e) {}
	}
	
	return code != 13;
}

function getEventTarget(e) {
	if(!e) e = event;
	
	if(e.target) {
		return e.target.nodeName;
	} else if (e.srcElement) {
		return e.srcElement.nodeName;
	}
}

function toggleClass(divName,c) {
	var div = document.getElementById(divName);
	if(null == div) return;
	div.className = c;	
}

/* From:
 * http://www.webmasterworld.com/forum91/4527.htm
 */
function setElementSelRange(e, selStart, selEnd) { 
	if (e.setSelectionRange) { 
		e.focus(); 
		e.setSelectionRange(selStart, selEnd); 
	} else if (e.createTextRange) { 
		var range = e.createTextRange(); 
		range.collapse(true); 
		range.moveEnd('character', selEnd); 
		range.moveStart('character', selStart); 
		range.select(); 
	} 
}

function scrollElementToBottom(e) {
	if(null == e) return;
	e.scrollTop = e.scrollHeight - e.clientHeight;
}

function toggleDiv(divName,state) {
	var div = document.getElementById(divName);
	if(null == div) return;
	var currentState = div.style.display;
	
	if(null == state) {
		if(currentState == "block") {
			div.style.display = 'none';
		} else {
			div.style.display = 'block';
		}
	} else if (null != state && (state == '' || state == 'block' || state == 'inline' || state == 'none')) {
		div.style.display = state;
	}
}

function checkAll(divName, state) {
	var div = document.getElementById(divName);
	if(null == div) return;
	
	var boxes = div.getElementsByTagName('input');
	var numBoxes = boxes.length;
	
	for(x=0;x<numBoxes;x++) {
		if(null != boxes[x].name) {
			if(state == null) state = !boxes[x].checked;
			boxes[x].checked = (state) ? true : false;
		}
	}
}

// [MDF]
function insertAtCursor(field, value) {
	if (document.selection) {
		field.focus();
		sel = document.selection.createRange();
		sel.text = value;
		field.focus();
	} 
	else if (field.selectionStart || field.selectionStart == '0') {
		var startPos = field.selectionStart;
		var endPos = field.selectionEnd;
		var cursorPos = startPos + value.length;

		field.value = field.value.substring(0, startPos) + value	+ field.value.substring(endPos, field.value.length);

		field.selectionStart = cursorPos;
		field.selectionEnd = cursorPos;
	}
	else{
		field.value += value;
	} 
}

// [JAS]: [TODO] Make this a little more generic?
function appendTextboxAsCsv(formName, field, oLink) {
	var frm = document.getElementById(formName);
	if(null == frm) return;
	
	var txt = frm.elements[field];
	var sAppend = '';
	
	// [TODO]: check that the last character(s) aren't comma or comma space
	if(0 != txt.value.length && txt.value.substr(-1,1) != ',' && txt.value.substr(-2,2) != ', ')
		sAppend += ', ';
		
	sAppend += oLink.innerHTML;
	
	txt.value = txt.value + sAppend;
}

var loadingPanel;
function showLoadingPanel() {
	if(null != loadingPanel) {
		loadingPanel.destroy();
		loadingPanel = null;
	}

	var options = { 
	  width : "300px",
	  fixedcenter : true,
	  visible : false, 
	  constraintoviewport : true,
	  underlay : "shadow",
	  modal : true,
	  close : false,
	  draggable : false
	};
	
	loadingPanel = new YAHOO.widget.Panel("loadingPanel", options);
	
	loadingPanel.setHeader('Running...');
	loadingPanel.setBody('');
	loadingPanel.render(document.body);
	
	loadingPanel.hide();
	loadingPanel.setBody("This may take a few moments.  Please wait!");
	loadingPanel.center();
	
	loadingPanel.show();
}

function hideLoadingPanel() {
	loadingPanel.destroy();
	loadingPanel = null;
}

var genericPanel;
function genericAjaxPanel(request,target,modal,width,cb) {
	if(null != genericPanel) {
		genericPanel.destroy();
		genericPanel = null;
	}

	var options = { 
	  width : "300px",
	  fixedcenter : false,
	  visible : false, 
	  constraintoviewport : true,
	  underlay : "shadow",
	  modal : false,
	  close : true,
	  draggable : true
	};

	if(null != width) options.width = width;
	if(null != modal) options.modal = modal;
	if(true == modal) options.fixedcenter = true;
//	if(true != modal) options.draggable = true;
	
	var cObj = YAHOO.util.Connect.asyncRequest('GET', DevblocksAppPath+'ajax.php?'+request, {
			success: function(o) {
				var caller = o.argument.caller;
				var target = o.argument.target;
				var options = o.argument.options;
				var callback = o.argument.cb;

				genericPanel = new YAHOO.widget.Panel("genericPanel", options);
				genericPanel.hideEvent.subscribe(function(type,args,me) {
					try {
						setTimeout(function(){
							genericPanel.destroy();
							genericPanel = null;
						},100);
					} catch(e){}
				});
				
				genericPanel.setHeader('&nbsp;');
				genericPanel.setBody('');
				genericPanel.render(document.body);
				
				genericPanel.hide();
				genericPanel.setBody(o.responseText);
				
				if(null != target && !options.fixedcenter) {
					genericPanel.cfg.setProperty('context',[target,"bl","tl"]);
				} else {
					genericPanel.center();
				}
				
				try { callback(o); } catch(e) {}				
				
				genericPanel.show();
			},
			failure: function(o) {},
			argument:{request:request,target:target,options:options,cb:cb}
		}
	);	
}

function saveGenericAjaxPanel(div,close,cb) {
	YAHOO.util.Connect.setForm(div);
	
	var cObj = YAHOO.util.Connect.asyncRequest('POST', DevblocksAppPath+'ajax.php', {
			success: function(o) {
				var callback = o.argument.cb;
				var close = o.argument.close;
				
				if(null != genericPanel && close) {
					try {
						genericPanel.destroy();
						genericPanel = null;
					} catch(e) {}
				}
				
				try { callback(o); } catch(e) {}
			},
			failure: function(o) {},
			argument:{div:div,close:close,cb:cb}
	});
	
	YAHOO.util.Connect.setForm(0);
}

function genericAjaxGet(divName,args,cb) {
	if(null == cb) {
		var frm = document.getElementById(divName);

		if(null != frm) {
			var anim = new YAHOO.util.Anim(frm, { opacity: { to: 0.2 } }, 1, YAHOO.util.Easing.easeOut);
			anim.animate();
		}
		
		var cb = function(o) {
			var frm = document.getElementById(divName);
			if(null == frm) return;
			frm.innerHTML = o.responseText;
			
			var anim = new YAHOO.util.Anim(frm, { opacity: { to: 1.0 } }, 1, YAHOO.util.Easing.easeOut);
			anim.animate();
		}
	}
	
	var cObj = YAHOO.util.Connect.asyncRequest('GET', DevblocksAppPath+'ajax.php?'+args, {
		success: cb,
		failure: function(o) {},
		argument:{caller:this,divName:divName}
		}
	);
}

var genericAjaxPostAfterSubmitEvent = new YAHOO.util.CustomEvent("genericAjaxPostAfterSubmitEvent", window);

function genericAjaxPost(formName,divName,args,cb) {
	var frm = document.getElementById(formName);
	if(null == cb) {
		var div = document.getElementById(divName);
		
		if(null != div) {
			var anim = new YAHOO.util.Anim(div, { opacity: { to: 0.2 } }, 1, YAHOO.util.Easing.easeOut);
			anim.animate();
		}
	
		var cb = function(o) {
			// Events
			genericAjaxPostAfterSubmitEvent.fire();
			genericAjaxPostAfterSubmitEvent.unsubscribeAll();

			var div = document.getElementById(divName);
			if(null == div) return;
			div.innerHTML = o.responseText;
			
			var anim = new YAHOO.util.Anim(div, { opacity: { to: 1.0 } }, 1, YAHOO.util.Easing.easeOut);
			anim.animate();
		};
	}
	
	YAHOO.util.Connect.setForm(frm);
	var cObj = YAHOO.util.Connect.asyncRequest('POST', DevblocksAppPath+'ajax.php'+(null!=args?('?'+args):''), {
			success: cb,
			failure: function(o) {},
			argument:{caller:this,divName:divName}
			},
			null
	);
	YAHOO.util.Connect.setForm(0);
}

{/literal}
-->