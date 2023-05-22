<?

require_once("library/Boot_Session.php");


$templateID = WebUtil::GetInput("templateID", FILTER_SANITIZE_INT);
$templateArea = WebUtil::GetInput("templateArea", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$dbCmd = new DbCmd();

$t = new Templatex(".");

$t->set_file("origPage", "ad_templates_textFields-template.html");


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("MANAGE_TEMPLATES"))
	WebUtil::PrintAdminError("Not Available");


if($templateArea == "C")
	$templateView = "template_category";
else if($templateArea == "S")
	$templateView = "template_searchengine";
else
	throw new Exception("Illegal template view.");


// Make sure the user has permission to edit tempaltes on this domain.
$templateProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $templateID, $templateView);
$domainIDofTemplate = Product::getDomainIDfromProductID($dbCmd, $templateProductID);

if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofTemplate))
	throw new Exception("User can not edit these templates.");

	
// Get the Artwork for this Saved Project
$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $templateView, $templateID);
$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);


$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	if($Action == "save"){
		
		WebUtil::checkFormSecurityCode();

		// Hold all of the field data coming in from the URL in a 2D array
		$FieldDataArr = array();
		
		// The key is the layer level and the value is the Sort Data that the user enters.
		// After we sort based on the user's input... we can then find the correspending Data in the FieldDataArr based on Layer Level
		$SortArray = array();
		
		
		foreach($_REQUEST as $ParamName => $ParamValue){
		
			$matches = array();
			if(preg_match_all("/^field_(\d+)_(\d+)$/", $ParamName, $matches)){
				$SideNumber = $matches[1][0];
				$LayerLevel = $matches[2][0];
				
				if(!isset($FieldDataArr[$SideNumber]))
					$FieldDataArr[$SideNumber] = array();
					
				if(strlen($ParamValue) > 50)
					throw new Exception("Problem with the FieldName Value");
				
				$FieldDataArr[$SideNumber][$LayerLevel] = $ParamValue;
			}
			
			$matches = array();
			if(preg_match_all("/^sort_(\d+)_(\d+)$/", $ParamName, $matches)){
				$SideNumber = $matches[1][0];
				$LayerLevel = $matches[2][0];

				if(!isset($SortArray[$SideNumber]))
					$SortArray[$SideNumber] = array();
					
				if(strlen($ParamValue) > 3)
					throw new Exception("Problem with the Sort Value");
					
				$SortArray[$SideNumber][$LayerLevel] = $ParamValue;
			}
		}
		
		// Now write data back into our artwork object
		for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++){
		
			// These values should always be there... but in case they tamper with the  URL or something.
			if(!isset($FieldDataArr[$i]))
				continue;
			if(!isset($SortArray[$i]))
				continue;
			
			// Grab a 2D array off of our 3D array 
			$FieldsFromSideArr = $FieldDataArr[$i];
			$SortDataFromSideArr = $SortArray[$i];

						
			// Sort the Layer Field orders (unique ID within the artwork file) based upon the Sort data that was entered in the Form
			asort($SortDataFromSideArr);

			// We are actually not going to use the Sort values that come in the URL
			// We are going to use the sorting sequence from the user input... but use out own increment counter to make sure the sort values always start at 1 and count up.
			$SortCounter = 1;
			foreach($SortDataFromSideArr as $LayerLevel => $SortValue){
				
				// Now we want to loop through the Layers in the artwork object.
				// When we find the corresponding layer level... update the field order for it.
				for($j=0; $j<sizeof($ArtworkInfoObj->SideItemsArray[$i]->layers); $j++){
		
					if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerType != "text")
						continue;

					if($LayerLevel == $ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->level){
												

						// Now we want to find the corresponding "Field" description from the user Input
						// If we find (and we always should if they don't tamper with the URL) the corresponding Description of the field... then save it into the Artwork Object as well
						$fieldNameFound = "";
						foreach($FieldsFromSideArr as $FieldLayerLevel => $fieldData){
							if($FieldLayerLevel == $LayerLevel){
								$fieldData = stripslashes(trim($fieldData));
								$fieldData = preg_replace("/(&|\/|<|>|\")/", "", $fieldData); // Get rid of bad characaters that will cause the XML to fail parsing
								$ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->field_name = WebUtil::htmlOutput($fieldData);
								$fieldNameFound = $fieldData;
							}
						}
						
						// We don't want to give a layer a field order if there is no field description.
						if(!empty($fieldNameFound)){
							$ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->field_order = $SortCounter;
							$SortCounter++;
						}
						else
							$ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->field_order = 0;
					}
				}
			}
		}
		
		ArtworkLib::SaveArtXMLfile($dbCmd, $templateView, $templateID, $ArtworkInfoObj->GetXMLdoc());
		
		NavigationHistory::recordPageVisit($dbCmd, $UserID, "TempTxtFld", ($templateArea . ":" . $templateID));
		
		print "<html><script>top.closeCurrentTextFieldWindow();</script></html>";
		exit();
		
	}
	else{
		throw new Exception("Illegal Action");
	}
}



