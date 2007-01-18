{assign var=tagWeights value=$tagCloud->getWeightedTags()}
{assign var=relatedTags value=$tagCloud->getRelatedTags()}
{assign var=maxFreq value=$tagCloud->getMaxFrequency()}
{assign var=tags value=$tagCloud->getCloudTags()}
{assign var=alltags value=$tagCloud->getAllTags()}

{if !empty($relatedTags)}
{foreach from=$relatedTags item=tag name=tagpath}
{$alltags.$tag->name} {if !$smarty.foreach.tagpath.last}&gt;{/if}
{/foreach}
<br><br>
{/if}

{foreach from=$tags item=tag key=tagid}
{if isset($tagWeights.$tagid) && $tagWeights.$tagid->weight != 0}
	<a href="javascript:;" onclick="ajax.getRelatedCloud('{$relatedTagsStr}{if count($relatedTags) > 0},{/if}{$tagid}','{$maxfreq}');" style="font-size: {$tagWeights.$tagid->weight};">{$tag->name}</a>
	&nbsp;
{/if}
{/foreach}<br>


<br>