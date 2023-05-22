<?

require_once("library/Boot_Session.php");


$startrow = WebUtil::GetInput("startrow", FILTER_SANITIZE_INT, 1);
$startcolumn = WebUtil::GetInput("startcolumn", FILTER_SANITIZE_INT, 1);
$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);

$projectlistArr = explode("|", $projectlist);

$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

// Filter out blank spaces from extra pipe symbols.
$tempArr = array();
foreach($projectlistArr as $thisProjectID){
	$thisProjectID = trim($thisProjectID);
	if(!empty($thisProjectID))
		$tempArr[] = $thisProjectID;
}

$projectlistArr = $tempArr;


if(!ProjectOrdered::validateDomainPermissionOnProjectArr($dbCmd, $projectlistArr))
	throw new Exception("One of the Project IDs is not available for label generation.");



$domainObj = Domain::singleton();





$dbCmd->Query("SELECT ReplicationRows, ReplicationColumns, PageWidth, PageHeight, SpacingHorizontal, 
		SpacingVertical, MarginsLeft, MarginsTop, LabelWidth, LabelHeight, 
		QuantitySpill  FROM labelsetup WHERE UserID=" . $UserID);


if($dbCmd->GetNumRows() == 0)
	throw new Exception("Error:  You have not saved your label configuration yet.");

$row = $dbCmd->GetRow();

$rows = $row["ReplicationRows"];
$columns = $row["ReplicationColumns"];
$pagewidth = $row["PageWidth"];
$pageheight = $row["PageHeight"];
$hspacing = $row["SpacingHorizontal"];
$vspacing = $row["SpacingVertical"];
$lmargin = $row["MarginsLeft"];
$bmargin = $row["MarginsTop"];
$labelw = $row["LabelWidth"];
$labelh = $row["LabelHeight"];
$QuantitySpill = $row["QuantitySpill"];

// In Case the values are not set... make some defaults as a starting point
if($rows == "")
	throw new Exception("Error:  You have not saved your label configuration yet.");


// The PDF system measures in points which are 72 points per inch --#
$inches_2_PDF = 72;

$pagewidth = $pagewidth * $inches_2_PDF;
$pageheight = $pageheight * $inches_2_PDF;
$hspacing = $hspacing * $inches_2_PDF;
$vspacing = $vspacing * $inches_2_PDF;
$lmargin = $lmargin * $inches_2_PDF;
$bmargin = $bmargin * $inches_2_PDF;
$labelw = $labelw * $inches_2_PDF;
$labelh = $labelh * $inches_2_PDF;



$user_sessionID =  WebUtil::GetSessionID();



// Create the PDF doc with PHP's extension PDFlib --#
$pdf = pdf_new();

//Set the license on the main server
if(!Constants::GetDevelopmentServer())
	pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());


pdf_open_file($pdf, "");
pdf_set_info($pdf, "Title", "Label Sheets");;
pdf_set_info($pdf, "Subject", "Label Sheets");

PDF_set_parameter($pdf, "SearchPath", Constants::GetFontBase());



// Initialize some parallel arrays for containing the project information ... 
// The common index to all arrays is the project counter.
$OrderNumbersArr = array();
$ShippingTypeArr = array();
$MainOrderIDArr = array();
$ProductIDArr = array();

$ProjectCounter = 0;

$query = "SELECT projectsordered.Status, projectsordered.ID AS ProjectID, orders.ID AS OrderID, projectsordered.OrderDescription, 
		orders.ShippingChoiceID, projectsordered.OptionsAlias, projectsordered.ProductID, 
		projectsordered.Quantity FROM orders INNER JOIN projectsordered on projectsordered.OrderID = orders.ID WHERE ";

$query .= DbHelper::getOrClauseFromArray("projectsordered.ID", $projectlistArr);

$query .= " AND " . DbHelper::getOrClauseFromArray("projectsordered.DomainID", $domainObj->getSelectedDomainIDs());

$query .= " ORDER BY orders.DateOrdered DESC, projectsordered.ID DESC";

$dbCmd->Query($query);

while($row = $dbCmd->GetRow()){

	$Status = $row["Status"];
	$ProjectOrderID = $row["ProjectID"];
	$MainOrderID = $row["OrderID"];
	$OrderDescription = $row["OrderDescription"];
	$ShippingChoiceID = $row["ShippingChoiceID"];
	$OptionsAlias = $row["OptionsAlias"];
	$ProductID = $row["ProductID"];
	$Quantity = $row["Quantity"];

	// For large boxes of business cards... like 2000, 3000, 5000.   The largest box can hold 1000... so make duplicate order number labels in increments of QuantitySpill until quantity is reached.
	$QuantitySpillCounter = 0;
	while($QuantitySpillCounter < $Quantity){
	
		//Make the label read differently if we are spilled over
		if($QuantitySpillCounter == 0)
			$OrderNumbersArr[$ProjectCounter] = array("OrderNumber"=>$MainOrderID . " - P" . $ProjectOrderID, 
								"OrderDescription"=>$OrderDescription, 
								"OptionsAlias"=>$OptionsAlias);
		else
			$OrderNumbersArr[$ProjectCounter] = array("OrderNumber"=>"Cont. - " . $MainOrderID, 
								"OrderDescription"=>$OrderDescription, 
								"OptionsAlias"=>$OptionsAlias);
		
		
		$ShippingTypeArr[$ProjectCounter] = $ShippingChoiceID;
		$MainOrderIDArr[$ProjectCounter] = $MainOrderID;
		$ProductIDArr[$ProjectCounter] = $ProductID;
		$ProjectCounter++;
		
		$QuantitySpillCounter += $QuantitySpill;
	}

}




