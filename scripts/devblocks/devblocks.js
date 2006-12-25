<!--

var cDevblocks = function() {

	this.voteUp = function(id) {
		var cObj = YAHOO.util.Connect.asyncRequest('GET', 'ajax.php?c=core.module.bounties&a=voteUp&id=' + id, {
				success: function(o) {
					var caller = o.argument.caller;
					var id = o.argument.id;
					caller.refreshVotes(id);
				},
				failure: function(o) {},
				argument:{caller:this,id:id}
				}
		);
	}
	
	this.voteDown = function(id) {
		var cObj = YAHOO.util.Connect.asyncRequest('GET', 'ajax.php?c=core.module.bounties&a=voteDown&id=' + id, {
				success: function(o) {
					var caller = o.argument.caller;
					var id = o.argument.id;
					caller.refreshVotes(id);
				},
				failure: function(o) {},
				argument:{caller:this,id:id}
				}
		);
	}
	
	this.refreshVotes = function(id) {
		var div = document.getElementById('bountyVotes' + id);
		if(null == div) return;

//		YAHOO.util.Dom.setStyle(div, 'opacity', 1.0);

		var anim = new YAHOO.util.Anim(div, { opacity: { to:0.2 } }, 1, YAHOO.util.Easing.easeOut);
		anim.animate();
		
		var cObj = YAHOO.util.Connect.asyncRequest('GET', 'ajax.php?c=core.module.bounties&a=refreshVotes&id=' + id, {
				success: function(o) {
					var caller = o.argument.caller;
					var id = o.argument.id;
					var div = document.getElementById('bountyVotes' + id);
					if(null == div) return;
					
					div.innerHTML = o.responseText;
					
					var anim = new YAHOO.util.Anim(div, { opacity: { to: 1.0 } }, 1, YAHOO.util.Easing.easeOut);
					anim.animate();
					
				},
				failure: function(o) {},
				argument:{caller:this,id:id}
				}
		);
	}
		
}

var dbAjax = new cDevblocks();

-->