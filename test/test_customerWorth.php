<?php

require_once("library/Boot_Session.php");


set_time_limit(5000);




$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$domainIDsArr = array(Domain::getDomainID("PrintsMadeEasy.com"));

$customerWorthObj = new CustomerWorth($domainIDsArr);
$customerWorthObj->setBalanceAdjustmentsInclusionFlag(true);
$customerWorthObj->setShippingHandlingProfitInclusionFlag(true);



$bannerCodesToCheckArr = array(
"g-*bc-3-appointment-*",
"g-*bc-3-calendar-*",
"g-*bc-3-cheap-*",
"g-*bc-3-color-*",
"g-*bc-3-create-*",
"g-*bc-3-custom-*",
"g-*bc-3-design-*",
"g-*bc-3-free-*",
"g-*bc-3-general-*",
"g-*bc-3-glossy-*",
"g-*bc-3-industry-animal-*",
"g-*bc-3-industry-beauty-*",
"g-*bc-3-industry-cleaning-*",
"g-*bc-3-industry-fashion-*",
"g-*bc-3-industry-gardening-*",
"g-*bc-3-industry-hairdresser-*",
"g-*bc-3-industry-realtor-*",
"g-*bc-3-make-*",
"g-*bc-3-mispellings-*",
"g-*bc-3-online-*",
"g-*bc-3-order-*",
"g-*bc-3-personal-*",
"g-*bc-3-printing-*",
"g-*bc-3-quality-*",
"g-*bc-3-unique-*",
"g-*gc-free-std-*",
"g-*gc-std-*",
"g-*lh-general-*",
"g-*pc-cheap-*",
"g-*pc-create-*",
"g-*pc-custom-*",
"g-*pc-custom-*",
"g-*pc-design-*",
"g-*pc-design-*",
"g-*pc-general-*",
"g-*pc-make-*",
"g-*pc-personal-*",
"g-*pc-photo-*",
"g-*pc-printing-*",
"g-*pc-realestate-*",
"g-*pme-*",
"gcn-*bc-3-general_optimized",
"gcn-*bc-3-general_optimized",
"gcn-*bc-3-general_optimized",
"gcn-*bc-3-selfdesigned_optimized",
"gcn-*bc-3-general_optimized",
"gcn-*pc-custom-*",
"gcn-*pc-general-*",
"gcn-*pc-personal-*",
"gcn-*pc-photo-*",
"gcn-*pc-realestate-*"
);

$numberOfDaysAheadToSearch = array(1, 7, 30, 90, 130);

$acqDatesHashArr = array(
					array("start"=>mktime(0, 0, 0, 1, 1, 2008), "end"=>mktime(0, 0, 0, 4, 1, 2008)), 
					array("start"=>mktime(0, 0, 0, 4, 1, 2008), "end"=>mktime(0, 0, 0, 7, 1, 2008)), 
					array("start"=>mktime(0, 0, 0, 7, 1, 2008), "end"=>mktime(0, 0, 0, 10, 1, 2008)),
					array("start"=>mktime(0, 0, 0, 10, 1, 2008), "end"=>mktime(0, 0, 0, 1, 1, 2009)),
					array("start"=>mktime(0, 0, 0, 1, 1, 2008), "end"=>mktime(0, 0, 0, 7, 1, 2008)),
					array("start"=>mktime(0, 0, 0, 3, 1, 2008), "end"=>mktime(0, 0, 0, 9, 1, 2008))
					);

					
// Output column headers.
print "Acquisition Period\tNew Users In Period\tBanner Clicks\tNew Customer Conversion\t";

foreach($numberOfDaysAheadToSearch as $thisDaysAhead){
	print "Avg. Wth. $thisDaysAhead day" . LanguageBase::GetPluralSuffix($thisDaysAhead, "", "s") . "\t";
}

print "\n\n";
flush();



// Make a new Row in the Spreadsheet for each Tracking Code.					
foreach($bannerCodesToCheckArr as $thisTrackingCode){

	print $thisTrackingCode . "\n";
	
	$customerWorthObj->setTrackingCodeSearch($thisTrackingCode);
	
	foreach($acqDatesHashArr as $thisAquisitionDateHash){
		
		$numberOfSecondsInMonth = 60 * 60 * 24 * 31.6;
		$numberOfDaysSpanned = round(($thisAquisitionDateHash["end"] - $thisAquisitionDateHash["start"]) / $numberOfSecondsInMonth);

		print  date("M jS, y", $thisAquisitionDateHash["start"]) . " - " . date("M jS, y", $thisAquisitionDateHash["end"]) . " ($numberOfDaysSpanned months)\t";
		
		$customerWorthObj->setNewCustomerAcquisitionPeriod($thisAquisitionDateHash["start"], $thisAquisitionDateHash["end"]);
		
		// Just give a random number so that we can get User Counts
		$customerWorthObj->setEndPeriodDaysFromAcqDate(1);
		//$customerWorthObj->setEndPeriodDate(mktime(0,0,0,7,1,2008));
		
		$customerCountInSpan = $customerWorthObj->getUserCountInAcqSpan();
		print $customerCountInSpan . "\t";
		
		// For Wildcards
		$trackCodeSQL = DbCmd::EscapeLikeQuery($thisTrackingCode);
		$trackCodeSQL = preg_replace("/\\*/", "%", $trackCodeSQL);
		
		$dbCmd->Query("SELECT COUNT(*) from bannerlog USE INDEX (bannerlog_Date) WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDsArr) ."
				 AND Name LIKE \"" . $trackCodeSQL  . "\" 
				AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($thisAquisitionDateHash["start"]) . "' AND '" . DbCmd::FormatDBDateTime($thisAquisitionDateHash["end"]) . "') ");
		$bannerClicks = $dbCmd->GetValue();
		print $bannerClicks . "\t";
		
		if(empty($bannerClicks))
			$conversionRate = "0";
		else
			$conversionRate = round(($customerCountInSpan / $bannerClicks), 4);
			
		print $conversionRate . "\t";
		
		
		outputCustomerWorthByDates($customerWorthObj, $numberOfDaysAheadToSearch);	
		
		print "\n";
	}
	
	print "\n\n";
	flush();
}


function outputCustomerWorthByDates(&$customerWorthObj, $daysAheadValuesArr){
	
	foreach($daysAheadValuesArr as $thisDayValue){
		$customerWorthObj->setEndPeriodDaysFromAcqDate($thisDayValue);
		print '$' . $customerWorthObj->getAverageProfitByEndPeriod() . "\t";
	}
}





