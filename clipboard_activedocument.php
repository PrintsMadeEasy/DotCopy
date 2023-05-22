<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();


$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_INT);


ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);



$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);


// Parse the xml document and populate and Object 
$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);


$t = new Templatex(".");

$t->set_file("origPage", "clipboard_activedocument-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var(array(
	"PROJECTID"=>$projectid,
	"VIEW"=>$view,
	"SIDENUMBER"=>$sidenumber
	));

$t->set_var("RETURN_URL_ENCODED", urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']) );

// Loop through each side in the Artwork File and create Tabs
$t->set_block("origPage","TABS_bl","TABS_blout");
$SideCounter = 0;
foreach ($ArtworkInfoObj->SideItemsArray as $SideObj) {

	$t->set_var(array("TABNAME"=>$SideObj->description));

	// Find out if the tab we are creating matches the side that we are viewing.
	if($sidenumber == $SideCounter){
		$t->set_var(array("TABCOLOR"=>"#666666", "LINKSTART"=>"", "LINKEND"=>""));
	}
	else{
		$TabLink = '<a href="javascript:LinkToTab(\'' .  $SideCounter . '\')" style="text-decoration:none">';
		$t->set_var(array("TABCOLOR"=>"#bbbbbb", "LINKSTART"=>$TabLink, "LINKEND"=>"</a>"));
	}
	
	$t->allowVariableToContainBrackets("LINKSTART");
	$t->allowVariableToContainBrackets("LINKEND");

	$t->parse("TABS_blout","TABS_bl",true);
	$SideCounter++;
}
// SideCounter is incremented to 1... Even if we are on tab ID 0.. So just subtract 1 here
if($sidenumber >= sizeof($ArtworkInfoObj->SideItemsArray) ){
	WebUtil::PrintError("There is a problem with the URL");
}


// If there is only one tab on the screen.. no point in showing it to the user.  Just remove the tab block completely
if($SideCounter == 1){
	$t->set_block("origPage","eraseTabsBL","eraseTabsBLout");
	$t->set_var(array("eraseTabsBLout"=>""));
}





// Break the layers into their own pieces.  Sort by the LayerID

//Nested Blocks
$t->set_block("origPage","TextOuterTableBL","TextOuterTableBLout");
$t->set_block("TextOuterTableBL","TextInnerTableBL","TextInnerTableBLout");
$t->set_block("origPage","ImageLayerBl","ImageLayerBlout");
$t->set_block("ImageLayerBl","VectorImageBL","VectorImageBLout");


$textLayerCounter = 0;


$ArtworkInfoObj->orderLayersByLayerLevel($sidenumber);

// Reverse the array so that it will match up to the Layer stacking order.
$LayersSorted = array_reverse($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers);

foreach($LayersSorted as $ThisLayer){

	if($ThisLayer->LayerType == "text"){
	
		// If this layer is a shadow (belonging to another layer) then we don't want to show it on the Active Document
		if($ArtworkInfoObj->CheckIfTextLayerIsShadowToAnotherLayer($sidenumber, $ThisLayer->level))
			continue;

		$textLayerCounter++;
		
		$TextAttDropDown = Clipboard::GetTextAttributesDropDown($ThisLayer);


		// Make a DHTML fly open menu showing all of the Attributes
		$permissionLinksHTML = "";
		$permissionLinksHTML .= "<input type='checkbox' name='position_x_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->position_x_locked ? "checked" : "") . "> X Coord Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='position_y_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->position_y_locked ? "checked" : "") . "> Y Coord Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='size_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->size_locked ? "checked" : "") . "> Size Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='deletion_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->deletion_locked ? "checked" : "") . "> Deletion Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='color_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->color_locked ? "checked" : "") . "> Color Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='font_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->font_locked ? "checked" : "") . "> Font Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='alignment_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->alignment_locked ? "checked" : "") . "> Alignment Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='rotation_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->rotation_locked ? "checked" : "") . "> Rotation Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='data_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->data_locked ? "checked" : "") . "> Data Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='selectable_no_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->not_selectable ? "checked" : "") . "> Not Selectable</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='transferable_no_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->not_transferable ? "checked" : "") . "> Not Transferable</a><br>";
		
		$permissionLinksHTML .= "<table cellpadding='0' cellspacing='0' width='100%'><tr><td align='right'><a class='blueredlink' href='javascript:SavePermissions(\"text\", " . $ThisLayer->level . ");' class='blueredlink''>Save</a></td></tr></table>";


		
		$permissionsDHTMwindow = getDHTMLwindowForPermissions($ThisLayer->level, $permissionLinksHTML, $ThisLayer->LayerDetailsObj->permissions->checkForAtLeast1Permission());


		// PDF Lib can't handle UTF-8 Characters.
		// Make sure never to call this function twice on the same string.
		$TextString = utf8_decode($ThisLayer->LayerDetailsObj->message);
		
		$textInfoMessage = WebUtil::htmlOutput($TextString);
		
		$t->set_var("TEXT_INFO", preg_replace("/!br!/", "<br>", $textInfoMessage));
		$t->set_var("LAYERID", $ThisLayer->level);
		$t->set_var("TEXT_ATTRIBUTES", $TextAttDropDown);
		$t->set_var("PERMISSIONS", $permissionsDHTMwindow);
		
		$t->allowVariableToContainBrackets("TEXT_ATTRIBUTES");
		$t->allowVariableToContainBrackets("TEXT_INFO");
		$t->allowVariableToContainBrackets("PERMISSIONS");
		$t->allowVariableToContainBrackets("TEXT_COLUMN2");
		$t->allowVariableToContainBrackets("DPI_WARNING");
		
		
		// Place text layers in 2 separate columns
		// Odd number means first column
		if($textLayerCounter % 2 <> 0){
			$t->parse("TextInnerTableBLout","TextInnerTableBL",false);
		}
		else{
			$t->parse("TEXT_COLUMN2","TextInnerTableBL",false);
			$t->allowVariableToContainBrackets("TEXT_COLUMN2");
			$t->parse("TextOuterTableBLout","TextOuterTableBL",true);
		}
		
	}
}

