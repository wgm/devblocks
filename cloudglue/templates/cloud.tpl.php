{assign var=tagPath value=$tagCloud->getPath()}
{if !empty($tagPath)}
<div style="background-color:rgb(240,240,240);">
<b>Tags:</b> 
{foreach from=$tagPath item=path name=paths}
	<a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','{$tagCloud->cfg->args}&tag={$path->id}&remove=1');">{$path->name}</a>{if !$smarty.foreach.paths.last} + {/if}
{/foreach}
<br>
<a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','c=search&a=resetCloud');"><b>start over</b></a>
| <a href="{devblocks_url}c=search&a=showMatches{/devblocks_url}"><b>show matches</b></a>
</div>
<br>
{/if}

{if !empty($tags)}
{foreach from=$tags key=tag_id item=tag name=tags}
	<span style="font-size:{$weights.$tag_id}px"><a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','{$tagCloud->cfg->args}&tag={$tag->id}');">{$tag->name}</a>{if !$smarty.foreach.tags.last},{/if}</span>
{/foreach}
{else}
	There are no additional related tags. <a href="{devblocks_url}c=search&a=showMatches{/devblocks_url}"><b>Show matches!</b></a>
{/if}
