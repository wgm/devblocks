<h1><a href="#">Project</a>: Cerberus Helpdesk</h1>

<br>

<table cellpadding="0" cellspacing="3" border="0">

	{foreach from=$bounties item=bounty name=bounties key=bounty_id}
	<tr>
		<td width="0%" nowrap="nowrap" style="padding:3px;border-right:1px solid rgb(255,255,255);" bgcolor="white" valign="top" align="center" id="bountyVotes{$bounty_id}">
			{assign var=voted value=$votes.$bounty_id}
			{include file="file:$path/modules/bounties/votes.tpl.php"}
		</td>
		<td width="100%" valign="top">
			<span class="task">{$bounty->title}</span><img src="images/spacer.gif" width="5" height="1"><span class="blocks">{if $bounty->estimate}{$bounty->estimate} blocks{else}no cost{/if}</span><br>
		</td>
	</tr>
	<tr>
		<td colspan="2"><img src="images/spacer.gif" width="1" height="5"></td>
	</tr>
	{/foreach}
	
</table>