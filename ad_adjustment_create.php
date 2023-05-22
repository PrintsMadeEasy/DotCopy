<?
require_once("library/Boot_Session.php");




$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_INT);
$updateparent = WebUtil::GetInput("updateparent", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$vendorid = WebUtil::GetInput("vendorid", FILTER_SANITIZE_INT);
$customer_adjstmnt = WebUtil::GetInput("customer_adjstmnt", FILTER_SANITIZE_FLOAT);
$vendor_adjstmnt = WebUtil::GetInput("vendor_adjstmnt", FILTER_SANITIZE_FLOAT);
$reason = WebUtil::GetInput("reason", FILTER_SANITIZE_STRING_ONE_LINE);




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();


if(!Order::CheckIfUserHasPermissionToSeeOrder($orderno))
	throw new Exception("The order number does not exist.");


	
$domainIDofOrder = Order::getDomainIDFromOrder($orderno);
	

$PaymentsObj = new Payments($domainIDofOrder);


	
$balanceAdjustmentObj = new BalanceAdjustment($orderno);
	

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($action)){

	WebUtil::checkFormSecurityCode();
	
	if($action == "priceadjustment"){
	

		//In case of a customer adjustment the the vendor ID should be 0, because it doesn't apply to them
		if($vendorid == "")
			$vendorid = 0;


		//This means that we are dealing with a customer adjustment
		if($vendorid == 0){
			
			if($customer_adjstmnt == 0)
				WebUtil::PrintAdminError("You can not create a customer adjustment of Zero.");

			// If the customer adjustment is negative then it means we are refunding money to the customer
			// We don't need to check for errors with the authorization for the refund.  There is a very good chance that it will go through with our bank.
			// If it doesn't go through for some reason, then we will deal with the errors elsewhere
			if($customer_adjstmnt < 0){

// ----------------------------------------------------------------------
// An UGLY HACK.  This can be removed by June, 2011.
if(Order::getDomainIDFromOrder($orderno) == 10 && $orderno < 564633){
	?>
	<html><body bgcolor="#3366CC" leftmargin="30" topmargin="0" marginwidth="20" marginheight="20" ><LINK REL=stylesheet HREF="./library/stylesheet.css" TYPE="text/css"><br><br><font class="Body"><font color="#FFFFFF">
	The funds for this order were captured from a merchant account that is now closed.  <a href="mailto:Brian@DotGraphics.net&subject=Refund Request&body=Order ID: %0AReason for Refund: %0APayable To:%0AAmount: %0AMailing Address:%0A%0A%0A%0A"><font color='#FFF3EE'>Click here</font></a> to request that physical check be mailed to the customer.  Don't forget to include the following... <br>a) Order ID<br>b) Reason for refund<br>c) Name/Company for &quot;payable to&quot;<br>d) Amount<br>e) Mailing address 
	<br><br>Please remember to leave a &quot;Customer Memo&quot; stating that a manual refund was requested.  Allow 10 days for the check to arrive.
	<br><br><a href='javascript:self.close();'><font color="#FFccFF"><b>Close Window</b></font></a></font></font><br><br><br><br></body></html>
	<?
	exit;
}
// ----------------------------------------------------------------------			
				
				// Customer Adjustments are positive within our system.
				$customer_adjstmnt_abs = abs($customer_adjstmnt);

				$balanceAdjustmentObj->refundMoneyToCustomer($customer_adjstmnt_abs, $UserID, $reason);
			}
			else{
				
				if(Order::getOrderBillingType($orderno) == "P"){
					
					// Well the charge did not go through.. so show an error to the browser
					?>
					<html><body bgcolor="#3366CC" leftmargin="30" topmargin="0" marginwidth="20" marginheight="20" ><LINK REL=stylesheet HREF="./library/stylesheet.css" TYPE="text/css"><br><br><font class="Body"><font color="#FFFFFF">
					You can not do positive balance adjustments for orders with Paypal.
					<br><br><a href='javascript:self.close();'><font color="#FFccFF"><b>Close Window</b></font></a></font></font><br><br><br><br></body></html>
					<?

					exit;
				}

				// Run the transaction with the bank.
				// Postive balance adjustments manually added should have an auth/capture done at the same time.
				if(!$balanceAdjustmentObj->chargeCustomerWithCapture($customer_adjstmnt, $UserID, $reason)){

					// It does not look like the transfer went through with the bank
					// Now we need to show an error message to the admin user
					// Well the charge did not go through.. so show an error to the browser
					?>
					<html><body bgcolor="#3366CC" leftmargin="30" topmargin="0" marginwidth="20" marginheight="20" ><LINK REL=stylesheet HREF="./library/stylesheet.css" TYPE="text/css"><br><br><font class="Body"><font color="#FFFFFF">
						The balance adjustment was not authorized on the customer's credit card.  The reason is...<br>
						<? echo $balanceAdjustmentObj->getPaymentGatewayErrorReason();  ?>
						<br><br><a href='javascript:self.close();'><font color="#FFccFF"><b>Close Window</b></font></a></font></font><br><br><br><br></body></html>
					<?

					exit;
				}
			}

		}
		else{
			// Then this is a Vendor Adjsutment.
			
			if($vendor_adjstmnt == 0)
				WebUtil::PrintAdminError("You can not create a Vendor Adjustment of Zero.");
			
			if($vendor_adjstmnt < 0){
				$balanceAdjustmentObj->takeMoneyFromVendor(abs($vendor_adjstmnt), $UserID, $vendorid, $reason);
			}
			else{
				$balanceAdjustmentObj->giveMoneyToVendor($vendor_adjstmnt, $UserID, $vendorid, $reason);
			}
		}


		?><html>
<script>
window.opener.location.href = window.opener.location.href;
self.close();
</script>

</html>
		<?
		exit();

	
	}
	else
		throw new Exception("Undefined Action");

}


