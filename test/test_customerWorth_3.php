<?php

require_once("library/Boot_Session.php");


set_time_limit(5000);




$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$domainIDsArr = array(Domain::getDomainID("PrintsMadeEasy.com"));


/*
$customerWorthObj = new CustomerWorth($domainIDsArr);
$customerWorthObj->setBalanceAdjustmentsInclusionFlag(true);
$customerWorthObj->setShippingHandlingProfitInclusionFlag(true);
*/


// Setup the Target Acquisition Cost for each tracking code.

$bannerCodesToCheckArr = array(
"g-all-bc-3-appointment"=>"42.52",
"g-all-bc-3-calendar"=>"52.59",
"g-all-bc-3-cheap"=>"33.97",
"g-all-bc-3-color"=>"79.77",
"g-all-bc-3-create"=>"35.17",
"g-all-bc-3-custom"=>"41.78",
"g-all-bc-3-design"=>"42.86",
"g-all-bc-3-free"=>"11.87",
"g-all-bc-3-general"=>"37.75",
"g-all-bc-3-glossy"=>"50.58",
"g-all-bc-3-industry-animal"=>"39.83",
"g-all-bc-3-industry-beauty"=>"43.57",
"g-all-bc-3-industry-cleaning"=>"38.76",
"g-all-bc-3-industry-fashion"=>"40.80",
"g-all-bc-3-industry-gardening"=>"49.29",
"g-all-bc-3-industry-hairdresser"=>"45.28",
"g-all-bc-3-industry-realtor"=>"54.70",
"g-all-bc-3-make"=>"30.75",
"g-all-bc-3-mispellings"=>"39.23",
"g-all-bc-3-online"=>"40.82",
"g-all-bc-3-order"=>"36.89",
"g-all-bc-3-personal"=>"27.27",
"g-all-bc-3-printing"=>"37.06",
"g-all-bc-3-quality"=>"42.60",
"g-all-bc-3-unique"=>"44.67",
"g-all-gc-free-std"=>"0",
"g-all-gc-std"=>"0",
"g-all-lh-general"=>"43.81",
"g-all-pc-cheap"=>"42.84",
"g-all-pc-create"=>"46.90",
"g-all-pc-custom"=>"39.66",
"g-all-pc-custom"=>"39.66",
"g-all-pc-design"=>"57.54",
"g-all-pc-design"=>"57.54",
"g-all-pc-general"=>"58.77",
"g-all-pc-make"=>"41.35",
"g-all-pc-personal"=>"29.90",
"g-all-pc-photo"=>"29.44",
"g-all-pc-printing"=>"43.66",
"g-all-pc-realestate"=>"114.38",
"g-all-pme-general"=>"39.73",
"gcn-all-bc-3-create"=>"36.24",
"gcn-all-bc-3-design"=>"36.52",
"gcn-all-bc-3-general"=>"36.24",
"gcn-all-bc-3-selfdesigned"=>"36.24",
"gcn-all-bc-3-templates"=>"36.24",
"gcn-all-pc-custom"=>"40.00",
"gcn-all-pc-general"=>"40.00",
"gcn-all-pc-personal"=>"40.00",
"gcn-all-pc-photo"=>"40.00",
"gcn-all-pc-realestate"=>"40.00"
);


$startDate = mktime(0, 0, 0, 2, 23, 2009);
$endDate = mktime(0, 0, 0, 2, 28, 2009);


					
// Output column headers.
print "Banner Code\tNumber of Clicks\tPurchases\tConversion Rate\tTarget Acq.\tTarget CPC\t";




// Make a new Row in the Spreadsheet for each Tracking Code.					
foreach($bannerCodesToCheckArr as $thisTrackingCode => $targetAcq){

	print $thisTrackingCode . "\t";

	// Make sure that Underscores are escaped (because they are WildCard characters in MySQL.
	$thisTrackingCode = DbCmd::EscapeSQL($thisTrackingCode);
	$thisTrackingCode = preg_replace("/_/", "\\_", $thisTrackingCode);
	
	$dbCmd->Query("SELECT COUNT(*) from bannerlog USE INDEX (bannerlog_Date) WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDsArr) ."
			 AND Name LIKE \"" . $thisTrackingCode  . "\" 
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($startDate) . "' AND '" . DbCmd::FormatDBDateTime($endDate) . "') ");
	$bannerClicks = $dbCmd->GetValue();
	print $bannerClicks . "\t";
		
	$dbCmd->Query("SELECT COUNT(*) FROM orders  WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDsArr) ."
			 AND Referral LIKE \"" . $thisTrackingCode  . "\" 
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($startDate) . "' AND '" . DbCmd::FormatDBDateTime($endDate) . "') ");
	$numberOfOrders = $dbCmd->GetValue();
	print $numberOfOrders . "\t";
	
	
	if(empty($bannerClicks))
		$conversionRate = "0";
	else
		$conversionRate = round(($numberOfOrders / $bannerClicks), 4);
		
	print $conversionRate . "\t";
		
		
	print '$' . $targetAcq . "\t";
	print round($conversionRate * $targetAcq, 2);
	
		
	print "\n";
}
	
print "\n\n";
flush();








