<?php

class IgnoredException extends Exception{
	function __construct($message){
		parent::__construct($message);
	}
}
?>