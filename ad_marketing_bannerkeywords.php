<?

require_once("library/Boot_Session.php");



$domainObj = Domain::singleton();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	throw new Exception("Permission Denied");





// Define the search patterns to extract keywords from various search engines.
// Every search engine will more than likely have a different name/value pair for the search phrase
$searchPatternsArr = array();
$searchPatternsArr[] = "/(\?|&)q=((\w|\d|%|\+|\s)+)(&|$)/i";
$searchPatternsArr[] = "/(\?|&)k=((\w|\d|%|\+|\s)+)(&|$)/i";
$searchPatternsArr[] = "/(\?|&)p=((\w|\d|%|\+|\s)+)(&|$)/i";


$t = new Templatex(".");


$t->set_file("origPage", "ad_marketing_bannerkeywords-template.html");


$start_timestamp = WebUtil::GetInput("starttime", FILTER_SANITIZE_INT);
$end_timestamp = WebUtil::GetInput("endtime", FILTER_SANITIZE_INT);


$BannerName = WebUtil::GetInput("bannername", FILTER_SANITIZE_STRING_ONE_LINE);
$BannerName = strip_tags($BannerName);
$t->set_var("BANNER_NAME", WebUtil::htmlOutput($BannerName));

$t->set_var("START_DATE", date("F j, Y", $start_timestamp));
$t->set_var("END_DATE", date("F j, Y", $end_timestamp));


$EmptyList = true;



// Make sure to show them a warning if the start date of the report begins before 12-26-2006
if(mktime(23, 59, 59, 12, 25, 2006) < $start_timestamp)
	$t->discard_block("origPage", "StartDateWarning");



$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);


$bannerNameSQL = DbCmd::EscapeLikeQuery($BannerName);
$bannerNameSQL = preg_replace("/\*/", "%", $bannerNameSQL);

$query = "SELECT Referer FROM bannerlog WHERE Name LIKE \"" . $bannerNameSQL . "\"
	AND Date BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp AND RefererBlank=0 
	AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());


$dbCmd->Query($query);

$numberClicks = $dbCmd->GetNumRows();


// Create arrays to hold the number of clicks/orders a particular key phrase or domain receives.
$searchPhraseArr = array();
$domainsArr = array();



	
while($row = $dbCmd->GetRow()){

	// Not all browsers give us a Referer in the header.
	if(!isset($row["Referer"]) || empty($row["Referer"]))
		continue;

	$refererURL = $row["Referer"];
	
	$bannerKeywords = WebUtil::getKeywordsFromSearchEngineURL($refererURL);
	
	if(!empty($bannerKeywords)){
		if(!isset($searchPhraseArr[$bannerKeywords]))
			$searchPhraseArr[$bannerKeywords] = 1;
		else
			$searchPhraseArr[$bannerKeywords]++;
	}
	
	$domainName = WebUtil::getDomainFromURL($refererURL);

	if(!empty($domainName)){	
		if(!isset($domainsArr[$domainName]))
			$domainsArr[$domainName] = 1;
		else
			$domainsArr[$domainName]++;
	}
}


$totalKewordClicks = 0;
$totalKewordPurchases = 0;


// Show all of the Search phrases in the HTML block
$t->set_block("origPage","KeywordsBL","KeywordsBLout");
foreach($searchPhraseArr as $thisSearchPhrase => $phraseCount){

	// Replace spaces with HTML space character... in case the user added multiple spaces we want to see that.
	$t->set_var("PHRASE", preg_replace("/\s/", "&nbsp;", WebUtil::htmlOutput($thisSearchPhrase)));
	$t->set_var("PCOUNT", $phraseCount);
	

	// Now look and see how many search phrases were found within the orders table.
	// When the tracking code is recorded in the orders table... 
	// ... we are also recording the "http Referer" which should be stored in the sessions/cookie (stored in parallel with tracking code)
	

	// We have to URL encode our search phrase in order to find a match against the Referer in the orders table.
	// It is possible that a space can be recorded as %20 or just a + sign, or a regular space...  So we have to search for all 3 possibilites possibilities.
	// By default PHP URL encodes spaces as plus signs
	$searchPhraseEncodedPluses = urlencode($thisSearchPhrase);
	$searchPhraseEncodedPercents = preg_replace("/\+/", "%20", $searchPhraseEncodedPluses);
	$searchPhraseEncodedSpaces = preg_replace("/\+/", " ", $searchPhraseEncodedPluses);
	
	
	$query = "SELECT COUNT(*) FROM orders WHERE Referral LIKE '" . $bannerNameSQL . "' AND 
		DateOrdered BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp 
		AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND ";
	
	
	// The search phrase may bump into a another name value pair which would mean it hits an & ampersand.
	// or it may be the last name/value pair in the URL in which case it terminates at the end of the string.
	$query .= " 
		(
		BannerReferer like '%=" . DbCmd::EscapeSQL($searchPhraseEncodedPercents) . "&%' OR 
		BannerReferer like '%=" . DbCmd::EscapeSQL($searchPhraseEncodedPluses) . "&%' OR 
		BannerReferer like '%=" . DbCmd::EscapeSQL($searchPhraseEncodedSpaces) . "&%' OR

		BannerReferer like '%=" . DbCmd::EscapeSQL($searchPhraseEncodedPercents) . "' OR 
		BannerReferer like '%=" . DbCmd::EscapeSQL($searchPhraseEncodedPluses) . "' OR 
		BannerReferer like '%=" . DbCmd::EscapeSQL($searchPhraseEncodedSpaces) . "'
		)
		";
	

		
	$dbCmd->Query($query);
	$purchaseCount = $dbCmd->GetValue();
	
	$t->set_var("PPURCHASES", $purchaseCount);
	$t->set_var("PCONVERSION", round(100 * ($purchaseCount / $phraseCount), 1) . '%');
	
	
	$totalKewordClicks += $phraseCount;
	$totalKewordPurchases += $purchaseCount;
	
	$t->parse("KeywordsBLout","KeywordsBL",true);
}


