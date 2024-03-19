<?php 
	include('dbConnection.php');
	$prototype = ['webUser', '!KU44qi!Qibx/Yq(', 'prototype'];
	$sys_log = ['webUser', '!KU44qi!Qibx/Yq(', 'sys_log'];
	
	$error_log = '/home/ubuntu/logs/mysqli_log.txt';

	try{
		$proto = new devicesDB( );
		$proto->openConnect(... $prototype);
		$log = new loggerDB();
	} catch (Exception $e){
		error_log($e->__toString(), 3, $error_log);
	}
?>

