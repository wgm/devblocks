<!--
var DevblocksAppPath = '{$smarty.const.DEVBLOCKS_WEBPATH}';

{literal}
var DevblocksUrl = function() {
{/literal}

{if $smarty.const.DEVBLOCKS_REWRITE}
	this.base_url = '{$smarty.const.DEVBLOCKS_WEBPATH}';
	this.rewrite = true;
{else}
	this.base_url = '{$smarty.const.DEVBLOCKS_WEBPATH}index.php';
	this.rewrite = false;
{/if}

{literal}
	this.vars = new Array();

	this.addVar = function(v) {
		this.vars[this.vars.length] = v;
	}
	
	// [JAS]: Write our URL using either Apache Rewrite or Query String
	this.getUrl = function() {
		if(this.rewrite) {
			var url = this.base_url + this.vars.join('/');
		} else {
			var url = this.base_url;
			for(var x=0;x<this.vars.length;x++) {
				url += ((x>0) ? '&' : '?') + 'a'+x+'='+this.vars[x];
			}
		}
		return url;
	}
}

function selectValue(e) {
	return e.options[e.selectedIndex].value;
}

function clearDiv(divName) {
	var div = document.getElementById(divName);
	if(null == div) return;
	
	div.innerHTML = '';
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
	} else if (null != state && (state == 'block' || state == 'inline' || state == 'none')) {
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
			boxes[x].checked = (state) ? true : false;
		}
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

function genericAjaxGet(divName,args,cb) {
	var frm = document.getElementById(divName);
	if(null == frm) return;

	// [JAS]: Default response action
	if(null == cb) {
		var anim = new YAHOO.util.Anim(frm, { opacity: { to: 0.2 } }, 1, YAHOO.util.Easing.easeOut);
		anim.animate();
		
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
			failure: function(o) {alert('fail');},
			argument:{caller:this,divName:divName}
			}
	);
}

function genericAjaxPost(formName,divName,args,cb) {
	var frm = document.getElementById(formName);
	var div = document.getElementById(divName);
	if(null == frm) return;

	// [JAS]: [TODO] This doesn't work in IE -- probably offsetWidth/Height
	if(null != div) {
		// [JAS]: [TODO] Move to a function
		var loading = document.createElement('div');
		loading.setAttribute('style','position:absolute;padding:5px;top:0;left:0;background-color:red;color:white;font-weight:bold;');
		loading.innerHTML = 'Loading...';
		
		var toX = YAHOO.util.Dom.getX(div) + (div.offsetWidth/2) - (loading.offsetWidth/2);
		var toY = YAHOO.util.Dom.getY(div) + (div.offsetHeight/2) - (loading.offsetHeight/2);		
		
		loading.style.top = toY;
		loading.style.left = toX;
		
		document.body.appendChild(loading);
	}

//	var anim = new YAHOO.util.Anim(frm, { opacity: { to: 0.2 } }, 1, YAHOO.util.Easing.easeOut);
//	anim.animate();
	
	YAHOO.util.Connect.setForm(formName);
	var cObj = YAHOO.util.Connect.asyncRequest('POST', DevblocksAppPath+'ajax.php?'+args, {
			success: function(o) {
				var div = document.getElementById(divName);
				if(null == div) return;

				document.body.removeChild(loading);
				div.innerHTML = o.responseText;
				
//				var anim = new YAHOO.util.Anim(frm, { opacity: { to: 1.0 } }, 1, YAHOO.util.Easing.easeOut);
//				anim.animate();
			},
			failure: function(o) {alert('fail');},
			argument:{caller:this}
			}
	);
}

{/literal}
-->