<table cellspacing="0" cellpadding="2" border="0" id="dbMenu">
	<tr>
		{foreach from=$modules item=module}
		<td {if $activeModule->id==$module->id}class="active"{/if} width="0%" nowrap="nowrap">
			<a href="index.php?c={$module->id}&a=click">{$module->params.menu_title|lower}</a>
		</td>
		{/foreach}
		<td width="100%" style="border-right:0px;">
			 <img src="images/spacer.gif" width="1" height="25">
		</td>
	</tr>
</table>
