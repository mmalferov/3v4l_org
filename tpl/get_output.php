<dl>
<? foreach ($this->data as $versions => $output)
{
	$id = explode(' ', $versions);
	$id = array_shift($id);
?>
	<dt id="v<?= str_replace('.', '', $id) ?>">Output for <?=$versions?></dt><dd><?=$output?></dd>
<? } ?>
</dl>