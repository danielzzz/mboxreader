<?php

/** 
* LICENSE MIT
* (C) Daniel Zelisko
* (C) Maciej Grajcarek
* http://github.com/danielzzz/mboxreader
*
* a php mbox file format (firebird) parser
*/


	class reader{
		
		var $file_name = '';
		var $file_path = 'jobs.txt';
		var $file = null;
		var $messages = null;
		
		var $db_user = 'root';
		var $db_password = '';
		var $db_host = 'localhost';
		var $db_name = 'mbox';
		
		
		function readFile(){
			$this->file = fopen($this->file_path . $this->file_name, "rt");
		}
		
		function processFile(){
			
			$mail_number = -1;
			$reveived_num = -1;
			$received = false;
			$next_is_message = false;
			$start_copy = false;
			$message = '';
			
			
			$boundary = null;
			$boundary_copy = false;
			
			while (!feof($this->file)) {
				
				$file_line = fgets($this->file);
				//if its a new mail increment counter
				
				if (strpos($file_line, 'From - ') !== false && strlen($file_line) == 32 ){
					
					if ($message != ''){
						$this->messages[$mail_number]['message'] = $message;
						$message = '';
					}
					
					if (!empty($this->messages[$mail_number]['from'])){
						$this->insertData();
						unset($this->messages[$mail_number]);
					}
					
					++$mail_number;
					$next_is_message = false;
					$start_copy = false;
					$boundary = null;
				}
				
				if (strpos($file_line, 'Received: ') !== false){
					
					$ip = $this->__getIP($file_line);
					if ($ip != '')
						$this->messages[$mail_number]['received']['ip'] = $ip;
					
					++$reveived_num; 
					$this->messages[$mail_number]['received'][$reveived_num][] =  substr($file_line, 10);
					$received = true;
				}

				if(substr($file_line,0,1) == "	" && strpos($file_line, 'Received: ') === false && $received ){
					$this->messages[$mail_number]['received'][$reveived_num][] = substr($file_line, 1) ;					
				}
				
				if (strpos($file_line,'Date: ')  !== false){
					$this->messages[$mail_number]['date'] = substr($file_line, 6);
					$received = false;
				}
				
				if (strpos($file_line,'From: ') !== false){
					$this->messages[$mail_number]['from'] = substr($file_line, 6);
				}
				
				if (strpos($file_line,'To: ') !== false && strpos($file_line,'To: ') == 0 ){
					$this->messages[$mail_number]['to'] = substr($file_line, 4);
				}

				if (strpos($file_line,'Subject: ') !== false){
					$this->messages[$mail_number]['subject'] = substr($file_line, 9);
					$next_is_message = true;
				}				
			
						
				
				if ($next_is_message && $file_line == "\n" ){
					$start_copy = true;
				}
				
				
				if (strpos($file_line,'boundary=') ){
					$boundary = $this->__getBoundary($file_line);
				}
				
				
				if($start_copy ){
					
					
					if (isset ($boundary)){
						
						
						
						if(strpos($file_line, "Content-Disposition: inline") !== false || strpos($file_line, "Content-Transfer-Encoding: quoted-printable") !== false){
							$boundary_copy = true;
							continue;
						
						}else if (strpos($file_line, $boundary.'--') !== false){
							$boundary_copy = false;
							
						}else if (strpos($file_line, "Content-Disposition: attachment") !== false){
							$boundary_copy = false;							
						}else if (strpos($file_line, '--'.$boundary) !== false){
							$boundary_copy = false;							
						}
						
						
						if ($boundary_copy)
							$message .= $file_line;
						
					}else					
						$message .= $file_line;
				}
				
			}

			if ($message != ''){
				$this->messages[$mail_number]['message'] = $message;
				$message = '';
			}
		}

		function insertData(){
			$connect = mysql_connect($this->db_host,$this->db_user,$this->db_password);
			mysql_selectdb($this->db_name, $connect);
			
			$query = "	INSERT INTO messages(`from`, `to`, `subject`, `date`,`received_ip`, `message` )
						VALUES (";
			
			foreach ($this->messages as $message){
				$query_temp = $query;

				
				$query_temp .=  str_replace("\n", '', "'".$message['from']."' ,");
				$query_temp .=  str_replace("\n", '', "'".$message['to']."' ,");
				$query_temp .=  str_replace("\n", '', "'".$message['subject']."' ,");
				$query_temp .=  str_replace("\n", '', "'".$message['date']."' ,");
				$query_temp .=  "'" . $message['received']['ip'] ."' ,";
				
				$query_temp .=  str_replace("\n", '', "'".$message['message']."' )");
				
				addslashes ($query_temp);
				mysql_query($query_temp, $connect);
				
			}
			
			mysql_close($connect);
			
		}
		
		function showMessages(){
			return $this->messages;			
		}
	
		function __getIP($line){
			
			$begin = strpos($line,'[') ;
			$end = strpos($line,']');
			$lenght = $end - $begin -1;
			
			if ($lenght > 0)
				return substr($line,$begin + 1,$lenght);
			else
				return '';
		
		}
		
		function __getBoundary($file_line){
			$begin = strpos($file_line,'"');
			$end = strpos($file_line,'"',$begin + 1);
		
			$length = $end - $begin -1;
			
			if ($length > 0)
				return substr($file_line, $begin + 1, $length);
			else
				return null;
			
		}
	}
	
	

	

	$reader = new reader();
	$reader->readFile(); 
	$reader->processFile();
	//print_r($reader->showMessages());
	$reader->insertData();

?>

<!-- 
DROP TABLE IF EXISTS `messages`;
CREATE TABLE  `messages` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `from` varchar(255) collate latin1_general_ci NOT NULL,
  `to` varchar(255) collate latin1_general_ci NOT NULL,
  `subject` varchar(255) collate latin1_general_ci NOT NULL,
  `date` varchar(255) collate latin1_general_ci NOT NULL,
  `message` text collate latin1_general_ci NOT NULL,
  `received_ip` varchar(15) collate latin1_general_ci NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

 -->
