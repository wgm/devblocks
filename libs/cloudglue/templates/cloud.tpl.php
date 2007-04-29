<table width="100%" cellpadding="2" cellspacing="0" border="0">
	<tr>
		<td valign="top" style="width:650px;">
			{assign var=tagPath value=$tagCloud->getPath()}
			{if !empty($tagPath)}
				<b>Posts tagged:</b>
				{foreach from=$tagPath item=path name=paths}
					<a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','c={$tagCloud->cfg->extension}&a={$tagCloud->cfg->php_click}&tag={$path->id}&remove=1'{if !empty($tagCloud->cfg->js_click)},{$tagCloud->cfg->js_click}{/if});" style="" title="Remove tag">{$path->name}</a>{if !$smarty.foreach.paths.last} +{/if} 
				{/foreach}
			{/if}
			<div id="{$tagCloud->cfg->divName}_results" style="width: 95%;background:rgb(255,255,255);">
			</div>
		</td>
		
		<td valign="top" style="border-left:1px solid rgb(240,240,240);">
		<b>{if !empty($tagPath) && !empty($tags)}Related topics:{else}Topics:{/if}</b>
		{if !empty($tagPath)}[ <a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','c={$tagCloud->cfg->extension}&a=resetCloud');" style="color:rgb(100,100,100);">start over</a> ]{/if}
		<br>
		
		{if !empty($tags)}
		{foreach from=$tags key=tag_id item=tag name=tags}
			<span style="font-size:{$weights.$tag_id}px"><a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','c={$tagCloud->cfg->extension}&a={$tagCloud->cfg->php_click}&tag={$tag->id}'{if !empty($tagCloud->cfg->js_click)},{$tagCloud->cfg->js_click}{/if});" style="color:rgb(255, 108, 25);">{$tag->name}</a>{if !$smarty.foreach.tags.last},{/if}</span>
		{/foreach}
		{else}
			There are no other relationships to your selected tags.
		{/if}
		</td>
	</tr>
</table>