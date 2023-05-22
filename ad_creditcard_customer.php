<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();



$phone = WebUtil::GetInput("phone", FILTER_SANITIZE_STRING_ONE_LINE);
$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
$orderid = WebUtil::GetInput("orderid", FILTER_SANITIZE_INT);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("CUSTOMER_BILLING_INFO"))
	throw new Exception("Permission Denied");
	
	

if(!Order::CheckIfUserHasPermissionToSeeOrder($orderid))
	throw new Exception("Permission Denied");


$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateOrdered) as DateOrdered 
		FROM  orders WHERE ID=" . intval($orderid));
$row = $dbCmd->GetRow();


if(Order::CheckForEmptyOrder($dbCmd, $orderid)){
	if(round((time() - $row["DateOrdered"]) / (60*60*24)) > 2){
		$ErrorMsg = "Severe issue with order in credit card window for Order ID ($orderid) : -- " . WebUtil::getRemoteAddressIp() . " -- " . date("l dS of F Y g:i:s A") . " -- UID: $UserID -- " . UserControl::GetNameByUserID($dbCmd, $UserID);
		WebUtil::WebmasterError($ErrorMsg);
	}
}



if($row["BillingType"] == "C"){

	?>

	<html>
	<head>
	<title>Billing Info</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	</head>

	<body bgcolor="#336699">
	<font color="#FFFFFF">This customer is billed by Corporate Invoicing.
	</font>
	</body>
	</html>


	<?
}
else if($row["BillingType"] == "N"){

	?>

	<html>
	<head>
	<title>Credit Card Info</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	</head>

	<body bgcolor="#336699">
	<font color="#FFFFFF">
	<?

	print $row["CardType"] . "<br>";
	print $row["CardNumber"] . "<br>";
	print $row["MonthExpiration"] . $row["YearExpiration"] . "<br>";

	print "<br>----------- - -  -  -&nbsp;&nbsp;-&nbsp;&nbsp;-<br><br>";

	print WebUtil::htmlOutput($row["BillingCompany"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingName"]) . "<br>";

	print WebUtil::htmlOutput($row["BillingAddress"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingCity"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingState"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingZip"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingCountry"]) . "<br><br>";

	print WebUtil::htmlOutput($email) . "<br>";
	print WebUtil::htmlOutput($phone) . "<br>";

	?>
	</font>
	</body>
	</html>


	<?
}
else if($row["BillingType"] == "P"){

	?>

	<html>
	<head>
	<title>Paypal Info</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	</head>

	<body bgcolor="#336699">
	<font color="#FFFFFF">
	<?

	print WebUtil::htmlOutput($email) . "<br>";
	
	print WebUtil::htmlOutput($row["BillingCompany"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingName"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingAddress"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingCity"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingState"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingZip"]) . "<br>";
	print WebUtil::htmlOutput($row["BillingCountry"]) . "<br><br>";

	print WebUtil::htmlOutput($phone) . "<br>";

	?>
	</font>
	</body>
	</html>


	<?
}
else{
	throw new Exception("Error with Billing Type.");
}


?>