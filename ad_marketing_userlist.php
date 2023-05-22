<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	throw new Exception("Permission Denied");

$domainObj = Domain::singleton();
	
$t = new Templatex(".");


$t->set_file("origPage", "ad_marketing_userlist-template.html");


$start_timestamp = WebUtil::GetInput("starttime", FILTER_SANITIZE_INT);
$end_timestamp = WebUtil::GetInput("endtime", FILTER_SANITIZE_INT);
$ReportType = WebUtil::GetInput("ReportType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$PageTitle = WebUtil::GetInput("PageTitle", FILTER_SANITIZE_STRING_ONE_LINE);  // Page Title is Useful on a UserIDarr Report... when the Page Title can't be implied by the type of report.
$CustomMessage = WebUtil::GetInput("CustomMessage", FILTER_SANITIZE_STRING_ONE_LINE);  // CustomMessageis Useful on a UserIDarr Report... when the Page Title can't be implied by the type of report.


$t->set_var("START_DATE", date("F j, Y", $start_timestamp));
$t->set_var("END_DATE", date("F j, Y", $end_timestamp));


$EmptyList = true;


$t->set_block("origPage","UsersBL","UsersBLout");


$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);


if($ReportType == "DeadAccounts"){

	$dbCmd->Query("SELECT ID FROM users WHERE DateCreated BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp 
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "
					ORDER BY ID DESC");

	$userCounts = 0;
	$userCountsWithSavedProjects = 0;
	while($thisUserID = $dbCmd->GetValue()){

		$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $thisUserID );
		$orderCount = $dbCmd2->GetValue();
		
		if($orderCount == 0){
			
			$userCounts++;
			
			$domainIDofUser = UserControl::getDomainIDofUser($thisUserID);
			
			$dbCmd2->Query("SELECT COUNT(*) FROM projectssaved WHERE UserID=" . $thisUserID );
			$savedProjectCount = $dbCmd2->GetValue();
			
			if($savedProjectCount != 0){
				$savedProjectCount = "<a href='javascript:SaveProj(\"".Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL())."\", $thisUserID);'>" . $savedProjectCount . "</a>";
				$userCountsWithSavedProjects++;
			}
			
			$t->set_var("VALUE2", $savedProjectCount);
			$t->allowVariableToContainBrackets("VALUE2");
			
			$t->set_var("USERID", $thisUserID);

			$EmptyList = false;

			$t->parse("UsersBLout","UsersBL",true);
		}
	}


	if($EmptyList){
		$t->set_block("origPage","NoResultsBL","NoResultsBLout");
		$t->set_var("NoResultsBLout", "<br><br>No Dead Accounts found within this period.");
	}


	$t->set_var("ACCOUNTS_NUM", $userCounts);
	
	$t->set_var("USER_LIST_TYPE", "Dead Accounts");
	
	$t->set_var("CUSTOM_MESSAGE", "Number of Accounts with at least 1 Saved Project: <b> $userCountsWithSavedProjects </b> <br><br>");
	$t->allowVariableToContainBrackets("CUSTOM_MESSAGE");
	
	
	$t->set_var("COLUMN2", "Saved");
	
	

}
else if($ReportType == "MemosType"){

	$custReaction = WebUtil::GetInput("cstReac", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	if(strlen($custReaction) != 1)	
		throw new Exception("Error with Customer Reaction Char.");

	$dbCmd->Query("SELECT DISTINCT(CustomerUserID) FROM customermemos INNER JOIN users ON customermemos.CustomerUserID = users.ID 
			WHERE CustomerReaction = '" . DbCmd::EscapeSQL($custReaction) . "'  
			AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "
			AND Date BETWEEN '$start_mysql_timestamp' AND '$end_mysql_timestamp'");

	$userCounts = 0;

	while($thisUserID = $dbCmd->GetValue()){

		$userCounts++;
	
		$countsLink = "<a class='BlueRedLinkRecSM' href='javascript:ShowCustomerMemos($thisUserID);'>" . CustomerMemos::getLinkDescription($dbCmd2, $thisUserID, true) . "</a>";
	
		$t->set_var("VALUE2", $countsLink);
		$t->allowVariableToContainBrackets("VALUE2");
		
		$t->set_var("USERID", $thisUserID);

		$EmptyList = false;

		$t->parse("UsersBLout","UsersBL",true);
	}


	if($EmptyList){
		$t->set_block("origPage","NoResultsBL","NoResultsBLout");
		$t->set_var("NoResultsBLout", "<br><br>No Memos found within this period.");
	}


	$t->set_var("ACCOUNTS_NUM", $userCounts);
	
	$t->set_var("USER_LIST_TYPE", "Customer Memos");
	
	$t->set_var("CUSTOM_MESSAGE", "Listing users who have a Customer Memo Reaction of <font class='SmallBody'><font color='#660000'><b><em><u>" . WebUtil::htmlOutput(CustomerMemos::getReactionDescription($custReaction)) . "</u></em></b></font></a> within the report period.<br><br>");
	$t->allowVariableToContainBrackets("CUSTOM_MESSAGE");
	
	$t->set_var("COLUMN2", "Memos");
	
	

}
else if($ReportType == "UserIDarr"){

	// Expect a pipe-delimited list of UserIDs.
	$userlist = WebUtil::GetInput("userlist", FILTER_SANITIZE_STRING_ONE_LINE);
	
	$userIDArr = array();
	
	$tempArr = split("\|", $userlist);
	
	foreach($tempArr as $thisUserID){
		if(preg_match("/^\d+$/", $thisUserID))
			$userIDArr[] = $thisUserID;
	}


	foreach($userIDArr as $thisUserID){
	
		$countsLink = "";
	

		$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $thisUserID . " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
		$orderCount = $dbCmd2->GetValue();

		if($orderCount != 0)
			$countsLink = "<a href='javascript:Cust($thisUserID);'>" . $orderCount . "</a>";
		else
			$countsLink = "0";
			

		$domainIDofUser = UserControl::getDomainIDofUser($thisUserID);

		$countsLink .= "&nbsp;&nbsp; / &nbsp;&nbsp;";
		
		
		$dbCmd2->Query("SELECT COUNT(*) FROM projectssaved WHERE UserID=" . $thisUserID . " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
		$savedProjectCount = $dbCmd2->GetValue();

		if($savedProjectCount != 0)
			$countsLink .= "<a href='javascript:SaveProj(\"".Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL())."\", $thisUserID);'>" . $savedProjectCount . "</a>";
		else
			$countsLink .= "0";

		$t->set_var("VALUE2", $countsLink);
		$t->allowVariableToContainBrackets("VALUE2");
		
		$t->set_var("USERID", $thisUserID);

		$EmptyList = false;

		$t->parse("UsersBLout","UsersBL",true);

	}


	if($EmptyList){
		$t->set_block("origPage","NoResultsBL","NoResultsBLout");
		$t->set_var("NoResultsBLout", "<br><br>No Accounts found.");
	}


	$t->set_var("ACCOUNTS_NUM", sizeof($userIDArr));
	
	$t->set_var("USER_LIST_TYPE", WebUtil::htmlOutput($PageTitle));
	
	
	if(empty($CustomMessage))
		$CustomMessage = WebUtil::htmlOutput($CustomMessage);
	else
		$CustomMessage = WebUtil::htmlOutput($CustomMessage) . "<br><br>";
	
	$t->set_var("CUSTOM_MESSAGE", $CustomMessage );
	$t->allowVariableToContainBrackets("CUSTOM_MESSAGE");

	
	
	$t->set_var("COLUMN2", "<nobr>Ord / Save</nobr>");
	


}
else{

	throw new Exception("Illegal Report Type");
}


$t->pparse("OUT","origPage");




?>