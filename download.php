<?php
(isset($_REQUEST['attach']))?$file = base64_decode($_REQUEST['attach']):$file = '';
(isset($_REQUEST['name']))?$filename = base64_decode($_REQUEST['name']):$filename = '';

if(empty($file) || empty($filename)){
	exit;
}

header("Content-Type: text/Calendar");
header("Content-Disposition: inline; filename=".$filename.".ics");

echo file_get_contents($file);