if($textLayerCounter == 0){

	// Hide the Select All buttons
	$t->set_block("origPage","HideTextSelectAll","HideTextSelectAllout");
	$t->set_var(array("HideTextSelectAllout"=>""));

	$t->set_var(array("TextOuterTableBLout"=>"<br><font class='Largebody'>&nbsp;&nbsp;&nbsp;&nbsp;<b>No Text Layers.</b></font>"));
}
else{
	// If so... then is means that we ended on an odd number of layers... so we have to parse the last row out.
	if($textLayerCounter % 2 <> 0){
		$t->set_var(array("TEXT_COLUMN2"=>"&nbsp;"));
		$t->parse("TextOuterTableBLout","TextOuterTableBL",true);
	}
}



// http://bugs.php.net/bug.php?id=22526 ... this keeps the server from hanging when running the popen system command  through the function call ImageLib::GetDimensionsFromImageFile();
session_write_close();


$ImageLayerCounter = 0;
foreach($LayersSorted as $ThisLayer){

	if($ThisLayer->LayerType == "graphic"){

		$ImageLayerCounter++;
		
		$TheImageFileName = ImageLib::WriteImageIDtoDisk($dbCmd, $ThisLayer->LayerDetailsObj->imageid);

		// Create the image source so that the browser can get access
		$TempImgSrc = "./image_preview/" . $TheImageFileName;
		
		$Image_SystemPath = Constants::GetTempImageDirectory() . "/" . $TheImageFileName;

		// Find out how big the file is in raw pixels
		$DimHash = ImageLib::GetDimensionsFromImageFile($Image_SystemPath);

		if(empty($DimHash["Width"]) || empty($DimHash["Height"]))
			throw new Exception("Error with Image Dimensions.");
			
		$ImagePixelWidth = $DimHash["Width"];
		$ImagePixelHeight = $DimHash["Height"];

		$ImageDescStr = Clipboard::GetImageAttributesDesc($ArtworkInfoObj->SideItemsArray[$sidenumber]->dpi, $ThisLayer, $ImagePixelWidth, $ImagePixelHeight);

		// We store images in the flash file in coordinates (at 96 dpi).
		$dpi_Ratio = $ArtworkInfoObj->SideItemsArray[$sidenumber]->dpi / 96; 

		$NewPixelWidth = round($ThisLayer->LayerDetailsObj->width * $dpi_Ratio);
		$NewPixelHeight = round($ThisLayer->LayerDetailsObj->height * $dpi_Ratio);

		// Now we want to check if the image is stretched out too big for the current DPI setting
		
		// Prevent division by 0
		if($NewPixelHeight == 0)
			$NewPixelHeight = 1;
		if($NewPixelWidth == 0)
			$NewPixelWidth = 1;
		
		// We want to get a ratio of width vs. height on both the original dimensions and the the modified ones
		// If the ratios differ by 30% then we don't care about checking DPI
		$OriginalDimRatio = $ImagePixelWidth / $ImagePixelHeight;
		$NewDimRatio = $NewPixelWidth / $NewPixelHeight;
		
		// Now get a ratio between ratios
		$RR = $OriginalDimRatio / $NewDimRatio;
		
		// If the number equals 1 .. then it means they have not distorted the image.
		$ImageDistortion = 1 - $RR;
		
		$ImageDistortion = abs($ImageDistortion * 100);
		
		$DPIwarning = "";
		if($ImageDistortion < 30){

			// Now get a ratio between the old with and new width... same with height... if either dimension has been stretched more than 30% then we will complain
			$WidthRatio = ($NewPixelWidth - $ImagePixelWidth) / $ImagePixelWidth;
			$HeightRatio = ($NewPixelHeight - $ImagePixelHeight) / $ImagePixelHeight;
			
			if($WidthRatio * 100 > 30  || $HeightRatio * 100 > 30 )
				$DPIwarning = "<br><font class='error'>The DPI may not be sufficient.</font>";	
		}
		
		// Clear out the inner block
		$t->set_var("VectorImageBLout", "");
		
		
		// If this is a Vector Image... then show a link to download the file of the source file.
		if(!empty($ThisLayer->LayerDetailsObj->vector_image_id)){
			
			$dbCmd->Query("SELECT Record, TableName FROM vectorimagepointer WHERE ID = " . $ThisLayer->LayerDetailsObj->vector_image_id);
			$vectPointerRow = $dbCmd->GetRow();
			
			if($dbCmd->GetNumRows() >= 0){

				$dbCmd->Query("SELECT OrigFileName, OrigFileType, OrigFileSize FROM " . $vectPointerRow["TableName"] . " WHERE ID = " . $vectPointerRow["Record"]);
				$vectImageRow = $dbCmd->GetRow();

				$t->set_var("VECTOR_IMAGE_NAME", WebUtil::htmlOutput($vectImageRow["OrigFileName"]));
				$t->set_var("VECTOR_IMAGE_SIZE", round($vectImageRow["OrigFileSize"] / 1024 / 1024, 2) . " MB");
				$t->set_var("VECTOR_IMAGE_TYPE", strtoupper(ImageLib::getImageExtentsionByFileType($vectImageRow["OrigFileType"])));
				$t->set_var("VECTOR_IMAGE_ID", $ThisLayer->LayerDetailsObj->vector_image_id);
				
				$t->parse("VectorImageBLout","VectorImageBL",true);
			}
		}



		// Make a DHTML fly open menu showing all of the Attributes
		$permissionLinksHTML = "";
		$permissionLinksHTML .= "<input type='checkbox' name='position_x_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->position_x_locked ? "checked" : "") . "> X Coord Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='position_y_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->position_y_locked ? "checked" : "") . "> Y Coord Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='size_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->size_locked ? "checked" : "") . "> Size Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='deletion_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->deletion_locked ? "checked" : "") . "> Deletion Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='rotation_locked_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->rotation_locked ? "checked" : "") . "> Rotation Locked</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='selectable_no_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->not_selectable ? "checked" : "") . "> Not Selectable</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='transferable_no_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->not_transferable ? "checked" : "") . "> Not Transferable</a><br>";
		$permissionLinksHTML .= "<input type='checkbox' name='always_on_top_" . $ThisLayer->level . "' " . ($ThisLayer->LayerDetailsObj->permissions->always_on_top ? "checked" : "") . "> Always on Top</a><br>";
		
		$permissionLinksHTML .= "<table width='100%' cellpadding='0' cellspacing='0'><tr><td align='right'><a class='blueredlink' href='javascript:SavePermissions(\"graphic\", " . $ThisLayer->level . ");' class='blueredlink''>Save</a></td></tr></table>";

		
		$permissionsDHTMwindow = getDHTMLwindowForPermissions($ThisLayer->level, $permissionLinksHTML, $ThisLayer->LayerDetailsObj->permissions->checkForAtLeast1Permission());



		$t->set_var("DPI_WARNING", $DPIwarning);
		$t->set_var("IMAGE_ATTRIBUTES", $ImageDescStr);
		$t->set_var("LAYERID", $ThisLayer->level);
		$t->set_var("IMAGE_SRC", $TempImgSrc);
		$t->set_var("PERMISSIONS", $permissionsDHTMwindow);
		
		$t->allowVariableToContainBrackets("PERMISSIONS");
		$t->allowVariableToContainBrackets("DPI_WARNING");
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





