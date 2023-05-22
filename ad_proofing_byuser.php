<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$starttime = WebUtil::GetInput("starttime", FILTER_SANITIZE_INT);
$endtime = WebUtil::GetInput("endtime", FILTER_SANITIZE_INT);
$csUserID = WebUtil::GetInput("csUserID", FILTER_SANITIZE_INT);
$CsReportURL = WebUtil::GetInput("CsReportURL", FILTER_SANITIZE_URL);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();

$t = new Templatex(".");
$t->set_file("origPage", "ad_proofing_byuser-template.html");


$domainIDofUserToCheck = UserControl::getDomainIDofUser($csUserID);
if(!in_array($domainIDofUserToCheck, $domainObj->getSelectedDomainIDs()))
	WebUtil::PrintAdminError("UserID is not valid.");
	

$t->set_block("origPage","ProjectsBL","ProjectsBLout");

$startSQLtimstamp = date("YmdHis", $starttime);
$endSQLtimstamp = date("YmdHis", $endtime);


$dbCmd->Query("SELECT DISTINCT ProjectID FROM projecthistory 
		INNER JOIN projectsordered AS PO ON projecthistory.ProjectID = PO.ID
		WHERE Note='Proofed' AND 
		projecthistory.UserID=$csUserID AND 
		projecthistory.Date BETWEEN $startSQLtimstamp AND $endSQLtimstamp  
		AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "
		ORDER BY ProjectID DESC");

$counter=0;
while($projectID = $dbCmd->GetValue()){

	$dbCmd2->Query("SELECT Status FROM projectsordered WHERE ID=$projectID");
	$projectStatus = $dbCmd2->GetValue();

	$t->set_var("PROJECTID", $projectID);
	$t->set_var("STATUS", ProjectStatus::GetProjectStatusDescription($projectStatus, true, "10px"));
	$t->allowVariableToContainBrackets("STATUS");

	$t->parse("ProjectsBLout","ProjectsBL",true);

	$counter++;
}


$t->set_var("PROOF_NUM", $counter);
$t->set_var("PROOFER_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $csUserID)));


$t->set_var("START_DATE", date("M j, Y", $starttime));
$t->set_var("END_DATE", date("M j, Y", $endtime));

$t->set_var("CSREPORTURL", $CsReportURL);


if($counter == 0){
	$t->set_block("origPage","NoResultsBL","NoResultsBLout");
	$t->set_var("NoResultsBLout", "No Proofs found for this user within the period.");
}


$t->pparse("OUT","origPage");



?>