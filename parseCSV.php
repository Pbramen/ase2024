<?php
	include("./db.php");
	include("./customExceptions.php");

	function detect_encoding($line){
		if(!mb_detect_encoding($line, ['ASCII', 'UTF-8'], true))
			throw new Exception("Invalid string encoding.");
	}

	function checkEntries($strings){
		// if all values are empty -> throw error
		$miss = "";
		if ($strings[0] === ""){
			$miss .= "type ";
		}
		if ($strings[1] === ""){
			$miss .= "company ";
		}
		if ($strings[2] === ""){
			$miss .= "sn";
		}
		if ($miss === ""){
			return;
		}
		if ($miss === "type company sn"){
			throw new IgnoredException("Blank line");
		}
		throw new Exception("Missing values: $miss");
	}
	
	function getExecTime($start){
		$end = microtime(true);
		return (($end - $start) / 60);
	}



	$deviceMap = $proto->getAllDevices();
	$companyMap = $proto->getAllCompany();

	date_default_timezone_set("America/Chicago");
	$file = $argv[1];
	
	$proto->createTracking($file);
	$fileName = '/home/ubuntu/parts/'.$file;

	$cached = $proto->checkTracking($file);
	//echo $cached;
	try{
		if ( !file_exists($fileName)){
			throw new Exception("File not found");
		}
		$fp = fopen($fileName, "r");
		if (!$fp){
			throw new Exception("File open failed");
		}
	
		while((($line = fgets($fp)) !== false) ){
		
			$proto->lineNumber+=1;
			
			
			if($cached >= $proto->lineNumber){
				//echo "entry already entered\r\n";
				continue;
			}
	
			
			$line = str_replace("\r\n", '', $line);
			$strings = explode(",", $line);
			try{
				
				$time_start= microtime(true);
				//check if correct encoding is used.
				detect_encoding($line);
				//check for missing elements
				$argv = count($strings); 
				if($argv === 3){
					checkEntries($strings);
					// values are correct! 
					// Insert if SN is unquie
					$device = $strings[0];
					$company = $strings[1];
					if (isset($deviceMap[$device])){
						$deviceId = $deviceMap[$device];
					}
					else{
						$deviceId = $proto->insertDevice($device);
					}
					if(isset($companyMap[$company])){
						$companyId = $companyMap[$company];
					}
					else{
						$companyId = $proto->insertCompany($company);
					}
					$proto->insertNewDevice($deviceId, $companyId, $strings[2]);
					$exec_time = getExecTime($time_start);
					$proto->incrementLine($file, $exec_time);
				} 
				// there is more or less than three arguments. 
				else{	
					// check if there are less than three arguemets -> log error;
					if($argv < 3){
						throw new Exception('Missing arguments: '. 3-$argv);
					}
					checkEntries($strings);
					$device = $strings[0];
					$company = $strings[1];
				
					if (isset($deviceMap[$device])){
						$deviceId = $deviceMap[$device];
					}
					else{
						$deviceId = $proto->insertDevice($device);
					}
					if(isset($companyMap[$company])){
						$companyId = $companyMap[$company];
					}
					else{
						$companyId = $proto->insertCompany($company);
					}
					// Check if at least three arguements are not null and SN exists and is unquie
					$proto->insertNewDevice($deviceId, $companyId, $strings[2]);
					//throw new Exception("Warning: extra arguements detected");
					throw new IgnoredException("Warning: extra arguements detcted");
			
					}
			}catch (IgnoredException $e){
				$exec_time = getExecTime($time_start);
				$proto->incrementLine($file, $exec_time);
				
				$log->openConnect(...$sys_log);
				$log->insertException($e->getMessage(), $proto->lineNumber, $file, $line, "parse_warnings");
			
			} catch (Exception $e){
				$exec_time = getExecTime($time_start);
				$proto->incrementLine($file, $exec_time);
				// Log errors here.
				$log->openConnect(...$sys_log);
				$log->insertException($e->getMessage(), $proto->lineNumber, $file, $line);
				
			}
		}
	//echo "exited loop";
		
	$r = fclose($fp);
	if(!$r){
		throw new Exception("File close failed.");
	}
	} catch (Exception $e){
		//TODO: log error to database!
		$log->openConnect(...$sys_log);
		$log->insertSystemError($e);
		exit(-1);
	}
//	$log->openConnect(...$sys_log);
//	$log->insertTime($file, $proto->lineNumber, $execution_time);
?>