function getDHTMLwindowForPermissions($layerLevel, $innerHTML, $hasAtLeast1PermissionFlag){

	$permissionsHTML = "<div id='LayerPermDiv" . $layerLevel . "' class='hiddenDHTMLwindow' >";

	// If there are any permissions set on the Layer... then show it in bold.
	$permissionLink = "<a href='javascript:showLayerPermissions(". $layerLevel . ", true);'><font color='#FFFFFF'>Permissions</font></a>";

	

	if($hasAtLeast1PermissionFlag)
		$permissionLink = "<b>$permissionLink <font color='#cc0000'>*</font></b>";

	$permissionsHTML .= $permissionLink;

	$permissionsHTML .= "<span style='visibility:hidden; left:-40px; top:-25' id='LayerPermSpan" . $layerLevel . "'  >" . getPermissionTableHTML($innerHTML, $layerLevel) . "</span></div>";

	return $permissionsHTML;
}

function getPermissionTableHTML($innerHTML, $layerLevel){

	$retHTML = '
	  <table width="146" border="0" cellspacing="0" cellpadding="2" bgcolor="#CC6600" style="-moz-opacity:60%;filter:progid:DXImageTransform.Microsoft.dropShadow(Color=#999999,offX=3,offY=3)" >
	    <tr> 
	      <td> 
		<table width="100%" border="0" cellspacing="0" cellpadding="8" bgcolor="#FFFFFF">
		  <tr> 
		    <td class="ReallySmallBody" align="left" style="background-image: url(./images/admin/arrival_time_back.png); background-repeat: repeat;">
		    
		    <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="ReallySmallBody" align="right"><a class="blueredlink" href="javascript:showLayerPermissions('. $layerLevel . ', false);">Close</a></td></tr></table>
		    <img src="./images/transparent.gif" height="6" width="1"><br>
		    
		      '. $innerHTML . '
		    </td>
		  </tr>
		</table>
	      </td>
	    </tr>
	  </table>
	';

	return $retHTML;
}

?>