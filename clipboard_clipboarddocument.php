<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$ClipBoardArray = Clipboard::GetClipBoardArray();


$t = new Templatex(".");

$t->set_file("origPage", "clipboard_clipboarddocument-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Break the layers into their own pieces.  Sort by the LayerID

// Nested Blocks
$t->set_block("origPage","TextOuterTableBL","TextOuterTableBLout");
$t->set_block("TextOuterTableBL","TextInnerTableBL","TextInnerTableBLout");
$t->set_block("origPage","ImageLayerBl","ImageLayerBlout");


$LayersSorted = array();
$textLayerCounter = 0;

$t->allowVariableToContainBrackets("TEXT_COLUMN2");

foreach($ClipBoardArray as $ThisClipBoardID => $ClipBoardItemObj){

	if($ClipBoardItemObj["LayerObj"]->LayerType == "text"){

		// If this layer is a shadow (belonging to another layer) then we don't want to show it on the Clipboard
		if(Clipboard::CheckIfClipboardIDisShadowToAnotherLayer($ThisClipBoardID))
			continue;

		$textLayerCounter++;
		
		$TextAttDropDown = Clipboard::GetTextAttributesDropDown($ClipBoardItemObj["LayerObj"]);
		
		// PDF Lib can't handle UTF-8 Characters.
		// Make sure never to call this function twice on the same string.
		$TextString = utf8_decode($ClipBoardItemObj["LayerObj"]->LayerDetailsObj->message);
		
		$testInfoMessage = WebUtil::htmlOutput($TextString);
		
		$t->set_var("TEXT_INFO", preg_replace("/!br!/", "<br>", $testInfoMessage));
		$t->set_var("LAYERID", $ThisClipBoardID);
		$t->set_var("TEXT_ATTRIBUTES", $TextAttDropDown);
		
		$t->allowVariableToContainBrackets("TEXT_INFO");
		$t->allowVariableToContainBrackets("TEXT_ATTRIBUTES");
		
		// Odd number means first column
		if($textLayerCounter % 2 <> 0){
			$t->parse("TextInnerTableBLout","TextInnerTableBL",false);
		}
		else{
			$t->parse("TEXT_COLUMN2","TextInnerTableBL",false);
			$t->parse("TextOuterTableBLout","TextOuterTableBL",true);
		}
		
	}
}

if($textLayerCounter == 0){

	// Hide the Select All buttons
	$t->set_block("origPage","HideTextSelectAll","HideTextSelectAllout");
	$t->set_var("HideTextSelectAllout", "");

	$t->set_var("TextOuterTableBLout", "<br><font class='Largebody'>&nbsp;&nbsp;&nbsp;&nbsp;<b>No Text Layers.</b></font>");
}
else{
	// If so... then is means that we ended on an odd number of layers... so we have to parse the last row out.
	if($textLayerCounter % 2 <> 0){
		$t->set_var(array("TEXT_COLUMN2"=>"&nbsp;"));
		$t->parse("TextOuterTableBLout","TextOuterTableBL",true);
	}
}



$ImageLayerCounter = 0;
foreach($ClipBoardArray as $ThisClipBoardID => $ClipBoardItemObj){

	if($ClipBoardItemObj["LayerObj"]->LayerType == "graphic"){

		$ImageLayerCounter++;
		
		$TheImageFileName = ImageLib::WriteImageIDtoDisk($dbCmd, $ClipBoardItemObj["LayerObj"]->LayerDetailsObj->imageid);

		// Create the image source so that the browser can get access
		$TempImgSrc = "./image_preview/" . $TheImageFileName;

		$ImageDescStr = "";

		$t->set_var("IMAGE_ATTRIBUTES", $ImageDescStr);
		$t->set_var("LAYERID", $ThisClipBoardID);
		$t->set_var("IMAGE_SRC", $TempImgSrc);
		
		$t->allowVariableToContainBrackets("IMAGE_ATTRIBUTES");

		$t->parse("ImageLayerBlout","ImageLayerBl",true);
		

	}
}
if($ImageLayerCounter == 0){
	// Hide the Select All buttons
	$t->set_block("origPage","HideImageSelectAll","HideImageSelectAllout");
	$t->set_var("HideImageSelectAllout", "");
	
	$t->set_var("ImageLayerBlout", "<br><font class='Largebody'>&nbsp;&nbsp;&nbsp;&nbsp;<b>No Image Layers.</b></font>");
}


$t->pparse("OUT","origPage");


?>