<a href="javascript:;" onclick="dbAjax.voteUp('{$bounty->id}');"><img src="images/vote_up{if $voted==1}_filled{/if}.gif" border="0"></a> 
<a href="javascript:;" onclick="dbAjax.voteDown('{$bounty->id}');"><img src="images/vote_down{if $voted==-1}_filled{/if}.gif" border="0"></a><br>
<b>{if $bounty->votes > 0}+{/if}{$bounty->votes} votes</b>
