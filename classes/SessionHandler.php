<?php

// A custom session handler is required if we are going to load balance the servers.  The database provides common session data for all nodes.
// Also it provides extra security over cross-domain websites since you can verify Domain ID's of Sessions against Domain ID's in the URL.

class SessionHandler {
	
	static $SESSION_DB_HOST = "localhost";
	static $SESSION_DB_NAME = "session_db";
	static $SESSION_DB_USER = "session_user";
	static $domainIDfromURL = 0;
	static $sessionID = "dont_know_yet";
	
	private static $sess_dbObj;

	// We need to get the usernames and passwords directly because during a Session Destroy the Class Auto-Loader and other objects are destroyed.
	static function getSessionDbPassword(){
		
		if(Constants::GetDevelopmentServer())
			$passwordFile = Constants::GetAccountBase() . "\\dot\\session_db_password.txt";
		else
			$passwordFile = Constants::GetAccountBase() . "/constants/session_db_password.txt";
		
		if(!file_exists ($passwordFile))
			exit ( "Error in static function getSessionDbPassword" );
		return trim(file_get_contents($passwordFile));
	}
	
	static function refreshDatabaseObject(){
		
		self::$sess_dbObj = mysql_connect(self::$SESSION_DB_HOST, self::$SESSION_DB_USER, self::getSessionDbPassword());
		
		if(self::$sess_dbObj){
			return mysql_select_db(self::$SESSION_DB_NAME, self::$sess_dbObj);
		}

        return false;
	}
	
	static function open($save_path, $session_name){

		self::$domainIDfromURL = Domain::getDomainIDfromURL();
		
		if(self::refreshDatabaseObject()){
			self::checkDosAttack();
			return true;
		}
		else {
			return false;
		}
	}
	
	static function close(){

		$qry = "SELECT RELEASE_LOCK('".mysql_real_escape_string(self::$sessionID)."')";
		mysql_query($qry, self::$sess_dbObj);
		
		return true;
	}
	
	static function read($phpSessionId){
		
		self::$sessionID = $phpSessionId;
		
		// Make sure that another thread does not wipe out a session variable.  
		$qry = "SELECT GET_LOCK('".mysql_real_escape_string(self::$sessionID)."',5)";
		mysql_query($qry, self::$sess_dbObj);
		
        $qry = "SELECT SessionData FROM sessions WHERE 
        		DomainID='".self::$domainIDfromURL."' 
        		AND SessionID = '" . mysql_real_escape_string($phpSessionId) . "'";
        
        
        if ($result = mysql_query($qry, self::$sess_dbObj)) {
            if (mysql_num_rows($result)) {
                $record = mysql_fetch_assoc($result);
                return base64_decode($record['SessionData']);
            }
            else{
            	//exit("no session.<br>" . $qry);
            }
        }
        return '';
	}
	
