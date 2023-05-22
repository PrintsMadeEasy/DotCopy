<?


class ReturnedPackages {

	private $_dbCmd;
	private $_errorMessage;




	###-- Constructor --###
	function ReturnedPackages(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		$this->_errorMessage = "";

	}
	
	// Returns True or False depending on whether it can mark an order as been Returned... If false you need to get the error message
	// Pass in a Project Number like "P234343" and it will look up the Order Number and mark the order as defective
	// It is easy to do it by project number because they just need to scan a Bar Code From one of the project in the order.
	// It is possible for an Order to be notified twice on a returned package.... but it will complain if you try to call this method on an Order that already has an "Open" notification

	function MarkOrderAsReturned($ProjectNumber, $UserID){
	
		WebUtil::EnsureDigit($UserID);
		
		// Possibly Strip off the P in front
		if(preg_match("/^P\d+$/i", $ProjectNumber))
			$ProjectNumber = substr($ProjectNumber, 1);
		
		if(!preg_match("/^\d+$/", $ProjectNumber))
			throw new Exception("Invalid Project number");
			
		$domainObj = Domain::singleton();
		
		$this->_dbCmd->Query("SELECT OrderID FROM projectsordered WHERE ID=$ProjectNumber AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
		if($this->_dbCmd->GetNumRows() == 0){
			$this->_errorMessage = "The project number does not exist.";
			return false;
		}
		$thisOrderID = $this->_dbCmd->GetValue();
		
		$this->_dbCmd->Query("SELECT Status FROM projectsordered WHERE ID =" . $ProjectNumber);
		if($this->_dbCmd->GetValue() != "F"){
			$this->_errorMessage = "This order is not Finished yet, so you can not mark the package as returned.";
			return false;
		}
		
		// First make sure that there are not any other Open returned package notifications
		$this->_dbCmd->Query("SELECT COUNT(*) FROM returnedpackages WHERE OrderID=$thisOrderID AND Status='O'");
		if($this->_dbCmd->GetValue() != 0){
			$this->_errorMessage = "This order has already been marked as returned.";
			return false;
		}
		
		// Now record the package has beeing returned
		$InsertRow["OrderID"] = $thisOrderID;
		$InsertRow["Status"] = "O";
		$NotificationID = $this->_dbCmd->InsertQuery("returnedpackages", $InsertRow);
		
		// Add a default message showing the package was returned
		$this->AddMessageToNotification($NotificationID, $UserID, "Package has been returned.");
	
		return true;
	}
	
	function CloseNotificationOfReturnedPackage($NotificationID, $UserID){
		WebUtil::EnsureDigit($NotificationID);
		WebUtil::EnsureDigit($UserID);
		
		
		if(!$this->checkForPermissionOnNotificationID($NotificationID))
			throw new Exception("The Notification ID is not permitted.");
			
		// Add a message saying that it was closed
		$this->AddMessageToNotification($NotificationID, $UserID, "Closing Returned Package Notification");
		
		$this->_dbCmd->Query("UPDATE returnedpackages SET Status='C' WHERE ID=" . $NotificationID);
	
	}
	function AddMessageToNotification($NotificationID, $UserID, $Message){
	
		WebUtil::EnsureDigit($NotificationID);
		WebUtil::EnsureDigit($UserID);

		if(!$this->checkForPermissionOnNotificationID($NotificationID))
			throw new Exception("The Notification ID is not permitted.");
		
		$InsertRow["Date"] = date("YmdHis");
		$InsertRow["ReturnedPackageID"] = $NotificationID;
		$InsertRow["Message"] = $Message;
		$InsertRow["UserID"] = $UserID;
		$this->_dbCmd->InsertQuery("returnedpackagesmessages", $InsertRow);
	}
	
	// Returns HTML for the Messages belonging to a Returned Package Notification.
	// it will have the most recent message up on top.
	// It will return a 100% width table
	// If the notification ID is valid... we should always have at least 1 message
	function GetMessagesFromNotificationID($NotificationID){

		if(!$this->checkForPermissionOnNotificationID($NotificationID))
			throw new Exception("The Notification ID is not permitted.");

		$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DATE) AS UnixDate FROM returnedpackagesmessages WHERE ReturnedPackageID=$NotificationID ORDER BY ID DESC");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Illegal Notifiation ID in method call GetMessagesFromNotificationID");

		$MessageHash = array();
		while($row = $this->_dbCmd->GetRow())
			$MessageHash[] = $row;

		$retHTML = '<table width="100%" border="0" cellspacing="0" cellpadding="0">';

