<?php

require_once("library/Boot_Session.php");


$retURL = WebUtil::GetInput("retURL", FILTER_SANITIZE_URL);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$topDomainID = WebUtil::GetInput("topDomainID", FILTER_SANITIZE_INT);

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$domainObj = Domain::singleton();

$selectedDomainIDs = $domainObj->getSelectedDomainIDs();


if(sizeof($selectedDomainIDs) == 1){
	// If the user Saved a Single Domain ID while on this page (instead of just overriding a Top-Domain ID)... 
	// ... then redirect the user immediately and we shouldn't get a "SingleDomainException" again.
	header("Location: " . WebUtil::FilterURL($retURL));
	exit;	
}


if(!empty($action)){
	
	if($action == "setTopDomain"){
		// This should be called from an Ajax Call. The reason is that we have to set a cookie in the users browser
		// That can't be done with Header Redirects so reliable... because Apache can perform the redirect without sending header information to the browser.
		Domain::setTopDomainID($topDomainID);
		print "OK";
		exit;
	}
	else{
		throw new Exception("No Actions Passed.");
	}
}



$t = new Templatex(".");

$t->set_file("origPage", "ad_selectTopDomain-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("RETURN_URL", WebUtil::FilterURL($retURL));


// This is the Row that contains our Columns. There is an Inner Block of HTML for each column.
$t->set_block("origPage","DomainRowsBL","DomainRowsBLout");



$columnNumber = 1;
$maxColumns = 4;

// Extract Inner HTML blocks out of the "Row Block" we just extracted.
for($i=1; $i<= $maxColumns; $i++)
	$t->set_block ( "DomainRowsBL", "ColBL_$i", "ColBL_out_$i" );



// Loop through all of the domains that the user has selected.
// We don't want to overload the user with all domains that they have permissions to see.
foreach($selectedDomainIDs as $thisDomainID){
	

	
	$domainLogoObj = new DomainLogos($thisDomainID);

	$t->set_var("DOMAIN_LOGO_COL_$columnNumber", $domainLogoObj->navBarIcon);
	$t->set_var("DOMAIN_ID_COL_$columnNumber", $thisDomainID);
	$t->set_var("DOMAIN_KEY_COL_$columnNumber", Domain::getDomainKeyFromID($thisDomainID));
	
	
	// False because it is a nested block.
	$t->parse("ColBL_out_$columnNumber", "ColBL_$columnNumber", false ); 

	
	$columnNumber++;

	// Once we hit the Last column it is time to parse the Row and reset our column counter.
	if($columnNumber == ($maxColumns + 1)){
		$columnNumber = 1;
		$t->parse("DomainRowsBLout","DomainRowsBL",true);
	}
}

// Once we are done looping through all fo the domains that have been selected, we need to see how many columns were not used.
// Erase those columns and Parse the Row
// From the loop above, if we hit our last column... it would have already parsed the row and set the column counter back to 1.
if($columnNumber > 1){
	
	// Erase remaining columns.
	for($i=$columnNumber; $i<=$maxColumns; $i++)
		$t->set_var("ColBL_out_$i", "");

	$t->parse("DomainRowsBLout","DomainRowsBL",true);
}


$t->pparse("OUT","origPage");