	static function checkDosAttack(){
		

		if(in_array($_SERVER['REMOTE_ADDR'], array("69.63.86.138", "74.62.46.166", "74.62.46.166", 
"69.63.86.138", 
"66.112.75.100", 
"66.112.75.100", 
"99.122.84.136", 
"108.66.163.29", 
"72.161.5.176", 
"173.22.115.29", 
"70.141.193.225", 
"173.28.254.219", 
"76.0.3.43", 
"99.28.125.244", 
"72.51.38.88",
"75.40.205.54","74.62.46.166"))){
			return;
		}
		
		
		// Don't DOS protect our private IP.
		if(preg_match('/^10\.0\./', $_SERVER['REMOTE_ADDR'])){
			return;
		}

		
		
		
		$DataSrc = Constants::GetDatabaseName();
		$HostName = Constants::GetDatabaseHost();
		$UserId = Constants::GetDatabaseUserID();
		$Password = Constants::GetDatabasePassword();
	
		$mysqlConObj = mysql_connect($HostName, $UserId, $Password);
		if($mysqlConObj){
			mysql_select_db($DataSrc, $mysqlConObj);
		}
		
        

		// Find out if the IP address is being counted.
		$isAnAttack = false;
		$qry = "SELECT AccessCount, UNIX_TIMESTAMP(DateCreated) AS DateCreated FROM dosattack WHERE IPaddress='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."'";
        if ($result = mysql_query($qry, $mysqlConObj)) {
            if (mysql_num_rows($result)) {
            	
                $record = mysql_fetch_assoc($result);
                $accessCount = $record['AccessCount'];
                $dateCreated = $record['DateCreated'];
            	
                if(empty($accessCount))
                	$accessCount = 1;
                          
                $accessCount++;
                    
                // If the date created is greater than x mintue(s), then free up the IP
                if(time() - $dateCreated > 60 * 60 * 24){
                	$deleteQry = "DELETE FROM dosattack WHERE IPaddress='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."'";
             		mysql_query($deleteQry, $mysqlConObj);
                }
                else{
                	// Figure out if they are over the threshold
                	if($accessCount > 600){
                		$isAnAttack = true;
                	}
                	
                	// Now update the count in the DB.
                	$updateQry = "UPDATE dosattack SET AccessCount='".$accessCount."' WHERE IPaddress='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."'";
                	mysql_query($updateQry, $mysqlConObj);
                }
            }
            else{
      	
            	// If there is not a record yet, create one.
            	$insertQry = "INSERT INTO dosattack (IPaddress,AccessCount,DateCreated) VALUES ('".$_SERVER['REMOTE_ADDR']."','1', '". strftime ("%Y-%m-%d %H:%M:%S", time()). "')";
            	mysql_query($insertQry, $mysqlConObj);
            }
        }
        
        /*  ---- Removing the 500 Errors.  They do nothing to mitigate a DOS attack.  Stop the IP's before Apache with IP Tables.
        
		$maxMindObj = new MaxMind();
		if($maxMindObj->loadIPaddressForLocationDetails($_SERVER['REMOTE_ADDR']) && $maxMindObj->getCountry() != "US"){
			header("HTTP/1.0 500 Internal Server Error");
			exit('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
			<html><head>
			<title>500 Internal Server Error</title>
			</head><body>
			<h1>500 Internal Server Error</h1>
			</body></html>');
		}
    
        if($isAnAttack){
			header("HTTP/1.0 500 Internal Server Error");
			exit('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
			<html><head>
			<title>500 Internal Server Error</title>
			</head><body>
			<h1>500 Internal Server Error</h1>
			</body></html>');
        }
        */

			
	}
	
	// Updates session data or creates a new session.
	static function write($phpSessionId, $sess_data){
	
		// Find out if we need to create a new Session.
		// The Session ID is a commination of the "PHP 32 bit Session ID" and our own internal "Domain ID".
		$idOfExistingSession = null;
		$qry = "SELECT ID FROM sessions WHERE SessionID='".mysql_real_escape_string($phpSessionId)."' AND DomainID=" . self::$domainIDfromURL;
	
        if ($result = mysql_query($qry, self::$sess_dbObj)) {
            if (mysql_num_rows($result)) {
                $record = mysql_fetch_assoc($result);
                $idOfExistingSession = $record['ID'];
            }
        }
        
        $mysqlTimeStamp = strftime ( "%Y-%m-%d %H:%M:%S", time() );

		if(!$idOfExistingSession){

			$qry = "INSERT INTO sessions 
						(SessionID, SessionData, LastAccess, DomainID) 
						VALUES('".mysql_real_escape_string($phpSessionId)."', 
								'".mysql_real_escape_string(base64_encode($sess_data))."',
								'".mysql_real_escape_string($mysqlTimeStamp)."', 
								'".self::$domainIDfromURL."')";
			return mysql_query($qry, self::$sess_dbObj);

		}
		else{
			
			$qry = "UPDATE sessions 
						SET SessionData = '".mysql_real_escape_string(base64_encode($sess_data))."', 
						LastAccess = '".mysql_real_escape_string($mysqlTimeStamp)."'
						WHERE ID=$idOfExistingSession";

			return mysql_query($qry, self::$sess_dbObj);
		}

		
	}
	
	
	static function destroy($phpSessionId){
		
		$qry = "DELETE FROM sessions WHERE SessionID = '".mysql_real_escape_string($phpSessionId)."' AND DomainID=" . self::$domainIDfromURL;
		return mysql_query($qry, self::$sess_dbObj);
		
	}
	
	
	static function gc($maxlifetime){
		
		$qry = "DELETE FROM sessions WHERE LastAccess < '" . strftime ( "%Y-%m-%d %H:%M:%S", (time() - intval($maxlifetime))) . "'";
		return mysql_query($qry, self::$sess_dbObj);

	}


}









