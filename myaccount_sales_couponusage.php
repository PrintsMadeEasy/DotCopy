<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();

$couponsObj = new Coupons($dbCmd);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$SalesMaster = $AuthObj->CheckForPermission("SALES_MASTER");

$SalesRepID = WebUtil::GetInput("salesrep", FILTER_SANITIZE_INT);





// Check and make sure the salesrep ID coming in the URL is a subrep... or is the person that is logged in.
if(!$SalesMaster){

	$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());
	$SalesCommissionsObj->SetUser($UserID);
	
	if(!$SalesCommissionsObj->CheckIfSalesRepBelongsToUser($SalesRepID))
		throw new Exception("You do not have permissions to view this Sales Rep");

}


// Load the Sales Rep object with the Sales Rep ID that came in the URL.  .
$SalesRepObj = new SalesRep($dbCmd);
if(!$SalesRepObj->LoadSalesRep($SalesRepID))
	WebUtil::PrintError("You do not have permissions to view this.");




$t = new Templatex();

$t->set_file("origPage", "myaccount_sales_couponusage-template.html");


$t->set_block("origPage","UsageBL","UsageBLout");


$CouponID = WebUtil::GetInput("couponid", FILTER_SANITIZE_INT);
$CouponID = intval($CouponID);
if(empty($CouponID))
	throw new Exception("Missing Coupon ID");


$month = WebUtil::GetInput("month", FILTER_SANITIZE_INT);
$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT);

if(!$month || !$year)
	throw new Exception("The date period is missing.");


if($month == "ALL"){
	$StartSQL = date("YmdHis", mktime(1, 1, 1, 1, 1, $year));
	$EndSQL = date("YmdHis", mktime(1, 1, 1, 13, 1, $year));
	$t->set_var("REPORT_PERDIOD", "All of $year");
}
else{
	$StartSQL = date("YmdHis", mktime(1, 1, 1, $month, 1, $year));
	$EndSQL = date("YmdHis", mktime(1, 1, 1, ($month+1), 1, $year));
	$t->set_var("REPORT_PERDIOD", date("F", mktime(1, 1, 1, $month, 1, $year)) . " $year");
}


$couponsObj->LoadCouponByID($CouponID);

$t->set_var("COUPON", $couponsObj->GetCouponCode());


$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) AS Date, orders.BillingCompany as Company, orders.BillingName as Name, orders.UserID as CustomerID
		FROM orders
		INNER JOIN users on users.ID = orders.UserID
		WHERE orders.CouponID=$CouponID AND users.SalesRep=$SalesRepID
		AND orders.DateOrdered BETWEEN $StartSQL AND $EndSQL
		AND orders.DomainID = ".Domain::oneDomain()."
		ORDER BY orders.ID DESC");

$counter = 0;

while($row = $dbCmd->GetRow()){

	$t->set_var("DATE", date("M j, Y", $row["Date"]));
	$t->set_var("NAME", WebUtil::htmlOutput($row["Name"]));
	$t->set_var("COMPANY", WebUtil::htmlOutput($row["Company"]));
	$t->set_var("CUSTOMER_ID", $row["CustomerID"]);
	
	$t->parse("UsageBLout","UsageBL",true);

	$counter++;
	
	unset($row);
}


if($counter == 0)
	$t->set_var("UsageBLout", "<tr><td class='body'>No Coupon Usage.</td></tr>");


$t->pparse("OUT","origPage");



?>