<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();

$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("SALES_MASTER"))
	throw new Exception("Permission Denied");


$t = new Templatex(".");

$t->set_file("origPage", "ad_sales_abuse-template.html");


$EmptyList = true;

// First gather a list of all Sales Reps
$SalesIDarr = array();
$dbCmd->Query("SELECT UserID FROM salesreps");
while($SalesUserID = $dbCmd->GetValue())
	$SalesIDarr[] = $SalesUserID;

$t->set_block("origPage","AbuseBL","AbuseBLout");
foreach($SalesIDarr as $SalesUserID){

	$CustomerIDarr = array();
	$dbCmd->Query("SELECT DISTINCT ID FROM users WHERE SalesRep=$SalesUserID AND DomainID = " . Domain::oneDomain());
	while($CustomerID = $dbCmd->GetValue())
		$CustomerIDarr[] = $CustomerID;
	
	$TotalInstancesAbuse = 0;
	
	// Go through each Customer belonging to this Sales Rep
	foreach($CustomerIDarr as $CustomerID){
	
		// Get the first order number that this customer ever placed.
		$dbCmd->Query("SELECT MIN(ID) FROM orders WHERE UserID=$CustomerID");
		$MinOrderID = $dbCmd->GetValue();

		if(!$MinOrderID){
			print "The User ID $CustomerID belongs to the Sales Rep $SalesUserID but no orders have been placed.<br>";
			continue;
		}		
			
		// If we find out that it isn't linked to a Sales Commissions... 
		// it means that the Sales Rep picked up an existing customer
		$dbCmd->Query("SELECT COUNT(*) FROM salescommissions WHERE OrderID=$MinOrderID");
		if($dbCmd->GetValue() == 0)
			$TotalInstancesAbuse++;
			

		
	}
	
	
	$SalesCommissionsObj->SetUser($SalesUserID);
	$SalesCommissionsObj->SetDateRange(2002, 1, 1, 2020, 1, 1);
	
	$totalOrders = $SalesCommissionsObj->GetNumOrdersFromSalesRep($SalesUserID);
	
	// Can't divide by a Zero
	if($totalOrders == 0)
		continue;
	
	$EmptyList = false;
	
	$t->set_var("SALES_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $SalesUserID)));
	$t->set_var("INSTANCES", $TotalInstancesAbuse);
	$t->set_var("TOTALORDERS", $totalOrders);
	$t->set_var("SALES_ID", $SalesUserID);
	$t->set_var("RATIO", ceil(round($TotalInstancesAbuse / $totalOrders, 2) * 100));
	
	
	$t->parse("AbuseBLout","AbuseBL",true);

}

if($EmptyList){
	$t->set_block("origPage","NoAbuseBL","NoAbuseBLout");
	$t->set_var("NoAbuseBLout", "Nothing to report right now.");
}


$t->pparse("OUT","origPage");



?>