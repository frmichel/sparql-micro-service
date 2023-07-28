<?php
	$url = $_GET['url'];
	$url = str_replace(' ', '+', $url);

	$fileContents= file_get_contents($url);
	$fileContents = str_replace(array("\n", "\r", "\t"), '', $fileContents);
	$fileContents = trim(str_replace('"', "'", $fileContents));
	$fileContents = str_replace("\\", "/", $fileContents);
	$simpleXml = simplexml_load_string($fileContents);
	$json = json_encode($simpleXml);
	header('Content-Type: application/json');
	echo $json;
