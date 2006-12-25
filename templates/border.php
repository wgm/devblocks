<body topmargin="0" leftmargin="0" marginheight="0" marginwidth="0">

{include file="header.tpl.php"}

<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td background="images/topbg_blue.jpg" width="0%" nowrap="nowrap">
			<img src="images/devblocks_logo.jpg" align="absmiddle">
		</td>
		<td background="images/topbg_blue.jpg" width="100%" align="right" valign="bottom" style="padding:5px;">
			<span style="color:rgb(255,255,255);line-height:160%;">
				<b>jstanden</b> (developer) [ <a href="javascript:;" style="color:rgb(255,255,255);">sign out</a> ]
				<br>
				your balance: <span class="blocks">188 blocks</span> [ <a href="javascript:;" style="color:rgb(255,255,255);" title="What are blocks?">?</a> ]
			</span>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			{include file="menu.tpl.php}
		</td>
	</tr>
</table>

<div style="padding:10px">

{if null != $activeModule}
	{$activeModule->render()}
{/if}

</div>

{include file="footer.tpl.php"}

</body>
</html>
