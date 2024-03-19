<?php

	class mysql_db {
		public $dblink;
		public $lineNumber = 0;
		public $fileName;
		public function __construct(){
		}
		
		public function openConnect($username, $password, $db, $host='localhost'){
			$this->dblink = mysqli_connect($host, $username, $password, $db);
			if ($this->dblink->connect_errno){
				echo '\r\nConnection Error: '.$mysqli->connect_errno.' '.$mysqli->connect_error.'\r\n';
				exec("echo $mysqli->connect_error >> /home/ubuntu/logs/mysql_error.txt 2>&1");
				exit(-1);
			}
		}
		
		#Debugger - print query results if any
		protected function _printResults($result){
			if($result->num_rows <= 0)
				echo "\r\nThere are no rows in the table devices!\r\n";
			else{
				while($data= $result->fetch_array(MYSQLI_ASSOC)){
					echo '\r\nDevice type: '.$data['device_type'].', Manufacturer: '.$data['company'].', Serial Number: '.$data['serial_number'].'\r\n';
				}
			}	 
		}
		
		protected function _prepareStatement($sql,$s, ...$args){
			$res = $this->dblink->prepare($sql);
			$c = $res->bind_param($s, ...$args);
			if(!$c){
				throw new Exception("Failed to execute prepared statement at $this->lineNumber.");
			}
			$res->execute();
			return $res;
		}
	
	}
	
	
	class loggerDB extends mysql_db{
		//prepared statements
		protected $p_insertException;
		protected $p_insertSys;
		
		//binded params
		protected $exec_date;
		protected $type;
		protected $line;
		
		public function __construct(){

		}
		
		public function openConnect($username, $password, $db, $host='localhost'){
			parent::openConnect($username, $password, $db, $host);
			$this->p_insertException = $this->dblink->prepare("INSERT IGNORE INTO `parse_error` (execution_date, type, line_num, file, line) VALUES (?,?,?,?,?)");
			$this->p_insertException->bind_param("ssiss", $this->exec_date, $this->type, $this->lineNumber, $this->fileName, $this->line);
			
			$this->p_insertSys = $this->dblink->prepare("INSERT IGNORE INTO `sys_err` (execution_date, type) VALUES (?, ?)");
			$this->p_insertSys->bind_param("ss", $this->exec_date, $this->type);
		}
		
		function insertException($e, $lineNumber, $fileName, $line, $err="parse_error"){
			
			$date = date('Y-m-d H:i:s');
			$this->type = $e;
			$this->lineNumnber = $lineNumber;
			$this->fileName = $fileName;
			$this->line = $line;
			$this->exec_date = $date;
			
			$this->p_insertException->execute();
			mysqli_close($this->dblink);
		}
		
		function insertSystemError($e){
			$date = date('Y-m-d H:i:s');

			$this->exec_date = $date;
			$this->type = $e->getMessage();
			
			$this->p_insertSys->execute();
			mysqli_close($this->dblink);
		}
	}

	class devicesDB extends mysql_db{
	
		//prepared statements
		protected $p_updateLine;
		protected $p_checkTrack;
		protected $p_selectDevice;
		protected $p_selectCompany;
		protected $p_insertNew;
		
		//binded params
		protected $exec_name;
		protected $dname;
		protected $cname;
		protected $serial_num;
		protected $device;
		protected $company;
		public function __construct(){
		
		}
		public function openConnect($username, $password, $db, $host='localhost'){
			
			parent::openConnect($username, $password, $db, $host);
			
			$this->p_checkTrack = $this->dblink->prepare("Select lineNumber FROM `tracking` where fileName=(?)"); 
			$this->p_checkTrack->bind_param("s", $this->fileName);
			
			$this->p_updateLine = $this->dblink->prepare("UPDATE tracking SET lineNumber = lineNumber + 1, execution_time = execution_time + ? WHERE fileName = ?");
			$this->p_updateLine->bind_param("ds", $this->exec_name, $this->fileName);
			
			$this->p_selectDevice = $this->dblink->prepare("SELECT device_id FROM devices WHERE device =?");
			$this->p_selectDevice->bind_param("s", $this->dname);
			try{
			//echo "this is reached 1";
			$this->p_selectCompany = $this->dblink->prepare("SELECT company_id FROM companies where company=?");
			//echo "this is reached";
			$this->p_selectCompany->bind_param("s", $this->cname);
		
			
			$this->p_insertNew = $this->dblink->prepare("INSERT IGNORE INTO `relation` (serial_num, devices_id, company_id) VALUES (?, ?, ?)");
			$this->p_insertNew->bind_param("sii",$this->serial_num, $this->device, $this->company);
			} catch (Exception $e){
				echo $e;
			}
		}
		
		// inserts a new entry into relation table and devices table.
		// called after all checks for errors are done.
		function insertNewDevice($device, $company, $sn){
		
			try{
				//echo $device;
				$this->device = $device;
				$this->company = $company;
				$this->serial_num = $sn;
				$this->p_insertNew->execute();
			
//				if($this->dblink->affected_rows == 0){
//					throw new Exception("Duplicate SN");
//				}
			
			} catch(Exception $e){
				error_log($e->__toString(), 3, '/home/ubuntu/logs/mysqli_log.txt');
			}
		}
		
		function checkTracking($fileName){
			$res = $this->_prepareStatement("Select lineNumber FROM `tracking` where fileName=(?)", "s", $fileName);
			$r = 0;
			$res->bind_result($r);
			$res->fetch();
			return $r;
		}
		//called only once
		function createTracking($fileName){
			$this->fileName = $fileName;
			$res = $this->_prepareStatement("INSERT IGNORE INTO tracking (fileName) VALUES (?)", "s", $fileName);
		}
		
		function incrementLine($fileName, $exec_time){
			$this->exec_name = $exec_time;
			$this->p_updateLine->execute();
		}
		
		function getAllDevices(){
			$res = $this->dblink->query("Select * FROM devices");
			$d = array();
			while($row = $res->fetch_assoc()){
				$d[$row["device"]] = $row["device_id"];
			}
			return $d;
		}
		
		function getAllCompany(){
			$res = $this->dblink->query("Select * FROM companies");
			$d = array();
			while($row = $res->fetch_assoc()){
				$d[$row["company"]] = $row["company_id"];
			}
			return $d;
		}
		
		
		
		function insertDevice($value){
				$res = $this->_prepareStatement("INSERT INTO `devices` (device_type) VALUES (?)", "s", $value);
				return $this->dblink->insert_id;
		}
		
		function insertCompany($value){

				$res = $this->_prepareStatement("INSERT INTO `companies` (company_type) VALUES (?)", "s", $value);
				return $this->dblink->insert_id;

		
		}

		
		function selectAll(){
			$sql = "Select * from `devices`";
			try{
				$result = $this->dblink->query($sql);
				//$this->_printResults($result);
			} catch ( mysqli_sql_exception $e) { 
				//echo $e->getMessage();
				error_log($e->__toString(), 3, '/home/ubuntu/logs/mysqli_log.txt');
				exit();
			}
		}
	}
?>