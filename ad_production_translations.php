<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

$savechanges = WebUtil::GetInput("savechanges", FILTER_SANITIZE_STRING_ONE_LINE);




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$t = new Templatex(".");

$t->set_file("origPage", "ad_production_translations-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Make sure that Rack settings were already saved for this project
$dbCmd->Query("SELECT COUNT(*) FROM productionsetup WHERE DomainID=" . Domain::oneDomain());
if($dbCmd->GetValue() == 0)
	throw new Exception("This product has not been setup yet for rack control.");





if(!empty($savechanges))
	$t->set_var("MESSAGE", "<font class='error'><br>Your changes have been saved.</font>");

else
	$t->set_var("MESSAGE", "");

$t->allowVariableToContainBrackets("MESSAGE");


// Then it means we need to save to the database
if(!empty($savechanges)){
	
	WebUtil::checkFormSecurityCode();

	// We want to get the data from the text areas and convert line breaks to pipe symbols
	$UpdateArr["RackTranslations"] = _ConvertLineBreaksToPipes(WebUtil::GetInput("racks", FILTER_SANITIZE_STRING));
	$UpdateArr["RowTranslations"] = _ConvertLineBreaksToPipes(WebUtil::GetInput("rows", FILTER_SANITIZE_STRING));
	$UpdateArr["ColumnTranslations"] = _ConvertLineBreaksToPipes(WebUtil::GetInput("columns", FILTER_SANITIZE_STRING));
	
	$dbCmd->UpdateQuery("productionsetup", $UpdateArr, "DomainID=" . Domain::oneDomain());
	
}



// Get all of the data... then convert pipe symbols back into line breaks so we can display in "Textareas"
$dbCmd->Query("SELECT * FROM productionsetup WHERE DomainID=". Domain::oneDomain());
$ProductionSetupRow = $dbCmd->GetRow();

$t->set_var("RACKS", WebUtil::htmlOutput(_ConvertPipesToLineBreaks($ProductionSetupRow["RackTranslations"])));
$t->set_var("ROWS", WebUtil::htmlOutput(_ConvertPipesToLineBreaks($ProductionSetupRow["RowTranslations"])));
$t->set_var("COLUMNS", WebUtil::htmlOutput(_ConvertPipesToLineBreaks($ProductionSetupRow["ColumnTranslations"])));




$t->pparse("OUT","origPage");




function _ConvertLineBreaksToPipes($str){

	$retStr = "";
	$tempArray = split("\n", $str);
	
	foreach($tempArray as $ThisRow){
		if(!preg_match("/^\s*$/", $ThisRow))
			$retStr .= trim($ThisRow) . "|";
		
	}
	
	// Get rid of the last pipe symbol
	if(!empty($retStr))
		$retStr = substr($retStr, 0, -1);
	
	
	return $retStr;
}

function _ConvertPipesToLineBreaks($str){

	$retStr = "";
	$tempArray = split("\|", $str);
	
	foreach($tempArray as $ThisRow){
		if(!preg_match("/^\s*$/", $ThisRow))
			$retStr .= trim($ThisRow) . "\n";
		
	}
	
	// Get rid of the last line break
	if(!empty($retStr))
		$retStr = substr($retStr, 0, -1);
	
	
	return $retStr;
}

?>