// Make a new page
pdf_begin_page($pdf, $pagewidth, $pageheight);
pdf_add_bookmark($pdf, "Page 1", 0, 0);


// Set up the font we want to use 
pdf_set_parameter( $pdf, "FontOutline", "Decker=Decker.ttf");
$fontRes = pdf_findfont($pdf, "Decker", "winansi", 1);
$fontResBold = pdf_findfont($pdf, "CityOf", "winansi", 1);

$pageNumber = 1;

// The first spot on the grid comes from the URL
$currentRow = $startrow;
$currentColumn = $startcolumn;


for($i=0; $i<sizeof($OrderNumbersArr); $i++){

	pdf_save($pdf);

	// Translate the coordinates where the next label should be written.  We can figure out by the row and column number
	pdf_translate($pdf, GetXcoordinateOfLabel($currentColumn), GetYcoordinateOfLabel($currentRow));

	//  write the order number onto the PDF doc.
	pdf_setfont ( $pdf, $fontRes, 8);
	pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
	pdf_show_xy($pdf, $OrderNumbersArr[$i]["OrderNumber"], 0, 0);

	//  write the order description onto the PDF doc.
	pdf_setfont ( $pdf, $fontRes, 6);
	pdf_setcolor($pdf, "both", "rgb", .5, .5, .5, 0);
	pdf_show_xy($pdf, $OrderNumbersArr[$i]["OrderDescription"], 0, -10);

	//  write the product Options onto the PDF doc.
	pdf_setfont ( $pdf, $fontRes, 5);
	pdf_show_xy($pdf, $OrderNumbersArr[$i]["OptionsAlias"], 0, -16);


	// Find out if there are multiple projects on this Order..
	// If so then add a flag on the label to give us a warning that the shipment may be combined
	// WE are looking for projects that have not been canceled, belonging to the same order and sharging the same product ID 
	$dbCmd->Query("SELECT COUNT(*) FROM projectsordered 
			WHERE OrderID=" . $MainOrderIDArr[$i] . " AND 
			Status != 'C' AND ProductID = " . $ProductIDArr[$i]);
	$NumberOfProjects = $dbCmd->GetValue();
	
	if($NumberOfProjects > 1){

		// Draw a box around the order number
		pdf_setlinewidth($pdf, 1);
		pdf_rect($pdf, -3, -3, 80, 12);
		pdf_stroke($pdf);
		
		pdf_show_xy($pdf, $NumberOfProjects, 67, 1);
	}
	
	// Find out if it is expidited shipping... If show them a signal
	// Write the order number a couple of timest 
	if($ShippingTypeArr[$i] == "1"){
		pdf_setfont ( $pdf, $fontResBold, 12);
		pdf_setcolor($pdf, "both", "rgb", .6, 0, 0, 0);
		pdf_show_xy($pdf, $ShippingTypeArr[$i], 84, -1);
	}
	else if($ShippingTypeArr[$i] == "2"){
		pdf_setfont ( $pdf, $fontResBold, 12);
		pdf_setcolor($pdf, "both", "rgb", 0, 0, .6, 0);
		pdf_show_xy($pdf, $ShippingTypeArr[$i], 84, -1);
	}
	else if($ShippingTypeArr[$i] == "T"){
		pdf_setfont ( $pdf, $fontResBold, 12);
		pdf_setcolor($pdf, "both", "rgb", 0, .6, 0, 0);
		pdf_show_xy($pdf, $ShippingTypeArr[$i], 84, -1);
	}


	pdf_restore($pdf);

	// Increase the column and row numbers accordingly
	$currentColumn++;
	if($currentColumn > $columns){
		$currentColumn = 1;
		$currentRow++;
	}

	// Now check if it is time to make a new page.
	if($currentRow > $rows){

		// However, we only want to make a new page if there are more labels
		if($i< (sizeof($OrderNumbersArr) -1)){
			pdf_end_page($pdf);

			$pageNumber++;
			$currentRow = 1;

			pdf_begin_page($pdf, $pagewidth, $pageheight);
			pdf_add_bookmark($pdf, "Page " . $pageNumber, 0, 0);

			#-- Set up the font we want to use --#
			pdf_set_parameter( $pdf, "FontOutline", "Decker=Decker.ttf");
			$fontRes = pdf_findfont($pdf, "Decker", "winansi", 1);
			pdf_setfont ( $pdf, $fontRes, 8);
		}
	}
}

pdf_end_page($pdf);
pdf_close($pdf);




$data = pdf_get_buffer($pdf);

$nocache = time();

// Put PDF on disk 
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/Production_labels_" . $nocache . ".pdf", "w");
fwrite($fp, $data);
fclose($fp);


// Redirect to the Temporary PDF document
// A cron job will need to delete the proofs every 2 hours or so to keep the disk from getting full
header("Location: " . DomainPaths::getPdfWebPathOfDomainInURL() . "/Production_labels_" . $nocache . ".pdf");



// This function will translate a row or column number into a PDF coordinate (measured from the bottom left)
function GetXcoordinateOfLabel($columnNumber){

	global $hspacing;
	global $lmargin;
	global $labelw;

	return ($columnNumber * $hspacing) + ($columnNumber * $labelw) - $hspacing - $labelw + $lmargin;
}
// This function will translate a row or column number into a PDF coordinate (measured from the bottom left)
function GetYcoordinateOfLabel($rowNumber){

	global $vspacing;
	global $bmargin;
	global $labelh;
	global $pageheight;

	// subtracting the result from the "$pageheight" will reverse so that the lables are starting from the top instead of the bottom

	return $pageheight - (($rowNumber * $vspacing) + ($rowNumber * $labelh) - ($vspacing + $labelh) + $bmargin);

}



?>