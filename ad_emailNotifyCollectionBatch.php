<?

// http://localhost/dot/ad_emailNotifyCollectionBatch.php?view=start

require_once("library/Boot_Session.php");

$domainObj = Domain::singleton();

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_EMAIL_ADDRESS_BATCHES"))
	throw new Exception("Permission Denied");

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$batchId = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);

$emailObj = new EmailNotifyCollection();

if(empty($view))
	$view = "start";

if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "saveEmailBatch"){
		
		// Save allowed Domains
		$allowedDomainIds = $AuthObj->getUserDomainsIDs();		
		foreach ($allowedDomainIds AS $domainId) 
			$emailObj->updateBatchDomainRelation($batchId, $domainId, WebUtil::GetInput("DomainID".$domainId, FILTER_SANITIZE_INT));
					
		header("Location: ./" . WebUtil::FilterURL("ad_emailNotifyCollectionBatch.php?view=editEmailBatch&batchID=$batchId"));
		exit;
	}
	else if($action == "moveBatchUp"){
	
		$emailObj->moveBatchChoicePosition($batchId, true);
		header("Location: ./" . WebUtil::FilterURL("ad_emailNotifyCollectionBatch.php?view=start"));
		exit;
	}	
	else if($action == "moveBatchDown"){
		
		$emailObj->moveBatchChoicePosition($batchId, false);
		header("Location: ./" . WebUtil::FilterURL("ad_emailNotifyCollectionBatch.php?view=start"));
		exit;
	}
	else{
		throw new Exception("Undefined Action");
	}
}


$t = new Templatex(".");

$t->set_file("origPage", "ad_emailNotifyCollectionBatch-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

if($view == "start"){

	$t->discard_block("origPage", "EditBatchBL");
		
	$t->set_block("origPage","BatchListBL","BatchListBLout"); 

	// Extract Inner HTML blocks for the Arrow buttons out of the the Block we just extracted for the row.
	$t->set_block ( "BatchListBL", "upLink", "upLinkout" );
	$t->set_block ( "BatchListBL", "downLink", "downLinkout" );
		
	$t->allowVariableToContainBrackets("START_MONTH_SELECT");
	$t->allowVariableToContainBrackets("START_YEAR_SELECT");
	$t->allowVariableToContainBrackets("END_MONTH_SELECT");
	$t->allowVariableToContainBrackets("END_YEAR_SELECT");
	$t->allowVariableToContainBrackets("DOMAINNAMES");
	
	
	$startday   = WebUtil::GetInput("startday", FILTER_SANITIZE_INT);
	$startmonth = WebUtil::GetInput("startmonth", FILTER_SANITIZE_INT);
	$startyear  = WebUtil::GetInput("startyear", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	$endday     = WebUtil::GetInput("endday",   FILTER_SANITIZE_INT);
	$endmonth   = WebUtil::GetInput("endmonth", FILTER_SANITIZE_INT);
	$endyear    = WebUtil::GetInput("endyear",  FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(empty($endyear) || empty($startyear)) {		

		$currentDate = getdate(time());
			
		$startday    = $currentDate["mday"];
		$startmonth  = $currentDate["mon"];  
		$startyear   = $currentDate["year"]; 
		$endday      = $currentDate["mday"]; 
		$endmonth    = $currentDate["mon"];  
		$endyear    =  $currentDate["year"]; 
	}

	$t->set_var("START_DAY", $startday);	
	$t->set_var("START_MONTH_SELECT",Widgets::BuildMonthSelect( $startmonth, "startmonth", "" ));				
	$t->set_var("START_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice( $startyear,  "startyear", "" ));		
	$t->set_var("END_DAY", $endday);	
	$t->set_var("END_MONTH_SELECT",Widgets::BuildMonthSelect( $endmonth,"endmonth", "" ));	
	$t->set_var("END_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice($endyear,  "endyear", "" ));	
	
	$dateRangeStart = NULL; 
	if($startyear!="ALL") 
		$dateRangeStart = mktime(0,0,0,$startmonth,$startday,$startyear);
		
	$dateRangeEnd = NULL;		
	if($endyear!="ALL") 	
		$dateRangeEnd = mktime(0,0,0,$endmonth,$endday,$endyear);	
	
	if($dateRangeStart > $dateRangeEnd)
		$dateRangeEnd = $dateRangeStart;
	
	$batchIdArr = $emailObj->getBatchIdArr();
	
	if(empty($batchIdArr)) {
		$t->set_var("BatchListBLout","");
		$t->discard_block("origPage", "BatchListHeaderBL");
	}		
		
	$counter = 0;
	foreach($batchIdArr as $batchId) {
		
		$t->set_var("BATCH_ID",    $batchId);
		$t->set_var("BATCH_NAME",  $emailObj->getBatchDecriptionById($batchId));	
		$t->set_var("BATCH_IMPORTDATE", date("M j, Y", $emailObj->getBatchImportDateById($batchId)));	
		
		$t->set_var("DOMAINNAMES", $emailObj->generateBatchDomainText($batchId, $domainObj->getSelectedDomainIDs()));
			
		$emailObj->setStatisticsDateRange($dateRangeStart,$dateRangeEnd);
		$emailObj->setStatisticsAllowedDomainIds($domainObj->getSelectedDomainIDs());
		
		$t->set_var("STATISTIC_SENDERROR", $emailObj->statisticSendErrorsByBatchId($batchId));
		$t->set_var("STATISTIC_TRACKING",  $emailObj->statisticTrackingByBatchId($batchId));
		$t->set_var("STATISTIC_SENTEMAILS",$emailObj->statisticSentOutByBatchId($batchId));
		
		$statClicks = $emailObj->statisticClicksByBatchId($batchId);
		$statOrders = $emailObj->statisticOrdersByBatchId($batchId);
		
		$statConversionRate = "0.00%";
		if($statClicks>0)
			$statConversionRate = number_format($statClicks /$statOrders / 100 , 2)." %";  
					
		$t->set_var("STATISTIC_CLICKS",    $statClicks);
		$t->set_var("STATISTIC_ORDERS",    $statOrders);
		$t->set_var("STATISTIC_CONVRATE",  $statConversionRate);
		
		if(sizeof($batchIdArr) == 1){
			$t->set_var("upLinkout", "");
			$t->set_var("downLinkout", "");
		}
		else if ($counter == 0){
			$t->parse("downLinkout", "downLink", false );
			$t->set_var("upLinkout", "");
		}
		else if(($counter+1) == sizeof($batchIdArr)){
			$t->parse ( "upLinkout", "upLink", false );
			$t->set_var("downLinkout", "");
		}
		else{
			$t->parse ( "upLinkout", "upLink", false );
			$t->parse("downLinkout", "downLink", false );
		}
			
		$t->parse("BatchListBLout","BatchListBL",true); 	
	
		$counter++;
	}
	
	$t->discard_block("origPage", "BatchListBL");
}
else if($view == "editEmailBatch"){
	
    $t->discard_block("origPage", "StartBL");
	
	$t->set_var("BATCH_ID",   $batchId);
	$t->set_var("BATCH_NAME", $emailObj->getBatchDecriptionById($batchId));
	
	$t->set_var("DOMAIN_CHECKBOXES", $emailObj->createBatchDomainCheckBoxes($batchId, $AuthObj->getUserDomainsIDs()));
	$t->allowVariableToContainBrackets("DOMAIN_CHECKBOXES");

} else {
	throw new Exception("Illegal View Type");
}

Widgets::buildTabsForEmailNotify($t, "batches");

$t->pparse("OUT","origPage");

?>