<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$variableName = WebUtil::GetInput("varname", FILTER_SANITIZE_STRING_ONE_LINE);


$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

if(!$projectObj->isVariableData())
	WebUtil::PrintError("This is not a variable data project.");


$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){

	if($action == "save"){
	
	
		$dataAlterObj = new VariableDataFieldAlterData();
		
		$dataAlterObj->setCriteria("NOTBLANK");

	
		// Get CheckBox fields
		if(WebUtil::GetInput("addLineBreakBefore", FILTER_SANITIZE_STRING_ONE_LINE))
			$dataAlterObj->setDataAddBefore("!br!");
		if(WebUtil::GetInput("removeLineBreakBefore", FILTER_SANITIZE_STRING_ONE_LINE))
			$dataAlterObj->setDataRemoveBefore("!br!");
		if(WebUtil::GetInput("removeLineBreakAfter", FILTER_SANITIZE_STRING_ONE_LINE))
			$dataAlterObj->setDataRemoveAfter("!br!");
			
			
		
		// If they have chosen to add data after (this is manual text).
		// We can not use the "GetInput" function because it trims white spaces...and white spaces matter to us.
		// The second parameter is set to TRUE because we are doing an append (there may have been a line break already added before);
		if(isset($_REQUEST["addTextBefore"]))
			$dataAlterObj->setDataAddBefore($_REQUEST["addTextBefore"], true);
		
	
		// If they hit the checkbox to add a Line-Break after... then it will come after any Manual text that they have chosen to add.
		// Spaces matter... so don't Trim the input.
		if(isset($_REQUEST["addTextAfter"]))
			$dataAlterObj->setDataAddAfter($_REQUEST["addTextAfter"]);
		else
			$dataAlterObj->setDataAddAfter("");
	
		
		// Append Line breaks to the end of any data being added after the field.	
		if(WebUtil::GetInput("addLineBreakAfter", FILTER_SANITIZE_STRING_ONE_LINE))
			$dataAlterObj->setDataAddAfter("!br!", true);


		// Add the Data Alteration Object back into the Artwork Mappings Object.
		$artworkVarsObj->addVariableDataFieldAlteration($variableName, $dataAlterObj);
		
		
		
		// Set Size Restrictions
		$sizeRestrictionType = WebUtil::GetInput("sizeRestrictionType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES); 
		$sizeRestrictionLimit =  intval(WebUtil::GetInput("sizeRestrictionLimit", FILTER_SANITIZE_INT)); 
		$sizeRestrictionAction = WebUtil::GetInput("sizeRestrictionAction", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES); 
		
		$variableDataSizeRestrictionsObj = new VariableDataSizeRestrictions();
		
		if($sizeRestrictionType != "NONE" && $sizeRestrictionAction != "NONE" && $sizeRestrictionLimit > 0){
			$variableDataSizeRestrictionsObj->setSizeRestrictionType($sizeRestrictionType);
			$variableDataSizeRestrictionsObj->setSizeRestrictionAction($sizeRestrictionAction);
			$variableDataSizeRestrictionsObj->setSizeRestrictionLimit($sizeRestrictionLimit);
		}
		
		// Add the Variable Size Restrictions Object into the Artwork Mappings Object.
		// If there were no parameters set in the URL we will be wipping out the setting with a blank VariableDataSizeRestrictions object.
		$artworkVarsObj->addDataFieldSizeRestriction($variableName, $variableDataSizeRestrictionsObj);
		
	
		
		
		
		// Set the XML file back into the database.
		$projectObj->setVariableDataArtworkConfig($artworkVarsObj->getVarMappingInXML());
		
		
		$projectObj->updateDatabase();
		
		
		// In case the it is Saved... this will update the Shoppingcart, etc.
		ProjectOrdered::ProjectChangedSoBreakOrCloneAssociates($dbCmd, $editorview, $projectrecord);

	}
	else{
		throw new Exception("Undefined Action");
	}



	print "<html>\n<script>\n window.opener.location = window.opener.location; \n self.close(); </script></html>";
	exit;

}



$t = new Templatex();

$t->set_file("origPage", "vardata_dataextra-template.html");


$t->set_var("PROJECTRECORD", $projectrecord);
$t->set_var("VIEWTYPE", $editorview);
$t->set_var("VAR_NAME", WebUtil::htmlOutput($variableName));



if($artworkVarsObj->checkIfVariableHasDataAlteration($variableName)){

	
	$variableDataAlterObj = $artworkVarsObj->getDataAlterationObjectForVariable($variableName);
	
	
	// Get all of the data elements that we want to either remove or add.
	$dataToAddBefore = $variableDataAlterObj->getDataAddBefore();
	$dataToAddAfter = $variableDataAlterObj->getDataAddAfter();
	$dataToRemoveBefore = $variableDataAlterObj->getDataRemoveBefore();
	$dataToRemoveAfter = $variableDataAlterObj->getDataRemoveAfter();
	
	
	// We make it easy on the Customers.... Our internal code for a line break is.... !br!
	// If we find that a line starts with that... convert that into a checkbox for the user.
	// Then if there is anything left after that... we can stick it in the "Add To" or add after.
	
	
	// ------------------
	
	if(preg_match("/^!br!/", $dataToAddBefore)){
		$t->set_var("ADD_LINEBREAK_BEFORE_CHECKED", "checked");
		
		$dataToAddBefore = preg_replace("/^!br!/", "", $dataToAddBefore);
		$t->set_var("ADDTEXT_BEFORE", WebUtil::htmlOutput($dataToAddBefore));
		
	}
	else{
		$t->set_var("ADD_LINEBREAK_BEFORE_CHECKED", "");
		$t->set_var("ADDTEXT_BEFORE", WebUtil::htmlOutput($dataToAddBefore));
	}
	
	// ---------------------
	
	if(preg_match("/!br!$/", $dataToAddAfter)){
		$t->set_var("ADD_LINEBREAK_AFTER_CHECKED", "checked");
		
		$dataToAddAfter = preg_replace("/!br!$/", "", $dataToAddAfter);
		$t->set_var("ADDTEXT_AFTER", WebUtil::htmlOutput($dataToAddAfter));
	}
	else{
		$t->set_var("ADD_LINEBREAK_AFTER_CHECKED", "");
		$t->set_var("ADDTEXT_AFTER", WebUtil::htmlOutput($dataToAddAfter));
	}
	
	
	// ---------------------
	
	if(preg_match("/^!br!/", $dataToRemoveBefore)){
		$t->set_var("REMOVE_LINEBREAK_BEFORE_CHECKED", "checked");
	}
	else{
		$t->set_var("REMOVE_LINEBREAK_BEFORE_CHECKED", "");
	}
	
	// ---------------------
	
	if(preg_match("/!br!$/", $dataToRemoveAfter)){
		$t->set_var("REMOVE_LINEBREAK_AFTER_CHECKED", "checked");
	}
	else{
		$t->set_var("REMOVE_LINEBREAK_AFTER_CHECKED", "");
	}
	


}
else{

	// If there are no data alterations for this variable... then all fields are blank by default.
	
	
	
	$t->set_var("ADD_LINEBREAK_AFTER_CHECKED", "");
	$t->set_var("REMOVE_LINEBREAK_BEFORE_CHECKED", "");
	$t->set_var("REMOVE_LINEBREAK_AFTER_CHECKED", "");
	$t->set_var("ADDTEXT_BEFORE", "");
	$t->set_var("ADDTEXT_AFTER", "");	
}





// Setup the Size Restrictions

$sizeRestrictionsTypeArr["NONE"] = "No Size Restrictions";
$sizeRestrictionsTypeArr["GREATER_THAN_PICAS_WIDTH"] = "Greater Than X number of Picas";


$sizeRestrictionsActionArr["NONE"] = "Do Nothing";
$sizeRestrictionsActionArr["SHRINK_FONT_SIZE"] = "Shrink Font Size to stay within the Numerical Limit";


if($artworkVarsObj->checkIfVariableHasSizeRestriction($variableName)){

	$variableSizeRestrictObj = $artworkVarsObj->getSizeRestrictionObjectForVariable($variableName);
	
	$t->set_var("RESTRICT_TYPE_OPTIONS", Widgets::buildSelect($sizeRestrictionsTypeArr, $variableSizeRestrictObj->getRestrictionType()));
	$t->set_var("RESTRICT_ACTION_OPTIONS", Widgets::buildSelect($sizeRestrictionsActionArr, $variableSizeRestrictObj->getRestrictionAction()));
	$t->set_var("RESTRICT_LIMIT", $variableSizeRestrictObj->getRestrictionLimit());


}
else{
	
	$t->set_var("RESTRICT_TYPE_OPTIONS", Widgets::buildSelect($sizeRestrictionsTypeArr, "NONE"));
	$t->set_var("RESTRICT_ACTION_OPTIONS", Widgets::buildSelect($sizeRestrictionsActionArr, "NONE"));
	$t->set_var("RESTRICT_LIMIT", "");
}

$t->allowVariableToContainBrackets("RESTRICT_TYPE_OPTIONS");
$t->allowVariableToContainBrackets("RESTRICT_ACTION_OPTIONS");


VisitorPath::addRecord("Variable Data Advanced Field Configuration");

$t->pparse("OUT","origPage");





?>