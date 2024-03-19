<?php

//exec("sudo split -n l/7 '/home/ubuntu/qta422.csv' /home/ubuntu/parts/qta422");
$dirs = scandir('/home/ubuntu/parts');

function removeDirs($e){
	return $e != '.' && $e != '..';
}

$dirs = array_filter($dirs, "removeDirs");

foreach($dirs as $filename){
	echo "\r\n".$filename."\r\n";
	try{
		exec('/usr/bin/php /var/www/html/mysql/parseCSV.php '.$filename.' >>/home/ubuntu/output.txt 2>&1 &');

	} catch (Exception $e) {
		echo $e;
	}
}

?>