if($totalKewordClicks > 0){

	$t->set_var("PHRASE", "<b>Total</b>");
	$t->set_var("PCOUNT", "<b>" . $totalKewordClicks . "</b>");
	$t->set_var("PPURCHASES", "<b>" . $totalKewordPurchases . "</b>");
	$t->set_var("PCONVERSION", "<b>" . round(100 * ($totalKewordPurchases / $totalKewordClicks), 1) . '%' . "</b>");
	
	$t->allowVariableToContainBrackets("PHRASE");
	$t->allowVariableToContainBrackets("PCOUNT");
	$t->allowVariableToContainBrackets("PPURCHASES");
	$t->allowVariableToContainBrackets("PCONVERSION");
	
	$t->parse("KeywordsBLout","KeywordsBL", true);
}


if(sizeof($searchPhraseArr) == 0){
	$t->set_block("origPage","NoKeywordResultsBL","NoKeywordResultsBLout");
	$t->set_var("NoKeywordResultsBLout", "<br><br>No search phrases were captured for this tracking code within the given time period.");
}




$totalDomainClicks = 0;
$totalDomainPurchases = 0;


// Show all of the Search phrases in the HTML block
$t->set_block("origPage","DomainsBL","DomainsBLout");
foreach($domainsArr as $thisDomain => $domainCount){

	$t->set_var("DOMAIN", WebUtil::htmlOutput($thisDomain));
	$t->set_var("DCOUNT", $domainCount);
	
	
	$query = "SELECT COUNT(*) FROM orders WHERE Referral LIKE '" . $bannerNameSQL . "' AND 
		DateOrdered BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp 
		AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
		BannerReferer like '%://" . DbCmd::EscapeSQL($thisDomain) . "%'";
		
	$dbCmd->Query($query);
	$purchaseCount = $dbCmd->GetValue();

	$t->set_var("DPURCHASES", $purchaseCount);
	$t->set_var("DCONVERSION", round(100 * ($purchaseCount / $domainCount), 1) . '%');
	
	$totalDomainClicks += $domainCount;
	$totalDomainPurchases += $purchaseCount;


	$t->parse("DomainsBLout","DomainsBL",true);
}

if($totalDomainClicks > 0){

	$t->set_var("DOMAIN", "<b>Total</b>");
	$t->set_var("DCOUNT", "<b>" . $totalDomainClicks . "</b>");
	$t->set_var("DPURCHASES", "<b>" . $totalDomainPurchases . "</b>");
	$t->set_var("DCONVERSION", "<b>" . round(100 * ($totalDomainPurchases / $totalDomainClicks), 1) . '%' . "</b>");
	
	$t->allowVariableToContainBrackets("DOMAIN");
	$t->allowVariableToContainBrackets("DCOUNT");
	$t->allowVariableToContainBrackets("DPURCHASES");
	$t->allowVariableToContainBrackets("DCONVERSION");
	
	$t->parse("DomainsBLout","DomainsBL",true);
}



if(sizeof($domainsArr) == 0){
	$t->set_block("origPage","NoDomainsResultsBL","NoDomainsResultsBLout");
	$t->set_var("NoDomainsResultsBLout", "<br><br>No information was captured within the given time period for refering domains.");
}





$t->set_var("BANNER_CLICKS", $numberClicks);


$t->pparse("OUT","origPage");




?>