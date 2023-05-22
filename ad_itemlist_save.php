<?

require_once("library/Boot_Session.php");


// It could take a while to communicate with the bank... just give the script at least 5 minutes to run.
set_time_limit(300);



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_INT);
$projectids = WebUtil::GetInput("projectids", FILTER_SANITIZE_STRING_ONE_LINE);
$thisDiscountPercent = WebUtil::GetInput("thisDiscountPercent", FILTER_SANITIZE_FLOAT);



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!Order::CheckIfUserHasPermissionToSeeOrder($orderno))
	throw new Exception("Can not open the item list because the order number does no exist.");

WebUtil::checkFormSecurityCode();
	
	
$PaymentsObj = new Payments(Order::getDomainIDFromOrder($orderno));
	
	
$dbCmd->Query("SELECT ShippingQuote, ShippingState FROM orders WHERE ID=$orderno");
$row = $dbCmd->GetRow();
$ShippingQuote = $row["ShippingQuote"];
$ShippingState = $row["ShippingState"];

$ProjectListArr = split("\|", $projectids);

$NewGrandTotal_NoShipping = 0;
$TaxTotal = 0;

// Lets get all of the new discounts out of the URL and apply them to the Customer Subtotals --#
foreach($ProjectListArr as $projectID){

	//Make sure there are now blank elements in the Array
	if(preg_match("/^\d+$/", $projectID)){

		$orderNumber = Order::GetOrderIDfromProjectID($dbCmd, $projectID);
		
		if($orderNumber != $orderno)
			throw new Exception("Error updating a Project because there is an order ID conflict.");
			
		if(Order::CheckIfOrderComplete($dbCmd, $orderNumber))
			throw new Exception("Error saving item list.  This order is already complete");
		
		//Get the project percentage and vendor subtotals varialbe that comes in through the URL
		eval("\$thisDiscountPercent = \$_POST['projdiscpercent_" . $projectID . "'];");
		
		$thisDiscountPercent = $thisDiscountPercent/100;

		// Get the subtotal of this project
		$dbCmd->Query("SELECT CustomerSubtotal FROM projectsordered WHERE ID=$projectID");
		$ProjectSubtotal = $dbCmd->GetValue();

		$CustomerTax = number_format(round((Constants::GetSalesTaxConstant($ShippingState) * ($ProjectSubtotal  -  round($ProjectSubtotal * $thisDiscountPercent, 2))),2), 2);
		$TaxTotal += $CustomerTax;

		$NewGrandTotal_NoShipping += $ProjectSubtotal + $CustomerTax -  round($ProjectSubtotal * $thisDiscountPercent, 2);
	}
}

$NewShippingTax = number_format(round((Constants::GetSalesTaxConstant($ShippingState) * $ShippingQuote), 2), 2);

$TaxTotal += $NewShippingTax;

$NewGrandTotal = $NewGrandTotal_NoShipping + $ShippingQuote + $NewShippingTax;

// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $orderno);

// now we may have to reauthorize a larger charge on the person's credit card
if($PaymentsObj->AuthorizeNewAmountForOrder($orderno, ($NewGrandTotal + $softBalanceAdjustments), $TaxTotal, $ShippingQuote)){


	// Well we are authorized to update the new totals.
	// So set the new vendor totals and customer discounts
	foreach($ProjectListArr as $projectID){

		//Make sure there are now blank elements in the Array
		if(preg_match("/^\d+$/", $projectID)){
			
			$projectHistory = "";
		
			//Get the project percentage and vendor subtotals varialbe that comes in through the URL
			$thisDiscountPercent = WebUtil::GetInput("projdiscpercent_" . $projectID, FILTER_SANITIZE_FLOAT);
			$thisVendorSubtotal = WebUtil::GetInput("vendorsubtotal_" . $projectID, FILTER_SANITIZE_FLOAT);

			$thisDiscountPercent = $thisDiscountPercent/100;
			
			$dbCmd->Query("SELECT CustomerDiscount FROM projectsordered WHERE ID=$projectID");
			$oldDiscountPercent = $dbCmd->GetValue();
			
			if(strval($oldDiscountPercent) != strval($thisDiscountPercent)){
				$projectHistoryDesc = "Project Discount changed from " . round($oldDiscountPercent * 100) . "% to " . round($thisDiscountPercent * 100) . "%";
				ProjectHistory::RecordProjectHistory($dbCmd, $projectID, $projectHistoryDesc, $UserID);
			}

			
			$updateArr = array();
			$updateArr["CustomerDiscount"] = $thisDiscountPercent;
			
			// There could be up to 6 vendors associated with every project.
			for($i=1; $i<=6; $i++){
				if(isset($_POST['vendorsubtotal' . $i . '_' . $projectID]))
					$updateArr[("VendorSubtotal" . $i)] = $_POST['vendorsubtotal' . $i . '_' . $projectID];
			}

			$dbCmd->UpdateQuery("projectsordered", $updateArr, "ID=$projectID");
		}
	}

	Order::UpdateSalesTaxForOrder($dbCmd, $orderno); 
}
else{



	// Well the charge did not go through.. so show an error to the browser --#
	?>
	
	<html>
	<body bgcolor="#3366CC" leftmargin="0" topmargin="0" marginwidth="20" marginheight="20" >
	<br><br><br><br>
	<font class="Body"><font color="#FFFFFF">

		The new shipping charges were not authorized on the customer's credit card.  The reason is...<br><br>
		<? echo $PaymentsObj->GetErrorReason();  ?>
		<br><br><a href='javascript:self.close();'><font color="#FFccFF"><b>Close Window</b></font></a>

	</font></font>

	<br><br><br><br>
	</body>
	</html>
	
	<?
	
	exit;

}



//Make the parent window reload and this window close

?>

<html>
<script>
window.opener.location = window.opener.location;
self.close();
</script>
</html>