		foreach($MessageHash as $ThisMessage){
			
			$retHTML .= '
				<tr>
				  <td bgcolor="#AAAAAA">
				    <table width="100%" border="0" cellspacing="0" cellpadding="0">
				      <tr>
					<td width="50%" class="ReallySmallBody" style="color:#FFFFFF">&nbsp;From:
					  &nbsp;&nbsp;<b>' . WebUtil::htmlOutput(UserControl::GetNameByUserID($this->_dbCmd, $ThisMessage["UserID"]))  . '</b></td>
					<td width="50%" class="ReallySmallBody" align="right" style="color:#FFFFFF">'. date("M j, Y g:i a", $ThisMessage["UnixDate"])  .  '&nbsp;<img src="./images/greypixel.gif" border="0" width="2" height="15" align="absmiddle"></td>
				      </tr>
				    </table>
				  </td>
				</tr>
				<tr>
				  <td bgcolor="#666666"><img src="./images/transparent.gif" border="0" width="1" height="2"></td>
				</tr>
				<tr>
				  <td class="SmallBody">
				  <table width="100%" cellpadding="3" cellspacing="0" border="0" style="border-color:#CCCCCC; border-style:solid; border-width:1;">
				  <tr>
				  <td class="SmallBody">

				    '  . WebUtil::htmlOutput($ThisMessage["Message"]) .  '<br>
				    </td></tr></table>
				    </td>
				</tr>
			';
		}

		$retHTML .= '</table>';
		return $retHTML;
	}
	
	// Will contain the Command links for each notficiation based upon its status

	// Returns HTML with Javascript hyperlinks for invoking a command in the browser...  Make sure the appropriate Javascipt function exist.
	function GetCommandLinksFromNotificationID($NotificationID){
	
		WebUtil::EnsureDigit($NotificationID);
	
		if(!$this->checkForPermissionOnNotificationID($NotificationID))
			throw new Exception("The Notification ID is not permitted.");
		
		$retHTML = "<a href=\"javascript:AddReturnedPackageMessage('$NotificationID');\" class='BlueRedLink'>Add Message</a>";
		
		// If it is not already closed... then give them the link to close it.
		$this->_dbCmd->Query("SELECT Status FROM returnedpackages WHERE ID=$NotificationID");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Illegal NotificationID in method call to GetCommandLinksFromNotificationID");
		
		if($this->_dbCmd->GetValue() == "O")
			$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='5'><br><a href=\"javascript:CloseReturnedPackageNotification('$NotificationID');\" class='BlueRedLink'>Close Notification</a>";

		return $retHTML;
	}

	function GetCountOfReturnedPackages(){
		
		$domainObj = Domain::singleton();
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM returnedpackages 
					INNER JOIN orders ON orders.ID = returnedpackages.OrderID 
					WHERE returnedpackages.Status='O' 
					AND " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()));
		return $this->_dbCmd->GetValue();
	}
	
	// Returns an Array of Notification ID's
	function GetOpenReturnedPackages(){
	
		$domainObj = Domain::singleton();
		$retArr = array();
		$this->_dbCmd->Query("SELECT returnedpackages.ID FROM returnedpackages 
							INNER JOIN orders ON orders.ID = returnedpackages.OrderID 
							WHERE Status='O'
							AND " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()));
		while($id = $this->_dbCmd->GetValue())
			$retArr[] = $id;
		return $retArr;
	
	}
	function GetReturnedPackagesByOrderID($OrderID){
	
		WebUtil::EnsureDigit($OrderID);
		
		$domainObj = Domain::singleton();
		
		$retArr = array();
		$this->_dbCmd->Query("SELECT returnedpackages.ID FROM returnedpackages 
								INNER JOIN orders ON orders.ID = returnedpackages.OrderID 
								WHERE returnedpackages.OrderID=$OrderID
								AND " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()));
		while($id = $this->_dbCmd->GetValue())
			$retArr[] = $id;
		return $retArr;
	}
	
	function GetOrderNumberFromNotificationID($NotificationID){
		WebUtil::EnsureDigit($NotificationID);
		
		$this->_dbCmd->Query("SELECT OrderID FROM returnedpackages WHERE ID=$NotificationID");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Illegal NotificationID in method GetOrderNumberFromNotificationID");
		return $this->_dbCmd->GetValue();
	
	}
	function GetCustomerNameFromNotificationID($NotificationID){
		WebUtil::EnsureDigit($NotificationID);
		
		if(!$this->checkForPermissionOnNotificationID($NotificationID))
			throw new Exception("The Notification ID is not permitted.");
		
		$thisOrderID = $this->GetOrderNumberFromNotificationID($NotificationID);
		
		$this->_dbCmd->Query("SELECT ShippingName, ShippingCompany FROM orders WHERE ID=$thisOrderID");
		$row = $this->_dbCmd->GetRow();

		if(empty($row["ShippingCompany"]))
			return $row["ShippingName"];
		else
			return $row["ShippingCompany"];	
	
	}
	function GetErrorMessage(){
		return $this->_errorMessage;
	}
	
	
	function checkForPermissionOnNotificationID($NotificationID){
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$allDomainIDsOfUser = $passiveAuthObj->getUserDomainsIDs();
		
		$orderID = $this->GetOrderNumberFromNotificationID($NotificationID);
		
		$domainIDofOrder = Order::getDomainIDFromOrder($orderID);
		
		if(!in_array($domainIDofOrder, $allDomainIDsOfUser))
			return false;
		else
			return true;
	}
	


}


?>