<?php
if (empty($this->data))
	print('No segfault found (in version 5.5.4)');
else {
	$banner = '*** Segmentation fault';
	$offset = strrpos($this->data, $banner) + strlen($banner);
	$this->data = substr($this->data, $offset);

	$this->data = str_replace('Register dump:', '<h2>Register dump:</h2>', $this->data);
	$this->data = str_replace('Backtrace:', '<h2>Backtrace:</h2>', $this->data);
?><pre><?=$this->data?></pre>
<? } ?>