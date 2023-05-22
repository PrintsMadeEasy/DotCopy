<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$starttime = WebUtil::GetInput("starttime", FILTER_SANITIZE_INT);
$endtime = WebUtil::GetInput("endtime", FILTER_SANITIZE_INT);



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();


$t = new Templatex(".");
$t->set_file("origPage", "ad_balance_adjustments-template.html");




$t->set_block("origPage","AdminAdjustmentBL","AdjustmentsBLout");

$startSQLtimstamp = date("YmdHis", $starttime);
$endSQLtimstamp = date("YmdHis", $endtime);


$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE, WebUtil::GetCookie("BalanceAdjustmentsSearch", ""));


// Record the last Keyword Search into a Cookie. 
// If we ever get a null value within the input field... then we can use the last value searched for.
$DaysToRemember = 300;
$cookieTime = time()+60*60*24 * $DaysToRemember;
setcookie("BalanceAdjustmentsSearch" , $keywords, $cookieTime);





// Get list adjustments
$query = "SELECT OrderID, CustomerAdjustment, VendorAdjustment, Description, FromUserID, 
		UNIX_TIMESTAMP(DateCreated) DateCreated, VendorID FROM balanceadjustments 
		INNER JOIN orders ON orders.ID = balanceadjustments.OrderID
		WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
		AND (balanceadjustments.DateCreated BETWEEN " . $startSQLtimstamp . " AND " . $endSQLtimstamp . ") ";
		
if(!empty($keywords))
	$query .= " AND Description LIKE \"%" . DbCmd::EscapeLikeQuery($keywords) . "%\"";

$dbCmd->Query($query);


$VendorAdjusmentTotal = 0;
$CustomerAdjusmentTotal = 0;
$counter = 0;

while($row = $dbCmd->GetRow()){

	$thisOrderID = $row["OrderID"];
	$customerAdjust = $row["CustomerAdjustment"];
	$vendorAdjust = $row["VendorAdjustment"];
	$adjustedByUserID = $row["FromUserID"];
	$Adjst_desc = $row["Description"];
	$Adjustcreated = $row["DateCreated"];
	$ThisVendorID = $row["VendorID"];
	
	$CustomerAdjusmentTotal += $customerAdjust;
	$VendorAdjusmentTotal += $vendorAdjust;
	
	
	$counter++;
	

	// Get the Vendor name of who the adjustment belongs to
	$adjst_VendorName = "&nbsp;";

	if(!empty($vendorAdjust)){
		$dbCmd2->Query("SELECT Company FROM users WHERE ID=" . $ThisVendorID);
		$adjst_VendorName = $dbCmd2->GetValue();
	}

	if(empty($adjustedByUserID))
		$adjustedByUserName = "System";
	else
		$adjustedByUserName = UserControl::GetNameByUserID($dbCmd2, $adjustedByUserID);



	// Color red or green for pos or neg
	if(!empty($customerAdjust))
		$customerAdjust = number_format($customerAdjust, 2);
	if(!empty($vendorAdjust))
		$vendorAdjust = number_format($vendorAdjust, 2);

	$Adjustcreated = date ("M. d", $Adjustcreated);

	$t->set_var(array(	"VENDORADUST"=>Widgets::GetColoredPrice($vendorAdjust), 
				"CUSTOMERADJUST"=>Widgets::GetColoredPrice($customerAdjust), 
				"ADJUSTDATE"=>$Adjustcreated, 
				"ADJUSTBY"=>WebUtil::htmlOutput($adjustedByUserName), 
				"ADJUSTSUMMARY"=>$Adjst_desc, 
				"ADJ_ORDERID"=>$thisOrderID, 
				"V_NAME"=>$adjst_VendorName
			));
			
	$t->allowVariableToContainBrackets("VENDORADUST");
	$t->allowVariableToContainBrackets("CUSTOMERADJUST");

	$t->parse("AdjustmentsBLout","AdminAdjustmentBL",true);

}








$t->set_var("TOTALVENDORADJUST", Widgets::GetColoredPrice($VendorAdjusmentTotal, 2));
$t->set_var("TOTALCUSTOMERADJUST", Widgets::GetColoredPrice($CustomerAdjusmentTotal, 2));

$t->allowVariableToContainBrackets("TOTALVENDORADJUST");
$t->allowVariableToContainBrackets("TOTALCUSTOMERADJUST");

$t->set_var("START_DATE", date("M j, Y", $starttime));
$t->set_var("END_DATE", date("M j, Y", $endtime));
$t->set_var("ADJUSTMENTS_COUNT", $counter);


$t->set_var("START_TIMESTAMP", $starttime);
$t->set_var("END_TIMESTAMP", $endtime);




$t->set_var("KEYWORDS", WebUtil::htmlOutput($keywords));



if($counter == 0){
	$t->set_block("origPage","NoResultsBL","NoResultsBLout");
	$t->set_var("NoResultsBLout", "No Adjustments found within the period.");
}


$t->pparse("OUT","origPage");



?>