// Put everything in a 3D hash... so we can sort by the Layer Level
$TextLayersArr = array();


for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++){


	$TextLayersArr[$i] = array();
	
	$LayerCounter = 0;
	
	foreach ($ArtworkInfoObj->SideItemsArray[$i]->layers as $LayerObj) {

		if($LayerObj->LayerType == "text"){

			// Text layers which are shadows to other layers should not be listed as a Quick Edit Field
			if($ArtworkInfoObj->CheckIfTextLayerIsShadowToAnotherLayer($i, $LayerObj->level))
				continue;

			$TextLayersArr[$i][$LayerCounter] = array();
				
			// PDF Lib can't handle UTF-8 Characters.
			// Make sure never to call this function twice on the same string.
			$TextString = utf8_decode($LayerObj->LayerDetailsObj->message);
			
			$Message = WebUtil::htmlOutput($TextString);

			$TextLayersArr[$i][$LayerCounter]["LayerLevel"] = $LayerObj->level;
			$TextLayersArr[$i][$LayerCounter]["FieldOrder"] = $LayerObj->LayerDetailsObj->field_order;
			$TextLayersArr[$i][$LayerCounter]["FieldName"] = WebUtil::htmlOutput($LayerObj->LayerDetailsObj->field_name);
			$TextLayersArr[$i][$LayerCounter]["Message"] = $Message;
			
			$LayerCounter++;
		}
	}
}


$t->set_block("origPage","TextLayerBL","TextLayerBLout");



// If there is only 1 side... then we do not need to show the Front/Back label, etc.
if(sizeof($ArtworkInfoObj->SideItemsArray) > 1)
	$ShowSides = true;
else
	$ShowSides = false;

$textLayerFound = false;

for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++){

	$PrintFirstLayerOnSide = true;
	
	// Get 1 level off of the 3D array... so $LayersSortedArr is a 2D array.
	$LayersSortedArr = $TextLayersArr[$i];

	// This function will sort 2-D array based on the "Field Order" --#
	WebUtil::array_qsort2($LayersSortedArr, "FieldOrder");
	
	$SortCounter = 1;
	
	foreach ($LayersSortedArr as $LayerSortedObj) {
	
		$textLayerFound = true;
		
		// Skip over any text layers that do not have any Field descriptions.  Those will always go on the bottom
		if(empty($LayerSortedObj["FieldName"]))
			continue;

		if($PrintFirstLayerOnSide && $ShowSides)
			$t->set_var("SIDE_DESC", "<br><font color='#6699cc' size='4'><b>" . WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[$i]->description) . "</b></font><br><img src='./images/greypixel.gif' width='500' height='1'><br><br>");
		else
			$t->set_var("SIDE_DESC", "");
			
		$t->allowVariableToContainBrackets("SIDE_DESC");

		$PrintFirstLayerOnSide = false;

		$t->set_var("FIELD", WebUtil::htmlOutput($LayerSortedObj["FieldName"]));
		$t->set_var("TEXT_LAYER", $Message = preg_replace("/!br!/", "<br>", WebUtil::htmlOutput($LayerSortedObj["Message"])));
		$t->set_var("LEVEL", $LayerSortedObj["LayerLevel"]);
		$t->set_var("SORT", $SortCounter);
		$t->set_var("SIDENUMBER", $i);
		$t->allowVariableToContainBrackets("TEXT_LAYER");
		
		$SortCounter ++;


		$t->parse("TextLayerBLout","TextLayerBL",true);
	}
	foreach ($LayersSortedArr as $LayerSortedObj) {
	
		// Now we are going to write out all of the text layers that do not have a field name yet.
		if(!empty($LayerSortedObj["FieldName"]))
			continue;

		if($PrintFirstLayerOnSide && $ShowSides)
			$t->set_var("SIDE_DESC", "<br><font color='#6699cc' size='4'><b>" . WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[$i]->description) . "</b></font><br><img src='./images/greypixel.gif' width='500' height='1'><br><br>");
		else
			$t->set_var("SIDE_DESC", "");
			
		$t->allowVariableToContainBrackets("SIDE_DESC");

		$PrintFirstLayerOnSide = false;

		$t->set_var("FIELD", "");
		$t->set_var("TEXT_LAYER", $Message = preg_replace("/!br!/", "<br>", WebUtil::htmlOutput($LayerSortedObj["Message"])));
		$t->set_var("LEVEL", $LayerSortedObj["LayerLevel"]);
		$t->set_var("SORT", "");
		$t->set_var("SIDENUMBER", $i);
		$t->allowVariableToContainBrackets("TEXT_LAYER");
		
		$SortCounter ++;

		$t->parse("TextLayerBLout","TextLayerBL",true);
	}
}


if(!$textLayerFound){
	$t->set_var("TextLayerBLout", "There are no text layers on this template.");
}

$t->set_var("TEMPLATE_AREA", $templateArea);
$t->set_var("TEMPLATE_ID", $templateID);

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->pparse("OUT","origPage")

?>