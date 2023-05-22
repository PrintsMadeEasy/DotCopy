<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);


$t = new Templatex(".");

$t->set_file("origPage", "ad_statushistory-template.html");

$t->set_block("origPage","StatusBL","StatusBLout");

$t->set_var("ORDERNO", $projectid);

$dbCmd->Query("SELECT Note, UNIX_TIMESTAMP(Date) AS Date, UserID FROM projecthistory INNER JOIN projectsordered
				ON projecthistory.ProjectID = projectsordered.ID
				WHERE projecthistory.ProjectID=$projectid AND " . DbHelper::getOrClauseFromArray("DomainID", $AuthObj->getUserDomainsIDs()));
				

while($row = $dbCmd->GetRow()){

	$t->set_var("STATUS",  $row["Note"]);
	$t->set_var("DATE", date("D, M j, Y g:i a", $row["Date"]));
	$t->set_var("NAME", UserControl::GetNameByUserID($dbCmd2, $row["UserID"]));
	$t->allowVariableToContainBrackets("STATUS");

	$t->parse("StatusBLout","StatusBL",true);
}


if($dbCmd->GetNumRows() == 0)
	$t->set_var(array("StatusBLout"=>"<tr><td class='body'>No Status History Yet.</td></tr>"));


$t->pparse("OUT","origPage");


?>