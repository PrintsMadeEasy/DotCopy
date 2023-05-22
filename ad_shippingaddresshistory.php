<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$orderID = WebUtil::GetInput("orderid", FILTER_SANITIZE_INT);
$orderID = intval($orderID);

if(!Order::CheckIfUserHasPermissionToSeeOrder($orderID))
	throw new Exception("The Order ID is not available.");


$t = new Templatex(".");

$t->set_file("origPage", "ad_shippingaddresshistory-template.html");



$dbCmd->Query("SELECT UserID FROM orders WHERE ID=" . $orderID);
$CustomerID = $dbCmd->GetValue();

$t->set_block("origPage","DetailsBL","DetailsBLout");

$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS UnixDate FROM shippingaddresshistory WHERE OrderID=" . $orderID . " ORDER BY ID ASC");


while($row=$dbCmd->GetRow()){

	$t->set_var(array("DATE"=>date("D, M j, Y g:i a", $row["UnixDate"])));
	
	if($row["UserID"] == $CustomerID)
		$t->set_var("NAME", "Customer");
	else
		$t->set_var("NAME", UserControl::GetNameByUserID($dbCmd, $row["UserID"]));


	$shippingAddressHTML = "";
	if(empty($row["ShippingCompany"]))
		$shippingAddressHTML .= WebUtil::htmlOutput($row["ShippingName"]) . "<br>";
	else 
		$shippingAddressHTML .= WebUtil::htmlOutput($row["ShippingCompany"]) . "<br>Attn:" . WebUtil::htmlOutput($row["ShippingName"]) . "<br>";

	if(empty($row["ShippingAddressTwo"]))
		$shippingAddressHTML .= WebUtil::htmlOutput($row["ShippingAddress"]) . "<br>";
	else
		$shippingAddressHTML .= WebUtil::htmlOutput($row["ShippingAddress"]) . "<br>" . WebUtil::htmlOutput($row["ShippingAddressTwo"]) . "<br>";


	$shippingAddressHTML .= WebUtil::htmlOutput($row["ShippingCity"]) . ", " . WebUtil::htmlOutput($row["ShippingState"]) . "<br>" . WebUtil::htmlOutput($row["ShippingZip"]);


	if($row["ShippingResidentialFlag"] == "Y")
		$shippingAddressHTML .= "<br>Address Type: Residential";
	else if($row["ShippingResidentialFlag"] == "N")
		$shippingAddressHTML .= "<br>Address Type: Commercial";
	else
		$shippingAddressHTML .= "<br>Address Type: Unknown";


	$t->set_var("ADDRESS", $shippingAddressHTML);
	$t->allowVariableToContainBrackets("ADDRESS");



	$t->parse("DetailsBLout","DetailsBL",true);

}


if($dbCmd->GetNumRows() == 0)
	$t->set_var("DetailsBLout", "<tr><td class='body'>No Shipping Address History Yet.</td></tr>");

$t->set_var("ORDERNO", Order::GetHashedOrderNo($orderID));

$t->pparse("OUT","origPage");


?>