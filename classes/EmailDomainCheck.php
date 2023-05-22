<?php
class EmailDomainCheck {
	
	private $_dbCmd;
	
	function __construct() {
	      
		$this->_dbCmd = new DbCmd();
	}
	
	function checkDomains(){

		$newDomainArr = array();
		
		// Check for newly added EmailDomainNames and add it to the check list
		$this->_dbCmd->Query("SELECT DISTINCT(SUBSTRING_INDEX(Email,'@',-1)) AS NewDomain FROM emailnotifycollection LEFT JOIN emailcheckdomains ON SUBSTRING_INDEX(email,'@',-1) = emailcheckdomains.DomainName WHERE emailcheckdomains.DomainName IS NULL");
	
		while($row = $this->_dbCmd->GetRow()) 
			$newDomainArr[] = $row["NewDomain"];

		foreach ($newDomainArr AS $newDomain) {

			$addNewDomain["DomainName"] = $newDomain;
			$addNewDomain["Status"]  = "N";
			$addNewDomain["Retries"] = 0;
			
			$this->_dbCmd->InsertQuery("emailcheckdomains", $addNewDomain);
		}
	
		// Now check all Domains
		$this->_dbCmd->Query("SELECT DomainName FROM emailcheckdomains WHERE Retries < 6 ORDER BY LastDate");
	
		while($row = $this->_dbCmd->GetRow()) 
			$domainNameArr[] = $row["DomainName"];
	
		foreach ($domainNameArr AS $domainName) {
			
			$this->_dbCmd->Query("SELECT * FROM emailcheckdomains WHERE DomainName = '$domainName'");
			$row = $this->_dbCmd->GetRow();
			
			$retries = $row["Retries"];
			$status  = $row["Status"];
			$id      = $row["ID"];
			$error_number = 0;       
			$error        = '';
			
			print ("Checking $domainName ... <br>");
						
			// Check if Port 25 is open, timeout after 10 secs 
			$socket = @fsockopen($domainName, 25, $error_number, $error, 10);

			if (is_resource($socket)) {  

				$domainCheckStatus = "O";
				fclose($socket);
			} 
			else { 
				
				$domainCheckStatus = "N";	
			
				if($status == "N") 
					$retries++;
					
				if($retries>5) 
					EmailNotifyCollection::markAllEmailsDneWithDomain($domainName);		
			}
			
			$domainUpdateArr["Status"]  = $domainCheckStatus;
			$domainUpdateArr["Retries"] = $retries; 
			$domainUpdateArr["LastDate"] = time(); 
			
			$this->_dbCmd->UpdateQuery("emailcheckdomains", $domainUpdateArr, "ID = $id");
		}
	}	
}
