<!--
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
	} else if (null != state && (state == 'block' || state == 'none')) {
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

{/literal}
-->