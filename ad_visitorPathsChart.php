<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


set_time_limit(100);
ini_set("memory_limit", "512M");


// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("VISITOR_PATHS_REPORT"))
	throw new Exception("Permission Denied");

$domainObj = Domain::singleton();


$combinedChartParams = WebUtil::GetInput("combinedChartParams", FILTER_SANITIZE_STRING_ONE_LINE);
$chartType = WebUtil::GetInput("chartType", FILTER_SANITIZE_STRING_ONE_LINE);
$conflateDetailedLabels = WebUtil::GetInput("conflateDetailedLabels", FILTER_SANITIZE_STRING_ONE_LINE);




// If the combined report parameters has been sent... then we want to uncomporess the contents, and redirect the server to the "exploded URL parameters".
// This saves on IE GET URL lengh maximums... and prevents bugs in GraphViz using special characters in a "node url". 
if(!empty($combinedChartParams)){
	header("Location: ./ad_visitorPathsChart.php?chartType=combined&" . gzinflate(base64_decode($combinedChartParams)));
	exit;
}


// Make it bool
$conflateDetailedLabels = ($conflateDetailedLabels == "yes" ? true : false);


$visitorQueryObj = new VisitorPathQuery();

$visitorQueryObj->setQueryParametersFromURL();
	
$visitorReportObj = new VisitorPathReport();

$visitorReportObj->setVisitorQueryObject($visitorQueryObj);



if($chartType == "combined"){
	
	$dotFile = $visitorReportObj->getCumulativeReport();

}
else if($chartType == "single"){
	
	$sessionIDsArr = $visitorQueryObj->getSessionIdLimiters();
	
	$dotFile = $visitorReportObj->getSessionReport(current($sessionIDsArr), $conflateDetailedLabels);
}
else{
	throw new Exception("Error with the Chart Type parameter.");
}


// Put PDF on disk
$dotFileName = "dt_" . substr(md5($dotFile), 2, 18);
$dotFileNamePath = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $dotFileName;


// Convert Dot file name into a PDF
if(Constants::GetDevelopmentServer()){
	
	file_put_contents(($dotFileNamePath . ".dot"), $dotFile);
	
	// On windows we can't change directories and execute subsequent commands in context... so we have to execute a batch file.
	// "dot" from GraphViz doesn't allow us to specify file paths very easily.
	$batchFile = "cd " . DomainPaths::getPdfSystemPathOfDomainInURL() . "\n";
	$batchFile .= "dot -Teps -o".$dotFileName.".eps " . $dotFileName . ".dot\n";
	$batchFile .= "gswin32c -dSAFER -dBATCH -dNOPAUSE -dEPSCrop -sDEVICE=pdfwrite -sOutputFile=".$dotFileName.".pdf ".$dotFileName.".eps\n";
	file_put_contents((DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $dotFileName . ".bat"), $batchFile);
	WebUtil::mysystem(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $dotFileName . ".bat"); 
	
	unlink($dotFileNamePath . ".dot");
	unlink($dotFileNamePath . ".eps");
	unlink($dotFileNamePath . ".bat");
}
else{
	file_put_contents(($dotFileNamePath . ".dot"), $dotFile);
	//exit("dot -Teps -o".$dotFileNamePath.".eps " . $dotFileNamePath . ".dot");
	WebUtil::mysystem("dot -Teps -o".$dotFileNamePath.".eps " . $dotFileNamePath . ".dot"); 
	WebUtil::mysystem("gs -dSAFER -dBATCH -dNOPAUSE -dEPSCrop -sDEVICE=pdfwrite -sOutputFile=".$dotFileNamePath.".pdf ".$dotFileNamePath.".eps"); 
	//unlink($dotFileNamePath . ".dot");
}	


header("Location: " . DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $dotFileName . ".pdf");






	