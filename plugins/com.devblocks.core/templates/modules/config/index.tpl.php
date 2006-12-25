<form action="index.php" method="post">
<input type="hidden" name="c" value="{$c}">
<input type="hidden" name="a" value="saveBounty">
<input type="hidden" name="id" value="">

<h1>Create Bounty</h1>
<b>Name:</b> <input type="text" name="name"><br>
<b>Estimate:</b> <input type="text" name="estimate" size="3"> hours<br>
<input type="submit" value="{$translate->say('common.save_changes')}">
</form>

<br>

<form action="index.php" method="post">
<input type="hidden" name="c" value="{$c}">
<input type="hidden" name="a" value="massBountyEntry">

<h1>Bounty Quick Entry</h1>
<b>Enter one title per line:</b><br>
<textarea rows="8" cols="45" style="width:98%" name="entry"></textarea><br>
<input type="submit" value="{$translate->say('common.save_changes')}">
</form>

