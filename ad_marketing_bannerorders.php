<?

require_once("library/Boot_Session.php");



$domainObj = Domain::singleton();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	throw new Exception("Permission Denied");


$t = new Templatex(".");


$t->set_file("origPage", "ad_marketing_bannerorders-template.html");


$start_timestamp = WebUtil::GetInput("starttime", FILTER_SANITIZE_INT);
$end_timestamp = WebUtil::GetInput("endtime", FILTER_SANITIZE_INT);
$ipAddress = WebUtil::GetInput("ipaddress", FILTER_SANITIZE_STRING_ONE_LINE);


$BannerName = WebUtil::GetInput("bannername", FILTER_SANITIZE_STRING_ONE_LINE);
$t->set_var("BANNER_NAME", WebUtil::htmlOutput($BannerName));

$t->set_var("START_DATE", date("F j, Y", $start_timestamp));
$t->set_var("END_DATE", date("F j, Y", $end_timestamp));


$EmptyList = true;


$t->set_block("origPage","OrdersBL","OrdersBLout");


$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);


$query = "SELECT DISTINCT ID FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
	AND Referral=\"" . DbCmd::EscapeSQL($BannerName) . "\"
	AND DateOrdered BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp ";


if($ipAddress)

	$query .= " AND IPaddress=\"" . DbCmd::EscapeSQL($ipAddress) . "\" ";
	
$query .= " ORDER BY ID DESC";


$dbCmd->Query($query);

$numberOrders = $dbCmd->GetNumRows();
	
while($thisOrderID = $dbCmd->GetValue()){
	
	$orderTotal = Order::GetGrandTotalOfOrder($dbCmd2, $thisOrderID);

	$t->set_var("TOTAL", '$' . $orderTotal);
	$t->set_var("ORDERNO", $thisOrderID);
	
	$EmptyList = false;
	
	$t->parse("OrdersBLout","OrdersBL",true);
}


if($EmptyList){
	$t->set_block("origPage","NoResultsBL","NoResultsBLout");
	$t->set_var("NoResultsBLout", "<br><br>No orders from this Banner Name.");
}


$t->set_var("ORDER_NUM", $numberOrders);


$t->pparse("OUT","origPage");




?>