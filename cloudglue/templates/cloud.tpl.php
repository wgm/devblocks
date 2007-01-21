{assign var=tagPath value=$tagCloud->getPath()}
{if !empty($tagPath)}
<div style="background-color:rgb(240,240,240);">
<b>Selected Tags:</b> 
{foreach from=$tagPath item=path name=paths}
	<a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','c={$tagCloud->cfg->cb_extension}&a={$tagCloud->cfg->cb_click}&tag={$path->id}&remove=1');" style="color:rgb(255, 108, 25);" title="Remove tag">{$path->name}</a>{if !$smarty.foreach.paths.last} + {/if}
{/foreach} 
</div>
{/if}

{if !empty($tagPath) && !empty($tags)}These tags are also related your selected tags:<br><br>{/if}

{if !empty($tags)}
{foreach from=$tags key=tag_id item=tag name=tags}
	<span style="font-size:{$weights.$tag_id}px"><a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','c={$tagCloud->cfg->cb_extension}&a={$tagCloud->cfg->cb_click}&tag={$tag->id}');">{$tag->name}</a>{if !$smarty.foreach.tags.last},{/if}</span>
{/foreach}
{else}
	There are no other relationships to your selected tags.
{/if}