$t = new Templatex(".");


$t->set_file("origPage", "ad_adjustment_create-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Find out the current customer balance adjustment --#
$dbCmd->Query("SELECT SUM(balanceadjustments.CustomerAdjustment) FROM balanceadjustments
		WHERE balanceadjustments.OrderID=$orderno");
$customerBalanceAdjustment = $dbCmd->GetValue();



// Find out the grand total that the customer paid --#
$customerGrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $orderno);

$customerOriginalPrice  = $customerGrandTotal + $customerBalanceAdjustment;


$t->set_var("ORIG_CUST_BALANCE", number_format($customerOriginalPrice, 2));

$t->set_var("ORIG_CUST_BALANCE_NOCOMMA", number_format($customerOriginalPrice, 2, '.', ''));


$t->set_var("CUSTOMER_ADJUSTMENT_TOTALS_NOCOMMA", number_format(BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder($dbCmd, $orderno), 2, '.', ''));


if(Order::CheckIfOrderComplete($dbCmd, $orderno))
	$t->set_var("ORDER_IS_COMPLETE_FLAG_JS", "true");
else 
	$t->set_var("ORDER_IS_COMPLETE_FLAG_JS", "false");
	
	

// Get a unique list of Vendors with this order
$t->set_block("origPage","VendorAdjusmentsBL","VendorAdjusmentsBLout");


$vendorIDarr = ProjectOrdered::GetUniqueVendorIDsFromOrder($dbCmd, $orderno);


foreach($vendorIDarr as $VendorID){

	$VendorName = Product::GetVendorNameByUserID($dbCmd, $VendorID);

	$VendorSubtotal = Order::GetTotalFromOrder($dbCmd, $orderno, "vendorsubtotal", $VendorID);
	$VendorSubtotal = number_format($VendorSubtotal, 2);

	$dbCmd->Query("SELECT SUM(VendorAdjustment) FROM balanceadjustments 
			WHERE OrderID=$orderno AND VendorID=$VendorID");
	$VendorAdjustementsTotal = number_format($dbCmd->GetValue(), 2);

	$VendorBalance = number_format($VendorSubtotal + $VendorAdjustementsTotal, 2);

	$t->set_var("VENDOR_BALANCE", $VendorBalance);
	$t->set_var("VENDOR_ID", $VendorID);
	
	$t->set_var("VENDOR_NAME", $VendorName);
	$t->parse("VendorAdjusmentsBLout","VendorAdjusmentsBL",true);
}


// If there are no vendors defined... or maybe the order is empty.
if(empty($vendorIDarr))
	$t->set_var("VendorAdjusmentsBLout", "");


// If this variable comes in the URL then it is a signal to reload the opener page... or the one that launched this popup window.
if(!empty($updateparent))
	$t->set_var("RELOAD_PARENT", "window.opener.location = window.opener.location;");
else
	$t->set_var("RELOAD_PARENT", "");


$t->set_var("ORDERID", $orderno);


$t->pparse("OUT","origPage");


?>
