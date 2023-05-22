<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();





//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$t = new Templatex();

$t->set_file("origPage", "myaccount_sales_customer-template.html");


$SalesMaster = $AuthObj->CheckForPermission("SALES_MASTER");

// The Sales Master can see everyone.
if($SalesMaster)
	$SalesID = 0;
else
	$SalesID = $UserID;

$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());
$SalesCommissionsObj->SetUser($SalesID);

// Hide the Sales Block if they don't have permissions
$SalesRepObj = new SalesRep($dbCmd);
if(!$SalesRepObj->LoadSalesRep($UserID) && !$SalesMaster)
	WebUtil::PrintError("You do not have permissions to view this.");


$CustomerID = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);
$CustomerID = intval($CustomerID);
if(empty($CustomerID))
	throw new Exception("The Customer ID is missing.");
	


	
if(!$SalesCommissionsObj->CheckIfCustomerHasSalesRep($CustomerID))
	throw new Exception("The given customer ID does not have a sales rep.");

// Get the Base sales rep and the Expiration out of the users table
$dbCmd->Query("SELECT SalesRep, UNIX_TIMESTAMP(SalesRepExpiration) as Expiration FROM users WHERE ID=$CustomerID");
if($dbCmd->GetNumRows() == 0)
	throw new Exception("Customer does not exist.");
	
$domainIDofCustomer = UserControl::getDomainIDofUser($CustomerID);
if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofCustomer))
	throw new Exception("The Users Domain doesn't exist.");
	
$UsersRow = $dbCmd->GetRow();
$BaseSalesRep = $UsersRow["SalesRep"];
$CommissionExpiration = $UsersRow["Expiration"];

$SalesLinkChainArr = $SalesCommissionsObj->GetChainFromSubRepToUser($BaseSalesRep);

// Make sure that the customer belongs to the Sales Rep... or to one of the children of this sales rep
if($UserID != in_array($UserID, $SalesLinkChainArr) && !$SalesMaster)
	throw new Exception("Sales Rep does not have permissions to see this customer.");

$dbCmd->Query("SELECT ID FROM orders WHERE UserID=$CustomerID");
if($dbCmd->GetNumRows() == 0)
	throw new Exception("Customer does not have any orders.");

$OrderIDarr = array();
while($thisOrdID = $dbCmd->GetValue())
	$OrderIDarr[] = $thisOrdID;

$t->set_block("origPage","CustomerHistoryBL","CustomerHistoryBLout");

$OrderFound = false;
foreach($OrderIDarr as $thisOrdID){

	$dbCmd->Query("SELECT UNIX_TIMESTAMP(OrderCompletedDate) AS Date, SubtotalAfterDiscount FROM salescommissions WHERE OrderID=$thisOrdID ORDER BY OrderCompletedDate DESC");
	
	// This may skip orders before the Sales person was associated with the customer
	if($dbCmd->GetNumRows() == 0)
		continue;
		
	$OrderFound = true;
		
	$SalesCommissionRow =  $dbCmd->GetRow();
	
	$t->set_var("ORDER_NO", $thisOrdID);
	$t->set_var("DATE", date("n/j/y", $SalesCommissionRow["Date"]));
	$t->set_var("SUBTOTAL", $SalesCommissionRow["SubtotalAfterDiscount"]);
	
	$t->parse("CustomerHistoryBLout","CustomerHistoryBL",true);
	
}

if(!$OrderFound)
	$t->set_var("CustomerHistoryBLout", "");


$UserObj = new UserControl($dbCmd);
$UserObj->LoadUserByID($CustomerID);
$t->set_var("CUSTOMER_NAME", WebUtil::htmlOutput($UserObj->getCompanyOrName()));

if(empty($CommissionExpiration))
	$t->set_var("EXPIRATION", "never expire");
else
	$t->set_var("EXPIRATION", "expire on " . date("n/j/y", $CommissionExpiration));


// Now build a list of all sales reps associated with this customer
// The sales Reps listed here must
$PercentagesHTML = "";
foreach($SalesLinkChainArr as $ThisSalesID){

	// Don't include the Root (which could be part of the SalesChainLink Arr
	if($ThisSalesID == 0)
		continue;

	$dbCmd->Query("SELECT CommissionPercent FROM salesrepsrateslocked WHERE CustomerUserID=$CustomerID AND SalesUserID=$ThisSalesID");
	if($dbCmd->GetNumRows() == 0)
		throw new Exception("The Sales ID $ThisSalesID for Customer $CustomerID does not exist within salesrepsrateslocked");
	
	$UserObj->LoadUserByID($ThisSalesID);
	
	$PercentagesHTML .= WebUtil::htmlOutput($UserObj->getName()) . ": " . $dbCmd->GetValue() . "%<br>";
}

$t->set_var("PERCENTAGES", $PercentagesHTML);
$t->allowVariableToContainBrackets("PERCENTAGES");


$t->pparse("OUT","origPage");


?>