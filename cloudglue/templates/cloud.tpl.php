{assign var=tagPath value=$tagCloud->getPath()}
{if !empty($tagPath)}
<b>Tags:</b> 
{foreach from=$tagPath item=path name=paths}
	{$path->name}{if !$smarty.foreach.paths.last} + {/if}
{/foreach}
<br>
<br>
{/if}

{foreach from=$tags key=tag_id item=tag name=tags}
	<span style="font-size:{$weights.$tag_id}px"><a href="javascript:;" onclick="genericAjaxGet('{$tagCloud->cfg->divName}','{$tagCloud->cfg->args}&tag={$tag->id}');">{$tag->name}</a>{if !$smarty.foreach.tags.last},{/if}</span>
{/foreach}
