<?php

ini_set("memory_limit", "1700M");

if(file_exists("../classes/font_measurements.php"))
	require_once '../classes/font_measurements.php';
else
	require_once 'classes/font_measurements.php';

	
class PdfDoc {
	
	// Returns Variable Data pdf in binary
	// $flushProgressOutput should be a string "none", "space", "javascript"
	// If $flushProgressOutput is anything other than "none", then the output with flush something to the browser every 20 rows
	// This can be useful to keep the browser from timing out (using a "space" character)... or with "javascript" can report the progress back to Javascript and used with a DHTML component.
	// Pass in an array of project ID's.  All of them must have "mailing" parameter enabled on the product Object.
	// ... All projects should have them same number of sides... this method will use the first project in the order as a master... (if it has double-sided so will all the rest).  $parameterObjArr has to match the first project.
	// This only works on Project Ordered ID's
	static function &generatePDFforMailing(DbCmd $dbCmd, $variableDataSortingObj, $pdf_profile, $PDFlibOptionList, $flushProgressOutput, $batchDescription){
	
	
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowProgress('<b>Status:</b> Analyzing Mailing Batch.');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}
		
		// Figure how how many Cards will be printed
		$totalCardQuantity = $variableDataSortingObj->getTotalRows();
	
	
		if(empty($totalCardQuantity))
			throw new Exception("Error in method PdfDoc::generatePDFforMailing.  The Project List can not be empty.");
	
		$projectOrderedIDarr = $variableDataSortingObj->getUniqueProjectIDarr();
		
	
		// Build the Master PDF sheet... with all of the crop marks, etc.
		$firstProjectID = $projectOrderedIDarr[0];
		$firstProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $firstProjectID);
		$blankArtworkObj = new ArtworkInformation($firstProjectObj->getArtworkFile());
			
		// Get rid of all layers.
		for($i=0; $i<sizeof($blankArtworkObj->SideItemsArray); $i++)
			$blankArtworkObj->SideItemsArray[$i]->layers = array();
	
	
		$firstPDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $firstProjectID, $pdf_profile);
	
	
	
		// Write the Batch ID... as well as the Project Options of the First Project.  We should not be Batching mailing jobs together unless they have similar options... like all (glossy), etc.
		for($i=0; $i<sizeof($firstPDFobjectsArr); $i++)
			$firstPDFobjectsArr[$i]->setOrderno($batchDescription . "   ---   (" . $firstProjectObj->getOptionsAliasStr(true) . ")");
	
	 
	
		// Record the side numbers of the first artwork in the List.
		// We are going to compare all projects in the list to make sure they are all equal to these... or through an error.
		$sideCountOfFirstArtwork = sizeof($blankArtworkObj->SideItemsArray);
		$sideCountOfFirstParametersObj = sizeof($firstPDFobjectsArr);
	
		if($sideCountOfFirstArtwork != $sideCountOfFirstParametersObj){
			$err = "Error in function PdfDoc::generatePDFforMailing... The amount of Sides on the FIRST Artwork much match the number of PDF Parameter Objects on the FIRST project.";
			WebUtil::WebmasterError($err . " - ProjectID: " . strval($firstProjectID));
			throw new Exception($err);
		}
	
		
		// Store each Project/Artwork Objects within an Array so that we don't have to re-parse every time.
		// The projectID will be the index to the array.
		$projectObjArr = array();
		$variableDataObjArr = array();
		$variableProcessObjArr = array();
		$artworkMappingObjArr = array();
		$artVarsOnlyObjForProjectsArr = array();
		$artObjForProjectsArr = array();
		$pdfParemtersForProjectsObjArr = array(); // This is a 2-D array.
		$projectHasVariableImagesArr = array(); // A boolean flag for each projectID index.
		$userIDsOfProjectsArr = array();
		$projectPDIpageBackgroundPDFarr = array();  // A 2D array... first dimension is ProjectID... second is the side number.
		$projectPDIBackgroundPDFarr = array();  // A 2D array... first dimension is ProjectID... second is the side number.
		
		// Create an array of Variable Data objects for each User.  This array will only be populated if the User has selected Variable Images on 1 or more projects
		$variableImageObjectsForUserArr = array();
		
		$projectCounter = 0;
		
		
		
		// Create a new/blank PDF doc that we will paste the master pages and write the variable data onto 
		$pdf = pdf_new();
		
		// Sets the license key, author, font paths, etc.
		PdfDoc::setParametersOnNewPDF($pdf);
		
		PDF_begin_document($pdf, "", $PDFlibOptionList);
	
		
		foreach($projectOrderedIDarr as $thisProjectID){
		
			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $thisProjectID);
			$projectObjArr[$thisProjectID] = $projectObj;
			
			// Make 2 sets of Artwork Objects... one with all of the Text Layers with {Variables} inside removed, the other with Only them left inside..
			$artworkXML = $projectObj->getArtworkFile();
			$artworkObjNoVariables = new ArtworkInformation($artworkXML);
			$artworkObjVarsOnly = new ArtworkInformation($artworkXML);
			
			// A full Artwork Object stored with each project.
			$artObjForProjectsArr[$thisProjectID] = new ArtworkInformation($artworkXML);
			
			VariableDataProcess::removeVariableLayersFromArtwork($artworkObjNoVariables);
			VariableDataProcess::removeNonVariableLayersFromArtwork($artworkObjVarsOnly);
			
			// Keep a copy of Artwork Objects indexed by ProjectID
			$artVarsOnlyObjForProjectsArr[$thisProjectID] = $artworkObjVarsOnly;
			
			// Parse the Data file into our object.
			$variableDataObj = new VariableData();
			$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile());
			$variableDataObjArr[$thisProjectID] = $variableDataObj;
	
			// Controls Mapping the positions between variable names and column positions.		
			$artworkMappingsObj = new ArtworkVarsMapping($artworkXML, $projectObj->getVariableDataArtworkConfig());
			$artworkMappingObjArr[$thisProjectID] = $artworkMappingsObj;
	
			// Variable Data process object which will give us Artwork files back with all data substituted (when asked by line number).	
			$variableDataProcessObj = new VariableDataProcess($artworkObjVarsOnly->GetXMLdoc(), $artworkMappingsObj, $variableDataObj);
			$variableProcessObjArr[$thisProjectID] = $variableDataProcessObj;
			
			$projectHasVariableImagesArr[$thisProjectID] = $projectObj->hasVariableImages();
			
			// Create a Variable Image Object for the User... only if one hasn't been created it.
			if($projectObj->hasVariableImages() && !isset($variableImageObjectsForUserArr[$projectObj->getUserID()]))
				$variableImageObjectsForUserArr[$projectObj->getUserID()] = new VariableImages($dbCmd, $projectObj->getUserID()); 
	
			$userIDsOfProjectsArr[$thisProjectID] = $projectObj->getUserID();
			
			// Keep track of every PDF Parameter Object
			$pdfParemtersForProjectsObjArr[$thisProjectID] = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $thisProjectID, $pdf_profile);
	
	
			// Make sure that if the First artwork had a Front and a Back... then all of the others in the Group do as well.
			if($sideCountOfFirstArtwork != sizeof($artworkObjNoVariables->SideItemsArray)){
				$err = "Error in function PdfDoc::generatePDFforMailing... The amount of Sides on the current project does not match that of the first one in the list.";
				WebUtil::WebmasterError($err . " - ProjectID: " . strval($thisProjectID) . " - First ProjectID: " . strval($firstProjectID));
				throw new Exception($err);
			}
			
			$projectPDIpageBackgroundPDFarr[$thisProjectID] = array();
			$projectPDIBackgroundPDFarr[$thisProjectID] = array();
	
		
			// Create the background PDF for each project (without any variable layers)
			// Store them within PDF Page Objects to cache in memory.
			for($i=0; $i < sizeof($artworkObjNoVariables->SideItemsArray); $i++){
			
				$thisProjectPDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $thisProjectID, $pdf_profile);
			
				if(!isset($thisProjectPDFobjectsArr[$i])){
					$err = "Error in method PdfDoc::generatePDFforMailing... Their was not a PDF parameter Object set for the given side.";
					WebUtil::WebmasterError($err . " - ProjectID: " . strval($thisProjectID) . " - SideNumber: " . strval($i));
					throw new Exception($err);
				}
			
				// Get an array of Shape Objects to be placed on top of the artwork (if any), like hole punch marks, or maybe an envelope window
				$shapesContainerObj = $thisProjectPDFobjectsArr[$i]->getLocalShapeContainer();
				$shapesObjectsArr = $shapesContainerObj->getShapeObjectsArr($thisProjectPDFobjectsArr[$i]->getPdfSideNumber());
			
				
				$projectPDF = PdfDoc::GetArtworkPDF($dbCmd, $i, $artworkObjNoVariables, 
						$thisProjectPDFobjectsArr[$i]->getBleedtype(), 
						$thisProjectPDFobjectsArr[$i]->getBleedunits(), 
						$thisProjectPDFobjectsArr[$i]->getRotatecanvas(true), 
						$thisProjectPDFobjectsArr[$i]->getShowBleedSafe(), 
						$thisProjectPDFobjectsArr[$i]->getShowCutLine(), 
						$shapesObjectsArr);
	
	
	
	
				// Put the Original PDF Binary data into a PDF lib virtual file
				$PFV_FileName = "/MAILED_" . $thisProjectID . "_" . strval($i);
				PDF_create_pvf( $pdf, $PFV_FileName, $projectPDF, "");
	
				// Put the Original PDF file into a PDI object that can be used for caching.
				$ProjectSidePDFpdi = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
				$projectPDIBackgroundPDFarr[$thisProjectID][$i] = $ProjectSidePDFpdi;
	
				PDF_delete_pvf($pdf, $PFV_FileName);
				
				// Save the Page Object into Memory for this Artwork/Side combination.
				$projectPDIpageBackgroundPDFarr[$thisProjectID][$i] = PDF_open_pdi_page($pdf, $ProjectSidePDFpdi, 1, "");
	
			}
			
			
			if($flushProgressOutput == "none"){
				// don't do anything
			}
			else if($flushProgressOutput == "space"){
				print " ";
				Constants::FlushBufferOutput();
			}
			else if($flushProgressOutput == "javascript"){
				print "\n<script>";
				print "DataPercent('Generating Backgrounds: ', " . ceil( $projectCounter / sizeof($projectOrderedIDarr) * 100 ). ");";
				print "</script>\n";
				Constants::FlushBufferOutput();
			}
			else if($flushProgressOutput == "xml_progress"){
				print "<percent>" . ceil( $projectCounter / sizeof($projectOrderedIDarr) * 100 ) . "</percent>\n";
				Constants::FlushBufferOutput();
			}
			else{
				throw new Exception("Illegal flushProgressOutput type.");
			}
			
			$projectCounter++;
	
		}
		
	
	
		// find out how many cards will fit on a page so that we can figure how how many times the master sheet should be reproduced
		$totalPages = ceil($totalCardQuantity / $firstPDFobjectsArr[0]->getQuantity());
	
	
		// Get a blank parent sheet with all of the Crop Marks, etc.
		$pdfMasterSheet = PdfDoc::GeneratePDFsheet($dbCmd, $firstPDFobjectsArr, $blankArtworkObj, "#FFCCFF", $PDFlibOptionList, true);
	
	
	
	
		// --------------------------  Build PDF document -----------------------
	
		
	
		// Put the Master PDF (Binary data) into a PDF lib virtual file
		$PFV_BackgroundMasterFileName = "/pfv/pdf/ArtworkFileMaster";
		PDF_create_pvf( $pdf, $PFV_BackgroundMasterFileName, $pdfMasterSheet, "");
	
	
		// Put the Master PDF file into a PDI object that we can extract page(s) from.
		$MasterBackgroundPDIobj = PDF_open_pdi($pdf, $PFV_BackgroundMasterFileName, "", 0);
		
		PDF_delete_pvf($pdf, $PFV_BackgroundMasterFileName);
	
		$totalPagesInMasterFile = PDF_get_pdi_value($pdf, "/Root/Pages/Count", $MasterBackgroundPDIobj, 0, 0);
	
		// Create an array of PDF Page objects.  There should be one array entry for each page
		$PDI_PagesObjArr = array();
		for($i=0; $i < $totalPagesInMasterFile; $i++)
			$PDI_PagesObjArr[$i] = PDF_open_pdi_page($pdf, $MasterBackgroundPDIobj, ($i+1), "");
		
		
		// Take the first Project within the list... If it has a Summary Sheet flag set (from the Paramaters Object)... then we will create a Summary Sheet for the entire Batch
		// The Summary Sheet basically tells how many Projects are in the Batch and the Total number of Sheets printed.
		$batchDescription .= " --- Total Pages in PDF = " . ($totalPages * 2 + 2); // Times by 2 because it is always double-sided... Add 2 for the Summary Sheet.
		PdfDoc::addSummarySheetPossibly($dbCmd, $pdf, $projectOrderedIDarr, $pdf_profile, $batchDescription);
	
	
		$PageNumber = 1;
	
		// Generate a coversheet (if the profile says we should have one)
		if($firstPDFobjectsArr[0]->getDisplayCoverSheet()){
	
			PdfDoc::addCoverSheet($dbCmd, $pdf, $firstPDFobjectsArr, $blankArtworkObj, true, $totalCardQuantity, $PageNumber, $batchDescription, ShippingChoices::NORMAL_PRIORITY, "N", "#FFFF00");
	
			// Adding a coversheet means we need an additional page.
			$PageNumber++;
	
			// Double-sided artworks adds another page number (for the back of it).
			if(sizeof($blankArtworkObj->SideItemsArray) > 1)
				$PageNumber++;
		}
	
	
	
		for($j=0; $j<$totalPages; $j++){
			for($sideNum=0; $sideNum < $totalPagesInMasterFile; $sideNum++){
	
				$pagewidth = PDF_get_pdi_value($pdf, "width", $MasterBackgroundPDIobj, $PDI_PagesObjArr[$sideNum], 0);
				$pageheight = PDF_get_pdi_value($pdf, "height", $MasterBackgroundPDIobj, $PDI_PagesObjArr[$sideNum], 0);
			
				pdf_begin_page($pdf, $pagewidth, $pageheight);
	
	    			PDF_fit_pdi_page($pdf, $PDI_PagesObjArr[$sideNum], 0, 0, "");
	    			
	    			$rowNum = 1;
	    			$colNum = 0;
	    			
	
	    			// Loop through all of the positions on the parent sheet.
	    			// For a proof (with only 1 per page) the loop here will only get executed once.
	    			for($artPosition=1; $artPosition <= $firstPDFobjectsArr[$sideNum]->getQuantity(); $artPosition++){
					
	    				// Every time we loop, figure out what row and column we are on
	    				$colNum++;
	    				if($colNum > $firstPDFobjectsArr[$sideNum]->getColumns()){
	    					$colNum = 1;
	    					$rowNum++;
	    				}
	    				
	    				
	    				// If we are on the backside... then we want to reverse the column positions
	    				// on duplex pages, column 1 on the front side will be matched up to the final colum on the backside
	    				if($sideNum % 2 != 0)
	    					$onTheBackFlag = true;
	    				else
	    					$onTheBackFlag = false;
	    					
	    					
	    					
	    				
	    				// Now tranlate the Artwork position (on the PDF master Grid) to a Line Number... which can therefore be translated into a Project ID.
					// If we have defined a Matrix object... then the variable data line number needs to be translated according to the style of our matrix.
					// We want everything under slot 1 to start at the top of the data file... then move down.  The top of slot 2 will carry on where the bottom of slot 1 finished, etc.
					// This is called a Z-Sort Technique
					$matrixObj = $firstPDFobjectsArr[$sideNum]->getCoverSheetMatrix();
					if($matrixObj){
	
						// Matrix rows count from the top down... PDF generation starts with row 1 at the bottom.
						$matrixRow = $firstPDFobjectsArr[$sideNum]->getRows() + 1 - $rowNum;
	
						// Find out how many Items are in each stack of the Matrix.
						$itemsPerMatrixStack = ceil($totalCardQuantity / $matrixObj->getTotalElements());
	
						// Based upon the Row/Column of the PDF we are generating... find out how that translates to our Slot Position specified within the Matrix.
						$matrixSlotNumberValue = $matrixObj->getMatrixOrderValueAt($matrixRow, $colNum);
	
						// Figure out the corresponding Line number of the Data file depending on the Slot Number and Page Number.
						$variableDataLineNumber = ($matrixSlotNumberValue - 1) * $itemsPerMatrixStack + ($j + 1);
	
					}
					else{
						// Figure out the line number we are in for the variable data file
						// depending on the page number and position we are in for the page.
						$variableDataLineNumber = $j * $firstPDFobjectsArr[$sideNum]->getQuantity() + $artPosition;
					}
	    				
	    	
	
	
	  
	    				// Once the line numbers has hit the total quantity in the project, we are done.
	    				// So Write Blank white squares over areas that are not necessary
	    				// Otherwise, draw the varaible data on top.
					if($variableDataLineNumber <= $totalCardQuantity){
					
	
						// Because our Projects are Intermingled... they the line numbers are all wacky. 
						// Translate our Current Position on the PDF to the current Project ID and its corresponding Variable Data Line number.
						$projectIDonThisLine = $variableDataSortingObj->getProjectIDAtRowNum($variableDataLineNumber);
						$varDataLineNumberInsideProject = $variableDataSortingObj->getDataLineNumberAtRowNum($variableDataLineNumber);
	
	
	
						$thisPdfParameterObj = $pdfParemtersForProjectsObjArr[$projectIDonThisLine][$sideNum];
	
						$bleedunits = $thisPdfParameterObj->getBleedunits();
	
	
						$unitCanvasWidth  = PdfDoc::inchesToPDF($thisPdfParameterObj->getUnitWidth()) + $bleedunits * 2;
						$unitCanvasHeight  = PdfDoc::inchesToPDF($thisPdfParameterObj->getUnitHeight()) + $bleedunits * 2;
	
						// Convert the Content size of the Native Artwork document into PDF coordinates
						$nativeArtworkWidth_PDF = PdfDoc::flashToPDF($artVarsOnlyObjForProjectsArr[$projectIDonThisLine]->SideItemsArray[$sideNum]->contentwidth);
						$nativeArtworkHeight_PDF = PdfDoc::flashToPDF($artVarsOnlyObjForProjectsArr[$projectIDonThisLine]->SideItemsArray[$sideNum]->contentheight);
	
						$artworkCanvasWidthPicas = $nativeArtworkWidth_PDF + $bleedunits * 2;
						$artworkCanvasHeightPicas = $nativeArtworkHeight_PDF + $bleedunits * 2;
	
	
	
						// If we have chosen to Force the aspect ratio, then make sure that the PDF fills in the Specified Width/Height of the PDF profile, even if it means distorting.
						if($thisPdfParameterObj->getForceAspectRatio()){
							$artwork_scale_x = $unitCanvasWidth / $artworkCanvasWidthPicas;
							$artwork_scale_y = $unitCanvasHeight / $artworkCanvasHeightPicas;	
						}
						else{
							$artwork_scale_x = 1;
							$artwork_scale_y = 1;
						}
	
	
	
	
						pdf_save($pdf);
						PdfDoc::_translatePDFpointerToContentPosition($pdf, $pdfParemtersForProjectsObjArr[$projectIDonThisLine][$sideNum], $rowNum, $colNum, $onTheBackFlag);
		
						
						pdf_save($pdf);
						pdf_translate($pdf, -($bleedunits), -($bleedunits));
	
						// Paste the Background PDF on top of our master sheet.
						PDF_fit_pdi_page($pdf, $projectPDIpageBackgroundPDFarr[$projectIDonThisLine][$sideNum], 0, 0, "");
	
						// Restore from Bleed Unit Transposition
						pdf_restore($pdf);
	
	
	
	
	
						// Save before rotating.
						pdf_save($pdf);
						
						PdfDoc::_translatePointerForRotation($pdf, $pdfParemtersForProjectsObjArr[$projectIDonThisLine][$sideNum]);
	
	
						// This will draw the Variable Images on top of the PDF>.. for the given LineNumber / SideNumber.
						if($projectHasVariableImagesArr[$projectIDonThisLine]){
						
							// If we have distorted the aspect ratio.. then set the distortion before drawing the Variable Images on top.
							pdf_save($pdf);
							PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);
						
							PdfDoc::putVariableImagesOnPDF($pdf, $artObjForProjectsArr[$projectIDonThisLine], $variableDataObjArr[$projectIDonThisLine], $variableImageObjectsForUserArr[$userIDsOfProjectsArr[$projectIDonThisLine]], $artworkMappingObjArr[$projectIDonThisLine], $sideNum, $varDataLineNumberInsideProject, $nativeArtworkWidth_PDF, $nativeArtworkHeight_PDF);
						
							pdf_restore($pdf);
						}
			
			
						// Every so many pages we will output progress (if specified to do so).
						$progressOutputCounter = 4;
	
						// Maybe flush out the progress (for large files that take a while to process)
						if(($rowNum == 1 && $colNum == 1) && ($j % $progressOutputCounter == 0)){
	
							if($flushProgressOutput == "none"){
								// don't do anything
							}
							else if($flushProgressOutput == "space"){
								print " ";
								Constants::FlushBufferOutput();
							}
							else if($flushProgressOutput == "javascript"){
								print "\n<script>";
								print "DataPercent('Merging Data: ', " . ceil( $j / $totalPages * 100 ) . ");";
								print "</script>\n";
								Constants::FlushBufferOutput();
							}
							else if($flushProgressOutput == "xml_progress"){
								print "<percent>" . ceil( $j / $totalPages * 100 ) . "</percent>\n";
								Constants::FlushBufferOutput();
							}
							else{
								throw new Exception("Illegal flushProgressOutput type.");
							}
						}
	
	
						// Get the Artwork XML for the given line number (with all variables substituted)
						$artVariableDataXML = $variableProcessObjArr[$projectIDonThisLine]->getArtworkByLineNumber($varDataLineNumberInsideProject);
						
						// Substitue the Special Variable {Sequence}  with the current Row number.... although it may not exist.
						$artVariableDataXML = preg_replace("/{sequence}/i", $variableDataLineNumber, $artVariableDataXML);
	
						$artVarDataObj = new ArtworkInformation($artVariableDataXML);
	
						// If we have distorted the aspect ratio.. then set the distortion before drawing the text layers.
						pdf_save($pdf);
						PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);
	
						PdfDoc::_drawTextBlocksOnPDFside($pdf, $artVarDataObj, $sideNum, $artworkMappingObjArr[$projectIDonThisLine]);
						
						// Restore from the scaling
						pdf_restore($pdf);
						
						// Restore from Rotation
						pdf_restore($pdf);
	
						// Restore from the coordinates translation
						pdf_restore($pdf);
	
					}
			
	    			}
	    			
	    			PdfDoc::_showPageNumberOnPage($pdf, $PageNumber, ShippingChoices::NORMAL_PRIORITY, "N");
	    			$PageNumber++;
				
				pdf_end_page($pdf);
			}
		}
	
	
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowProgress('<b>Status:</b> Downloading PDF Document.');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}	
	
	
		// Now close all of the PDI page objects
		for($i=0; $i < $totalPagesInMasterFile; $i++)
			PDF_close_pdi_page($pdf, $PDI_PagesObjArr[$i]);
	
		PDF_close_pdi($pdf, $MasterBackgroundPDIobj);
		
	
		// Get rid of all of the PDI And Page objects (for individual Artworks).	
		foreach($projectPDIpageBackgroundPDFarr as $projectPDIpageHash){
			foreach($projectPDIpageHash as $thisPDIpageObject)
				PDF_close_pdi_page($pdf, $thisPDIpageObject);
		}
		foreach($projectPDIBackgroundPDFarr as $projectPDIhash){
			foreach($projectPDIhash as $thisPDIobject)
				PDF_close_pdi($pdf, $thisPDIobject);
		}
	
	
		PDF_end_document($pdf, "");
	
	
		$data = pdf_get_buffer($pdf);
			
	
		return $data;
	}
	
	
	
	static function setParametersOnNewPDF(&$pdf){
	
		
		if(!Constants::GetDevelopmentServer())
			pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());
		

		pdf_set_info($pdf, "Title", "Artwork");
		
	
		PDF_set_parameter($pdf, "SearchPath", Constants::GetFontBase());
		PDF_set_parameter($pdf, "SearchPath", Constants::GetTempDirectory());
	
	}
	
	
	
	
	
	// Returns Variable Data pdf in binary
	// $flushProgressOutput should be a string "none", "space", "javascript"
	// If $flushProgressOutput is anything other than "none", then the output with flush something to the browser every 20 rows
	// This can be useful to keep the browser from timing out (using a "space" character)... or with "javascript" can report the progress back to Javascript and used with a DHTML component.
	static function generateVariableDataPDF(DbCmd $dbCmd, $projectID, $viewType, $showReorderCard, &$parameterObjArr, $ColorIndication, $PDFlibOptionList, $flushProgressOutput){
	
	
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowProgress('<b>Status:</b> Loading Artwork.');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}	
	
	
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectID);
	
		if(!$projectObj->isVariableData())
			throw new Exception("Project: $projectID is not a variable data project in the function PdfDoc::generateVariableDataPDF");
		
		if($showReorderCard && $viewType != "ordered")
			throw new Exception("You can only show the re-order card on a Variable Data Project if the ViewType is ordered.");
		
	
		// Every 30 Lines of data we will output progress (if specified to do so).
		$progressOutputCounter = 30;
	
		
		$artworkXML = $projectObj->getArtworkFile();
		
		
		// Look for the special Variable "{Sequence}" within the Artwork file.
		// If we find it then we know we should try and substitue it with the corresponding row number from the data file
		// Set a flag here increase performance.... otherwise we would try and substitue Sequence variables on every row of a very large file even though it doesn't exist within the artwork.
		if(preg_match("/{sequence}/i", $artworkXML))
			$specialVariablesFlag = true;
		else
			$specialVariablesFlag = false;
		
		
		$artworkMappingsObj = new ArtworkVarsMapping($artworkXML, $projectObj->getVariableDataArtworkConfig());
		
		$artWithoutVariableLayersObj = new ArtworkInformation($artworkXML);
		$artWithOnlyVariableLayersObj = new ArtworkInformation($artworkXML);
		
		VariableDataProcess::removeVariableLayersFromArtwork($artWithoutVariableLayersObj);
		VariableDataProcess::removeNonVariableLayersFromArtwork($artWithOnlyVariableLayersObj);
		
	
	
		
		if($projectObj->hasVariableImages()){
			
			// The non-variable-images layers will get removed inside of the function call to generate them on the PDF>
			$artWithOnlyVariableImagesObj = new ArtworkInformation($artworkXML);
			
			// Get a Variable Image Object based upon the project Type
			$variableImagesObj = VariableImages::getVariableImageObjectFromProject($dbCmd, $projectObj);
		}
	
	
		// Get the master PDF sheet Without any Artwork Variables substituted on them
		$pdfMasterSheet = PdfDoc::GeneratePDFsheet($dbCmd, $parameterObjArr, $artWithoutVariableLayersObj, $ColorIndication, $PDFlibOptionList, true);
	
	
		
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowProgress('<b>Status:</b> Loading Data File... Preparing to merge data.');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}	
		
		$variableDataObj = new VariableData();
		$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile());
		
		$variableDataProcessObj = new VariableDataProcess($artWithOnlyVariableLayersObj->GetXMLdoc(), $artworkMappingsObj, $variableDataObj);
		
	
	
		// Figure how how many Cards will be printed
		// If we are showing a Re-order Card, then we need to add 1 spot.
		$totalCardQuantity = $projectObj->getQuantity();
		if($showReorderCard)
			$totalCardQuantity++;
	
	
		// find out how many cards will fit on a page so that we can figure how how many times the master sheet should be reproduced
		$totalPages = ceil($totalCardQuantity / $parameterObjArr[0]->getQuantity());
	
	
		// --------------------------  Build PDF document -----------------------
	
		// Create a new/blank PDF doc that we will paste the master pages and write the variable data onto 
		$pdf = pdf_new();
		
		// Sets the license key, author, font paths, etc.
		PdfDoc::setParametersOnNewPDF($pdf);
		
		PDF_begin_document($pdf, "", $PDFlibOptionList);
	
	
		// Put the Master PDF (Binary data) into a PDF lib virtual file
		$PFV_BackgroundMasterFileName = "/pfv/pdf/ArtworkFileMaster";
		PDF_create_pvf( $pdf, $PFV_BackgroundMasterFileName, $pdfMasterSheet, "");
	
	
		// Put the Master PDF file into a PDI object that we can extract page(s) from.
		$OriginalPDF = PDF_open_pdi($pdf, $PFV_BackgroundMasterFileName, "", 0);
		
		PDF_delete_pvf($pdf, $PFV_BackgroundMasterFileName);
	
		$totalPagesInMasterFile = PDF_get_pdi_value($pdf, "/Root/Pages/Count", $OriginalPDF, 0, 0);
	
		// Create an array of PDF Page objects.  There should be one array entry for each page
		$PDI_PagesObjArr = array();
		for($i=0; $i < $totalPagesInMasterFile; $i++)
			$PDI_PagesObjArr[$i] = PDF_open_pdi_page($pdf, $OriginalPDF, ($i+1), "");
		
	
	
		for($j=0; $j<$totalPages; $j++){
			for($sideNum=0; $sideNum < $totalPagesInMasterFile; $sideNum++){
	
				$pagewidth = PDF_get_pdi_value($pdf, "width", $OriginalPDF, $PDI_PagesObjArr[$sideNum], 0);
				$pageheight = PDF_get_pdi_value($pdf, "height", $OriginalPDF, $PDI_PagesObjArr[$sideNum], 0);
			
				pdf_begin_page($pdf, $pagewidth, $pageheight);
	
	    			PDF_fit_pdi_page($pdf, $PDI_PagesObjArr[$sideNum], 0, 0, "");
	    			
	    			$rowNum = 1;
	    			$colNum = 0;
	    			
	    			$bleedunits = $parameterObjArr[$sideNum]->getBleedunits();
	    			
				$unitCanvasWidth  = PdfDoc::inchesToPDF($parameterObjArr[$sideNum]->getUnitWidth()) + $bleedunits * 2;
				$unitCanvasHeight  = PdfDoc::inchesToPDF($parameterObjArr[$sideNum]->getUnitHeight()) + $bleedunits * 2;
	
				// Convert the Content size of the Native Artwork document into PDF coordinates
				$nativeArtworkWidth_PDF = PdfDoc::flashToPDF($artWithOnlyVariableLayersObj->SideItemsArray[$sideNum]->contentwidth);
				$nativeArtworkHeight_PDF = PdfDoc::flashToPDF($artWithOnlyVariableLayersObj->SideItemsArray[$sideNum]->contentheight);
	
				$artworkCanvasWidthPicas = $nativeArtworkWidth_PDF + $bleedunits * 2;
				$artworkCanvasHeightPicas = $nativeArtworkHeight_PDF + $bleedunits * 2;
				
	
	
				// If we have chosen to Force the aspect ratio, then make sure that the PDF fills in the Specified Width/Height of the PDF profile, even if it means distorting.
				if($parameterObjArr[$sideNum]->getForceAspectRatio()){
					$artwork_scale_x = $unitCanvasWidth / $artworkCanvasWidthPicas;
					$artwork_scale_y = $unitCanvasHeight / $artworkCanvasHeightPicas;	
				}
				else{
					$artwork_scale_x = 1;
					$artwork_scale_y = 1;
				}
	
	
	    			// Loop through all of the positions on the parent sheet.
	    			// For a proof (with only 1 per page) the loop here will only get executed once.
	    			for($artPosition=1; $artPosition <= $parameterObjArr[$sideNum]->getQuantity(); $artPosition++){
					
	    				// Every time we loop, figure out what row and column we are on
	    				$colNum++;
	    				if($colNum > $parameterObjArr[$sideNum]->getColumns()){
	    					$colNum = 1;
	    					$rowNum++;
	    				}
	    				
	    				
	    				// If we are on the backside... then we want to reverse the column positions
	    				// on duplex pages, column 1 on the front side will be matched up to the final colum on the backside
	    				if($sideNum % 2 != 0)
	    					$sideBackFlag = true;
	    				else
	    					$sideBackFlag = false;
					
	
	
	   				pdf_save($pdf);
	   				
	   				PdfDoc::_translatePDFpointerToContentPosition($pdf, $parameterObjArr[$sideNum], $rowNum, $colNum, $sideBackFlag);
	   
					PdfDoc::_translatePointerForRotation($pdf, $parameterObjArr[$sideNum]);
	
	
	    				// Don't draw anything in the re-order Slot
	    				if($showReorderCard && $j == 0 && $artPosition == 1 ){
	    				
						pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 1);
						pdf_rect($pdf, -($bleedunits), -($bleedunits), $unitCanvasWidth, $unitCanvasHeight );
						pdf_fill($pdf);
	
						// Restore from  Coordinate Translation and Scaling
						// Because we will execute the "continue" statement below we need to execute these restores ahead of time.
	    					pdf_restore($pdf);
	    				
	    					continue;
	    				}
	    			
	
			
				
					// If we have defined a Matrix object... then the variable data line number needs to be translated according to the style of our matrix.
					// We want everything under slot 1 to start at the top of the data file... then move down.  The top of slot 2 will carry on where the bottom of slot 1 finished, etc.
					// This is called a Z-Sort Technique
					$matrixObj = $parameterObjArr[$sideNum]->getCoverSheetMatrix();
					if($matrixObj){
	
						// Matrix rows count from the top down... PDF generation starts with row 1 at the bottom.
						$matrixRow = $parameterObjArr[$sideNum]->getRows() + 1 - $rowNum;
	
						// If there is a re-order card then figure out what value of the Matrix it is covering.
						// It will be the last row of the Matix in the first column
						if($showReorderCard){
							$lastRowOfMatrix = $matrixObj->getNumberRows();
							$slotNumberOfReorderCard = $matrixObj->getMatrixOrderValueAt($lastRowOfMatrix, 1);
						}
						else{
							// Since there is not a re-order card... then set this value out of range so that it won't interfere
							$slotNumberOfReorderCard = $matrixObj->getTotalElements() + 1;
						}
	
						// Find out how many Items are in each stack of the Matrix.
						$itemsPerMatrixStack = ceil($totalCardQuantity / $matrixObj->getTotalElements());
	
						// Based upon the Row/Column of the PDF we are generating... find out how that translates to our Slot Position specified within the Matrix.
						$matrixSlotNumberValue = $matrixObj->getMatrixOrderValueAt($matrixRow, $colNum);
	
						// Figure out the corresponding Line number of the Data file depending on the Slot Number and Page Number.
						$variableDataLineNumber = ($matrixSlotNumberValue - 1) * $itemsPerMatrixStack + ($j + 1);
	
						// The position of the Data file will need to be decremented if we are on a Slot number which comes after the Slot number filling the re-order slot.
						if($matrixSlotNumberValue >= $slotNumberOfReorderCard)
							$variableDataLineNumber--;
	
					}
					else{
							// Figure out the line number we are in for the variable data file
							// depending on the page number and position we are in for the page.
							$variableDataLineNumber = $j * $parameterObjArr[$sideNum]->getQuantity() + $artPosition;
	
							// If a re-order card is being put on the first sheet
							// Then the line number of the variable data File will always be one behind the position it is being drawn on
							if($showReorderCard)
								$variableDataLineNumber--;
	
					}
	
	
	
	  
	    				// Once the line numbers has hit the total quantity in the project, we are done.
	    				// So Write Blank white squares over areas that are not necessary
	    				// Otherwise, draw the varaible data on top.
					if($variableDataLineNumber > $projectObj->getQuantity()){
					
						pdf_save($pdf);
						pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 1);
						pdf_rect($pdf, -($bleedunits), -($bleedunits), $unitCanvasWidth, $unitCanvasHeight );
						pdf_fill($pdf);
						pdf_restore($pdf);
	
					}
					else{
	
						// This will draw the Variable Images on top of the PDF>.. for the given LineNumber / SideNumber.
						if($projectObj->hasVariableImages()){
							
							// If we have distorted the aspect ratio.. then set the distortion before drawing the Variable Images on top.
							pdf_save($pdf);
							PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);
							
							PdfDoc::putVariableImagesOnPDF($pdf, $artWithOnlyVariableImagesObj, $variableDataObj, $variableImagesObj, $artworkMappingsObj, $sideNum, $variableDataLineNumber, $nativeArtworkWidth_PDF, $nativeArtworkHeight_PDF);
							
							pdf_restore($pdf);
						}
	
	
	
						// Maybe flush out the progress (for large files that take a while to process)
						if($sideNum == 0 && ($variableDataLineNumber % $progressOutputCounter == 0)){
	
							if($flushProgressOutput == "none"){
								// don't do anything
							}
							else if($flushProgressOutput == "space"){
								print " ";
								Constants::FlushBufferOutput();
							}
							else if($flushProgressOutput == "javascript"){
								print "\n<script>";
								print "DataPercent('Merging Data: ', " . ceil( $variableDataLineNumber / $totalCardQuantity * 100 ). ");";
								print "</script>\n";
								Constants::FlushBufferOutput();
							}
							else{
								throw new Exception("Illegal flushProgressOutput type.");
							}
						}
	
	
						// Get the Artwork XML for the given line number (with all variables substituted)
						$artVariableDataXML = $variableDataProcessObj->getArtworkByLineNumber($variableDataLineNumber);
						
						
						// For very large jobs I have had to run them manually.  This allows me to start off with a manual sequence number.
						$offsetValue = 0;
						$passiveAuthObj = Authenticate::getPassiveAuthObject();
						if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->GetUserID() == 2){
							$offsetValue = 0;
						}
						
						// Substitue the Special Variable {Sequence with the current Row number.}
						if($specialVariablesFlag)
							$artVariableDataXML = preg_replace("/{sequence}/i", ($variableDataLineNumber + $offsetValue), $artVariableDataXML);
	
	
						$artVariableDataObj = new ArtworkInformation($artVariableDataXML);
							
						
						// If we have distorted the aspect ratio.. then set the distortion before drawing the text layers.
						pdf_save($pdf);
						PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);
						
						PdfDoc::_drawTextBlocksOnPDFside($pdf, $artVariableDataObj, $sideNum, $artworkMappingsObj);
						
						
						pdf_restore($pdf);
	
					}
			
					
					// Restore from the coordinates translation
					pdf_restore($pdf);
	
	    			}
				
				pdf_end_page($pdf);
			}
		}
	
	
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowProgress('<b>Status:</b> Downloading PDF Document.');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}	
	
	
		// Now close all of the PDI page objects
		for($i=0; $i < $totalPagesInMasterFile; $i++)
			PDF_close_pdi_page($pdf, $PDI_PagesObjArr[$i]);
	
		PDF_close_pdi($pdf, $OriginalPDF);
		
		PDF_end_document($pdf, "");
	
		$data = pdf_get_buffer($pdf);
	
		return $data;
	
	}
	
	
	// Make sure that the cursor position already translated (on the PDF object) to the lower-left corner of the content area (on the cut line).
	// The ContentWidth_PDF/Height is the size of the individual artwork that we are creating... not the parent sheet. 
	static function putVariableImagesOnPDF(&$pdf, &$artworkObj, &$variableDataObj, &$variableImagesObj, &$artworkMappingsObj, $sideNum, $variableDataLineNumber, $artworkWidthPicas, $artworkHeightPicas){
	
	
	
	
		// Create an artwork Object with only Variable Image layers on it.
		// Serialize/unserialize to destroy any references on the original artwork.
		$artWithOnlyVariableImagesObj = new ArtworkInformation($artworkObj->GetXMLdoc());
		VariableDataProcess::removeAnyLayerNotForVariableImages($artWithOnlyVariableImagesObj);
	
		foreach($artWithOnlyVariableImagesObj->SideItemsArray[$sideNum]->layers as $thisLayerObj){
	
			$artDPI = $artWithOnlyVariableImagesObj->SideItemsArray[$sideNum]->dpi;
			$imageScale = 72/$artDPI;
	
			$textStringFromLayer = $thisLayerObj->LayerDetailsObj->message;
	
			$variableImageName = VariableDataProcess::getVariableNameOfVariableImage($textStringFromLayer);
	
			$fieldPositionVarImage = $artworkMappingsObj->getFieldPosition($variableImageName);
	
			$customerImageName = $variableDataObj->getVariableElementByLineAndColumnNo(($variableDataLineNumber - 1), ($fieldPositionVarImage - 1));
	
			$variableImageID = $variableImagesObj->getImageIDbyCustomerFileName($customerImageName);
	
			$variableImageHash = $variableImagesObj->getImageByID($variableImageID);
	
	
			// Corrdinates in PDF are 1/72 inch.  in Flash they are 96 dpi
			$X_coordinate = PdfDoc::flashToPDF($thisLayerObj->x_coordinate);
			$Y_coordinate = PdfDoc::flashToPDF($thisLayerObj->y_coordinate);
	
			// Translate the coordinates.  Flash registration point is in the middle.  PDF registration point is at the bottom left corner.
			$X_coordinate = $X_coordinate + $artworkWidthPicas/2;
			$Y_coordinate = $artworkHeightPicas - ($Y_coordinate  + $artworkHeightPicas/2);
	
			// Put the binary Image data on disk
			$tmpfnameForImage = tempnam (Constants::GetTempDirectory(), "VARIM");
			$fp = fopen($tmpfnameForImage, "w");
			fwrite($fp, $variableImageHash["ImageData"]);
			fclose($fp);
			
			// Get the Height and Width in pixels of the image
			$nativeImageDim = ImageLib::GetDimensionsFromImageFile($tmpfnameForImage);
	
			// Now find out if the image needs to be resized based upon attributes within the Variable Image Text Layer
			$attributesImageDim = VariableImages::getHeightWidthForVariableImage($nativeImageDim["Width"], $nativeImageDim["Height"], $artDPI, $textStringFromLayer);
	
			if($attributesImageDim["Width"] != $nativeImageDim["Width"] || $attributesImageDim["Height"] != $nativeImageDim["Height"]){
				$resizeCommand = Constants::GetUpperLimitsShellCommand(80, 20) . Constants::GetPathToImageMagick() . "mogrify -quality 70 -geometry " . $attributesImageDim["Width"] . "x" . $attributesImageDim["Height"] . "! " . $tmpfnameForImage;
				system($resizeCommand);
			}
	
	
			// Now Read the image off of disk
			$fd = fopen ($tmpfnameForImage, "r");
			$imageBinaryData = fread ($fd, filesize ($tmpfnameForImage));
			fclose ($fd);
	
			unlink($tmpfnameForImage);
	
			// Load the Background Image data into a PDF lib virtual file
			PDF_create_pvf( $pdf, "/pfv/images/variableImage", $imageBinaryData, "");
	
			// Get an image resource for the PDF doc through the virtual file
			if(preg_match("/jpeg/i", $variableImageHash["ContentType"]))
				$pdfVariableImage = PDF_load_image ($pdf, "jpeg", "/pfv/images/variableImage", "");
			else if(preg_match("/png/i", $variableImageHash["ContentType"]))
				$pdfVariableImage = PDF_load_image ($pdf, "png", "/pfv/images/variableImage", "");
			else
				PdfDoc::PDFErrorMessage("There was an error creating the PDF document.", "The Variable Image Format could not be imported.");
	
			// Now we can release our virtual file since we already have a PDF image resource
			PDF_delete_pvf($pdf, "/pfv/images/variableImage");
	
			if(!$pdfVariableImage)
				PdfDoc::PDFErrorMessage("There was an error creating the PDF document.", "The Variable Image could not be imported.");
	
	
			// There could be attributes within the Text Layer that tell us how to align the image in relation to the coordinate of the text layer
			$VariableImageAlignment = VariableImages::getAttributeOfVariableImage($textStringFromLayer, "Alignment");
			if($VariableImageAlignment == "CENTER")
				$X_coordinate -= $attributesImageDim["Width"] * $imageScale / 2;
			else if($VariableImageAlignment == "RIGHT")
				$X_coordinate -= $attributesImageDim["Width"] * $imageScale;
	
			$VariableImageVertAlignment = VariableImages::getAttributeOfVariableImage($textStringFromLayer, "VerticalAlign");
			if($VariableImageVertAlignment == "MIDDLE")
				$Y_coordinate -= $attributesImageDim["Height"] * $imageScale / 2;
			else if($VariableImageVertAlignment == "TOP")
				$Y_coordinate -= $attributesImageDim["Height"] * $imageScale;
	
	
			// Paste the Variable Image on top
			pdf_save($pdf);
			pdf_translate($pdf, $X_coordinate, $Y_coordinate);
			pdf_place_image( $pdf, $pdfVariableImage, 0, 0, $imageScale);
			pdf_restore($pdf);
			pdf_close_image($pdf, $pdfVariableImage);
		}
	
	}
	
	
	
	// It returns the PDF data in binary format 
	// This is meant to take artwork object and replicate is in accordance with the details in the parameters objects 
	// Param 2 in an array of PDF Parameters Objects
	// Param 3 an Artwork Object
	// Param 4 is a Hext color code that is written next to any order number...  It is usefull for separting order in a giant stack.  EX:  "FF3333"
	// Param 5 Refer to the PDFlib manual for a list of options that can be applied to the PDF_BEGIN_DOCUMENT
	static function GeneratePDFsheet(DbCmd $dbCmd, array $parameterObjArr, &$ArtworkInfoObj, $ColorIndication, $PDFlibOptionList, $isVariableDataFlag){

		
		// Create the PDF doc with PHP`s extension PDFlib  
		$pdf = pdf_new();
		
		// Sets the license key, author, font paths, etc.
		PdfDoc::setParametersOnNewPDF($pdf);
			
		PDF_begin_document($pdf, "", $PDFlibOptionList);
		
	
		// Get RGB values into a neatly formated Object .. The second parameter tells us to get the values in Dec format (not hex)
		$ColorCodeObj = ColorLib::getRgbValues($ColorIndication, false);
		
		$sideCntr = 0;
		foreach($parameterObjArr as $parameterObj){

			if(get_class($parameterObj) != "PDFparameters")
				throw new Exception("Error in GeneratePDFsheet. The parameterObjArr must contain objects of PDFparameters");
		
			// If we are on the backside... then we want to reverse the column positions
			// on duplex pages, column 1 on the front side will be matched up to the final colum on the backside
			if($sideCntr % 2 != 0)
				$sideBackFlag = true;
			else
				$sideBackFlag = false;
			
			$sideCntr++;
	
	
			// When we set this flag to false the program will stop generating images
			// We want to do this after we have generated the desired quantity.
			$keenGenaratingImages = true;
			$imageGenerationCounter = 0;
	
	
			// Get the parameter from the Object and store in scalar variables 
			$bleedunits = $parameterObj->getBleedunits();
			$originalBleedUnits = $parameterObj->getBleedunits();
			
	
			if(!isset($ArtworkInfoObj->SideItemsArray[$parameterObj->getPdfSideNumber()]))
				continue;
	
			// Make a new page
			pdf_begin_page($pdf, PdfDoc::inchesToPDF($parameterObj->getPagewidth()), PdfDoc::inchesToPDF($parameterObj->getPageheight()));
			pdf_add_bookmark($pdf, $parameterObj->getSideDescription(), 0, 0);
	
			// Define the Artwork Ojbect for the side that we will be generating.
			$SideObj = $ArtworkInfoObj->SideItemsArray[$parameterObj->getPdfSideNumber()];
	
	
			// Figure out how big to make the PDF document based up on our artwork file and the size of our bleed units.
			$picasCanvasWidth = PdfDoc::inchesToPDF($parameterObj->getUnitWidth()) + $originalBleedUnits * 2;
			$picasCanvasHeight = PdfDoc::inchesToPDF($parameterObj->getUnitHeight()) + $originalBleedUnits * 2;
			
			
			// Get the native dimensions in the Artwork... normally it should be matching that in the PDF profile
			$artworkPicasWidth = PdfDoc::flashToPDF($SideObj->contentwidth) + $originalBleedUnits * 2;
			$artworkPicasHeight = PdfDoc::flashToPDF($SideObj->contentheight) + $originalBleedUnits * 2;
			
	
			if($parameterObj->getRotatecanvas(true) == 90 || $parameterObj->getRotatecanvas(true) == 270){
				$tempWidth = $picasCanvasWidth;
				$picasCanvasWidth = $picasCanvasHeight;
				$picasCanvasHeight = $tempWidth;
				
				$tempWidth = $artworkPicasWidth;
				$artworkPicasWidth = $artworkPicasHeight;
				$artworkPicasHeight = $tempWidth;
			}
	
			// Get an array of Shape Objects to be placed on top of the artwork (if any), like hole punch marks, or maybe an envelope window
			$shapesContainerObj = $parameterObj->getLocalShapeContainer();
			$shapesObjectsArr = $shapesContainerObj->getShapeObjectsArr($parameterObj->getPdfSideNumber());
			
			
			// Get an individual PDF document for the artwork
			$PDFsigngleArtworkDoc = PdfDoc::GetArtworkPDF($dbCmd, $parameterObj->getPdfSideNumber(), $ArtworkInfoObj, $parameterObj->getBleedtype(), $bleedunits, $parameterObj->getRotatecanvas(true), $parameterObj->getShowBleedSafe(), $parameterObj->getShowCutLine(), $shapesObjectsArr);
	
	
			// Selecting a "V"oid bleed type will crop off the image at the "Safe Line".
			// Or you could consider it a negative bleed.
			if($parameterObj->getBleedtype() == "V")
				$bleedunits = -($bleedunits);
	
	
			// Put the PDF data into a PDF lib virtual file
			$PFV_FileName = "/pfv/pdf/ArtworkFile" . $parameterObj->getPdfSideNumber();
			PDF_create_pvf( $pdf, $PFV_FileName, $PDFsigngleArtworkDoc, "");
	
			// Put the PDF file into a PDI object that can be pasted throughout the document.
			$PDI_completeDocumentObj = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
			$PDI_PageObj = PDF_open_pdi_page($pdf, $PDI_completeDocumentObj, 1, "");
	
	
	
			// ----  Paste all of the mini  single Artwork PDF documents across their rows and columns -----
	
			for($k=0; $k < $parameterObj->getColumns() && $keenGenaratingImages; $k++){
	
	
				// Draw all of the actual Images on the PDF document
				for($z=0; $z < $parameterObj->getRows() && $keenGenaratingImages == true; $z++){
	
					// the following function call will Translate the coordinates of PDF lib to where we want 
					// begin drawing this copy, so make sure to Save before we do that
					pdf_save($pdf);
					
	
					// Translate to the content position... no need to rotate because the PDF unit Background was already rotated during generation.
					PdfDoc::_translatePDFpointerToContentPosition($pdf, $parameterObj, ($z+1), ($k+1), $sideBackFlag);
	
	
					pdf_save($pdf);
					pdf_translate($pdf, -($bleedunits), -($bleedunits));
	
	
					// Paste the Mini PDF document onto the larger one.
					// If we have chosen to Force the aspect ratio, then make sure that the PDF fills in the Specified Width/Height of the PDF profile, even if it means distorting.
					if($parameterObj->getForceAspectRatio()){
	
						$artwork_scale_x = $picasCanvasWidth / $artworkPicasWidth;
						$artwork_scale_y = $picasCanvasHeight / $artworkPicasHeight;
						
						pdf_save($pdf);
						PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);
						
						PDF_fit_pdi_page($pdf, $PDI_completeDocumentObj, 0, 0, "");
						
						pdf_restore($pdf);
					}
					else{
					
						PDF_fit_pdi_page($pdf, $PDI_completeDocumentObj, 0, 0, "");
					}
					
					
					// Restore from Bleed translation
					pdf_restore($pdf);
	
					if($parameterObj->getShowCropMarks()){
						$cropMarkCheckArr = PdfDoc::_checkForCropMarkEdges($parameterObj, $z, $k, $sideBackFlag);
						PdfDoc::_drawCropMarks($pdf, $picasCanvasWidth, $picasCanvasHeight, $originalBleedUnits, $cropMarkCheckArr["LeftEdge"], $cropMarkCheckArr["RightEdge"], $cropMarkCheckArr["TopEdge"], $cropMarkCheckArr["BottomEdge"]);
					}
					
					if($parameterObj->getShowOutsideBorder()){
						PdfDoc::_drawOutlineAroundArt($pdf, $originalBleedUnits, $picasCanvasWidth, $picasCanvasHeight);
					}
					
	
					// Restore from Coordinate translation
					pdf_restore($pdf);
	
					$imageGenerationCounter++;
	
					if($imageGenerationCounter == $parameterObj->getQuantity())
						$keenGenaratingImages = false;
					
				}
			}
	
			
			PDF_delete_pvf($pdf, $PFV_FileName);
			PDF_close_pdi_page($pdf, $PDI_PageObj);
			PDF_close_pdi($pdf, $PDI_completeDocumentObj);
	
		
			// If there are any Global Shapes... put them on the very top of the PDF.
			$shapesContainerObjGlobal = $parameterObj->getGlobalShapeContainer();
			$shapesGlobalObjectsArr = $shapesContainerObjGlobal->getShapeObjectsArr($parameterObj->getPdfSideNumber());
			PdfDoc::_drawShapesOnPDF($pdf, $shapesGlobalObjectsArr);
	
			// Now Write the order numbers on to the PDF.
			// As well as the color bar that separates the orders from each other.
			PdfDoc::WriteOrderNumberOnPDF($pdf, $parameterObj, $ColorCodeObj, $isVariableDataFlag);
	
			pdf_end_page($pdf);
		}
	
	
		PDF_end_document($pdf, "");
	
		$data = pdf_get_buffer($pdf);
	
		return $data;
	
	}
	
	
	
	
	// It returns the PDF data in binary format 
	// Pass in a GangGroup Object and it will plot the Artworks in each position accordingly.
	// Pass in one ParameterObj for the whole batch.  The Bleed Settings on this Parameters Object is universal for the whole document... but the Bleed Type is special to each projectID.
	// Side Number can either be 0 (for frontside) or 1 (for backside).
	// Param 5 Refer to the PDFlib manual for a list of options that can be applied to the PDF_BEGIN_DOCUMENT
	static function GenerateGangSheet(DbCmd &$dbCmd, PDFparameters &$parameterObj, $sideNumber, $isCoverSheetFlag, GangGrouping &$gangGroupObj, $PDFlibOptionList, $flushProgressOutput, $colorCodeObj){

		if($sideNumber != 0 && $sideNumber != 1)
			throw new Exception("Error in function PdfDoc::GenerateGangSheet. The side number can only be 0 or 1");
	
		if(!in_array($flushProgressOutput, array("javascript", "spaces", "none")))
			throw new Exception("Error in method PdfDoc::GenerateGangSheet.  The flush progress output variable is incorrect.");
	
	
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
		
			$productID = $gangGroupObj->getProductID();
			
			if($isCoverSheetFlag)
				$sheetDescription = "Coversheet";
			else if($sideNumber == 0)
				$sheetDescription = "Front-Side";
			else if($sideNumber == 1)
				$sheetDescription = "Back-Side";
			
			print "\n<script>";
			print "ShowCurrentTask('<b>Status:</b> Generating the " . $sheetDescription . " for " . addslashes(Product::getRootProductName($dbCmd, $productID))  . ".');";
			print "</script>\n";
	
	
			
			Constants::FlushBufferOutput();
		}
		else if($flushProgressOutput == "spaces"){
			print "               ";
			Constants::FlushBufferOutput();
		}
	
	
		// Create the PDF doc with PHP`s extension PDFlib 
		$pdf = pdf_new();
		
		// Sets the license key, author, font paths, etc.
		PdfDoc::setParametersOnNewPDF($pdf);
			
		PDF_begin_document($pdf, "", $PDFlibOptionList);
		
		
		// The Key is the ProjectID for all of these arrays.
		// All of these arrays are organized the same.
		$pdfPdiObjectsArr = array();
		$pdfPageObjectsArr = array();
		$bleedTypesArr = array();
		$canvasRotationsArr = array();
		
		
		// We do this at the very last moment when the PDF is being generated.  
		// No use in going to all of the work we we are just looking for empty slot numbers etc.
		// This will try and keep projects in the same row.
		$gangGroupObj->reOrganizeSequenceForRowColBreaks();
			
		$projectIDarr = $gangGroupObj->getProjectIDarr();
		

		if($isCoverSheetFlag){
			$reorderCardArr =& PdfDoc::_getArrayOfReorderCards($dbCmd, $pdf, $projectIDarr, $parameterObj->getRotatecanvas(true));
		}
		
		$projectCounter = 0;
	
		// Generate the Background PDFs for each one of the Project IDs ahead of time and store them in an array.
		foreach($projectIDarr as $thisProjectID){
		
		
			if($flushProgressOutput == "javascript"){
				print "\n<script>";
				print "DataPercent('Processing: ', " . ceil( $projectCounter / $gangGroupObj->getProjectCount() * 100 ). ");";
				print "</script>\n                                                                                                       
				                                                                                                                                         
				                                                                                                           
				";
				Constants::FlushBufferOutput();
			}
			else if($flushProgressOutput == "spaces"){
				print "                                                                                                       

				                                                                
				";
				Constants::FlushBufferOutput();
			}
		
			// In Preview Mode, we just want to show the Default Product Artwork with some Debugging text for Super PDF Profile layout.
			// The Project ID's are not real on GangGrouping in Preview Mode
			if($gangGroupObj->getPreviewMode()){
				$artworkFile = $gangGroupObj->getPreviewArtworkForProduct();
			}
			else{
				$artworkFile = ArtworkLib::GetArtXMLfile($dbCmd, "ordered", $thisProjectID);				
			}
			

			$artInfoObj = new ArtworkInformation($artworkFile);
	
	
			// Get an array of Shape Objects to be placed on top of the artwork (if any), like hole punch marks, or maybe an envelope window
			$shapesContainerObj = $parameterObj->getLocalShapeContainer();
			$shapesObjectsArr = $shapesContainerObj->getShapeObjectsArr($sideNumber);
	
	
			// Just in case we are trying to put a SingleSided Artwork on a Double-Sided profile... we want to make a the Backside a clone of the front... but wipe off all of the layers.
			if(!isset($artInfoObj->SideItemsArray[$sideNumber])){
	
				$firstSideObj = unserialize(serialize($artInfoObj->SideItemsArray[0]));
				$firstSideObj->layers = array();
	
				$artInfoObj->SideItemsArray[$sideNumber] = $firstSideObj;
			}
	
	
			// Get the bleed Type from the Project Settings.  In case there isn't a backside on this project... just defualt to a "N"atual bleed.
			$projectBleedType = "N";
			$projectRotateCanvas = $parameterObj->getRotatecanvas(true);
	
			// Preview Mode doesn't have real Project ID's
			if(!$gangGroupObj->getPreviewMode()){
				$projectPDFparamObjArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $thisProjectID, $gangGroupObj->getPDFprofileName());
				if(isset($projectPDFparamObjArr[$sideNumber])){
					$projectBleedType = $projectPDFparamObjArr[$sideNumber]->getBleedtype();
					$projectRotateCanvas = $projectPDFparamObjArr[$sideNumber]->getRotatecanvas(true);
				}
			}
	
	
			if($isCoverSheetFlag){
			
				// We need to find out what Saved Project account we will get the "reorder" card from
				$ProjectUserID = Order::GetUserIDFromProject($dbCmd, $thisProjectID);
				$AccountTypeObj = new AccountType($ProjectUserID, $dbCmd);
				$AccountUserID = $AccountTypeObj->GetAccountUserID();
	
				$productID = ProjectBase::getProductIDquick($dbCmd, "ordered", $thisProjectID);
				
				$barCodeXOnCoverSheet = $reorderCardArr[$AccountUserID][$productID]["BarcodeX"];
				$barCodeYOnCoverSheet = $reorderCardArr[$AccountUserID][$productID]["BarcodeY"];
				
				$bleedTypesArr[$thisProjectID] = "N";
				$canvasRotationsArr[$thisProjectID] = $parameterObj->getRotatecanvas(true);
				$pdfPdiObjectsArr[$thisProjectID] = $reorderCardArr[$AccountUserID][$productID]["PDF_PDI_doc"];
				$pdfPageObjectsArr[$thisProjectID] = $reorderCardArr[$AccountUserID][$productID]["PDF_PDI_page"];	
			}
			else{
	
				// Get an individual PDF document for the artwork
				$PDFsigngleArtworkDoc = PdfDoc::GetArtworkPDF($dbCmd, $sideNumber, $artInfoObj, $projectBleedType, $parameterObj->getBleedunits(), $projectRotateCanvas, $parameterObj->getShowBleedSafe(), $parameterObj->getShowCutLine(), $shapesObjectsArr);
		
	
				// Put the PDF data into a PDF lib virtual file
				$PFV_FileName = "/pfv/pdf/ArtworkGangFile" . $thisProjectID . "-" . $sideNumber;
				PDF_create_pvf( $pdf, $PFV_FileName, $PDFsigngleArtworkDoc, "");
	
				$PDI_completeDocumentObj = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
				$PDI_PageObj = PDF_open_pdi_page($pdf, $PDI_completeDocumentObj, 1, "");
	
				PDF_delete_pvf($pdf, $PFV_FileName);
	
	
				$bleedTypesArr[$thisProjectID] = $projectBleedType;
				$canvasRotationsArr[$thisProjectID] = $projectRotateCanvas;
				$pdfPdiObjectsArr[$thisProjectID] = $PDI_completeDocumentObj;
				$pdfPageObjectsArr[$thisProjectID] = $PDI_PageObj;
			}
	
	
			
			
			$projectCounter++;
			
			if($flushProgressOutput == "javascript"){
				print "\n<script>";
				print "DataPercent('Processing: ', " . ceil( $projectCounter / $gangGroupObj->getProjectCount() * 100 ). ");";
				print "</script>\n                                                                          
				                                                                              
				                                                                                                                   
				                                                                                             
				";
				Constants::FlushBufferOutput();
			}
			else if($flushProgressOutput == "spaces"){
				print "                                                                                                       

				                                                                
				";
				Constants::FlushBufferOutput();
			}
	
		}
	
	
	
	
		
		// If we are on the backside... then we want to reverse the column positions
		// on duplex pages, column 1 on the front side will be matched up to the final colum on the backside
		if($sideNumber % 2 != 0)
			$sideBackFlag = true;
		else
			$sideBackFlag = false;
	
	
	
		// Get the parameter from the Object and store in scalar variables 
		$bleedunits = $parameterObj->getBleedunits();
		$originalBleedUnits = $bleedunits;
	
	
		// Make a new page
		pdf_begin_page($pdf, PdfDoc::inchesToPDF($parameterObj->getPagewidth()), PdfDoc::inchesToPDF($parameterObj->getPageheight()));
		pdf_add_bookmark($pdf, $parameterObj->getSideDescription(), 0, 0);
	
	
		$projectCounter = 0;
		
	
	
		// Loop through all rows and columns from the Master PDF profile
		for($k=0; $k < $parameterObj->getColumns(); $k++){
	
			for($z=0; $z < $parameterObj->getRows(); $z++){
	
	
				// Based upon our Row/Column... Figure out what GangRun Project is supposed to be printed there.
				$projectNumForPosition = $gangGroupObj->getProjectIDfromRowColumn($z+1, $k+1);
	
	
				// If there is no project mappaed to this position... then just still the artwork From Position one (should have the same height/width).
				if(empty($projectNumForPosition)){
					$bleedType = "N";
					$canvasRotate = $parameterObj->getRotatecanvas(true);
					
				}
				else{
					$bleedType = $bleedTypesArr[$projectNumForPosition];
					$canvasRotate = $canvasRotationsArr[$projectNumForPosition];
				}
	
	
				$canvasWidthPicas = PdfDoc::inchesToPDF($parameterObj->getUnitWidth()) + $originalBleedUnits * 2;
				$canvasHeightPicas = PdfDoc::inchesToPDF($parameterObj->getUnitHeight()) + $originalBleedUnits * 2;
	
				if($canvasRotate == 90 || $canvasRotate == 270){
					$tempWidth = $canvasWidthPicas;
					$canvasWidthPicas = $canvasHeightPicas;
					$canvasHeightPicas = $tempWidth;
				}
	
	
	
				// Selecting a "V"oid bleed type will crop off the image at the "Safe Line".
				// Or you could consider it a negative bleed.
				if($bleedType == "V")
					$bleedunits = -($bleedunits);
	
	
				// the following function call will Translate the coordinates of PDF lib to where we want 
				// begin drawing this copy, so make sure to Save before we do that
				pdf_save($pdf);
				
	
				PdfDoc::_translatePDFpointerToContentPosition($pdf, $parameterObj, ($z+1), ($k+1), $sideBackFlag);
				
	
	
				pdf_save($pdf);
				pdf_translate($pdf, -($bleedunits), -($bleedunits));
	
	
				// Paste the Mini PDF document onto the larger one.
				// But only if there is a project mapped to this position
				if(!empty($projectNumForPosition)){
				
					// Save in case the Aspect ratio will be chaning.
					pdf_save($pdf);
			
					// If we have chosen to Force the aspect ratio, then make sure that the PDF fills in the Specified Width/Height of the PDF profile, even if it means distorting.
					if($parameterObj->getForceAspectRatio()){
	
						$artworkPicasWidth = PDF_get_pdi_value($pdf, "width", $pdfPdiObjectsArr[$projectNumForPosition], $pdfPageObjectsArr[$projectNumForPosition], 0);
						$artworkPicasHeight = PDF_get_pdi_value($pdf, "height", $pdfPdiObjectsArr[$projectNumForPosition], $pdfPageObjectsArr[$projectNumForPosition], 0);
	
						$artwork_scale_x = $canvasWidthPicas / $artworkPicasWidth;
						$artwork_scale_y = $canvasHeightPicas / $artworkPicasHeight;
											
						PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);					
					}
				
					
					// Put the artwork in the correct spot. 
					if(!$isCoverSheetFlag)
						PDF_fit_pdi_page($pdf, $pdfPdiObjectsArr[$projectNumForPosition], 0, 0, "");
					
					
					
					
					// However, only put the barcode/reorder card in one of the slots.
					if($isCoverSheetFlag && $gangGroupObj->checkIfProjectIsFirst($z+1, $k+1)){
					
						$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectNumForPosition);
				
						PDF_fit_pdi_page($pdf, $pdfPdiObjectsArr[$projectNumForPosition], 0, 0, "");
					
						if($flushProgressOutput == "javascript"){
							print "\n<script>";
							print "DataPercent('Thumbnails: ', " . ceil( $projectCounter / $gangGroupObj->getProjectCount() * 100 ). ");";
							print "</script>\n
							                                                                                                       
							                                                                                                            
							                                                                                                 
							";
							Constants::FlushBufferOutput();
						}
						else if($flushProgressOutput == "spaces"){
							print "                                                                                                       

				                                                                
							";
							Constants::FlushBufferOutput();
						}
						
						$projectCounter++;
						
						pdf_save($pdf);
						
						pdf_translate($pdf, $bleedunits, $bleedunits);
						
						PdfDoc::_translatePointerForRotation($pdf, $parameterObj);
						
						pdf_translate($pdf, -($bleedunits), -($bleedunits));
						
						PdfDoc::_drawBarcodeAndThumbnailImage($dbCmd, $pdf, $projectObj, $barCodeXOnCoverSheet, $barCodeYOnCoverSheet, PdfDoc::inchesToPDF($parameterObj->getUnitWidth()), PdfDoc::inchesToPDF($parameterObj->getUnitHeight()));
						pdf_restore($pdf);
					}
					
					// Restore from possible Page Scaling.
					pdf_restore($pdf);
					
					
					// Since this barcode area is blank (because a single projects is taking up more than 1 space)... show the count... like 2 of 2 ... or 3 of 4.
					if($isCoverSheetFlag && !$gangGroupObj->checkIfProjectIsFirst($z+1, $k+1)){
						
						$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectNumForPosition);
						
						pdf_save($pdf);
						$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
						pdf_setfont ( $pdf, $DeckerFont, 15);
						pdf_show_xy($pdf, ("P" . $projectNumForPosition), 50, 70);
						pdf_show_xy($pdf, ("Slot " . $gangGroupObj->getCountOfProjectOccupyBeforePosition($z+1, $k+1) . " of " . $gangGroupObj->getSpacesUsedByProjectID($projectNumForPosition) . " -- Qty: " . $projectObj->getQuantity()) , 50, 48);
						pdf_restore($pdf);
					}
	
				}
	
	
				// Restore from Bleed translation
				pdf_restore($pdf);
	
	
				if($parameterObj->getShowCropMarks()){
					$cropMarkCheckArr = PdfDoc::_checkForCropMarkEdges($parameterObj, $z, $k, $sideBackFlag);
					PdfDoc::_drawCropMarks($pdf, $canvasWidthPicas, $canvasHeightPicas, $originalBleedUnits, $cropMarkCheckArr["LeftEdge"], $cropMarkCheckArr["RightEdge"], $cropMarkCheckArr["TopEdge"], $cropMarkCheckArr["BottomEdge"]);
				}
				
				if($parameterObj->getShowOutsideBorder()){
					PdfDoc::_drawOutlineAroundArt($pdf, $originalBleedUnits, $canvasWidthPicas, $canvasHeightPicas);
				}
	
	
				// Restore from Coordinate translation
				pdf_restore($pdf);
			}
		}
		
		
	
		PdfDoc::DrawColorSeparationBarBetweenRowGaps($pdf, $parameterObj, $colorCodeObj);
		
		
		// If there are any Global Shapes... put them on the very top of the PDF.
		$shapesContainerObjGlobal = $parameterObj->getGlobalShapeContainer();
		$shapesGlobalObjectsArr = $shapesContainerObjGlobal->getShapeObjectsArr($sideNumber);
		PdfDoc::_drawShapesOnPDF($pdf, $shapesGlobalObjectsArr);
		
		
		pdf_end_page($pdf);
	
	
		if($isCoverSheetFlag){
		
			// Close all of the PDI pages object for the reorder cards
			foreach($reorderCardArr as $ReorderCard_AccountIDHash){
				foreach($ReorderCard_AccountIDHash as $recordCardProductIDhash){
					PDF_close_pdi_page($pdf, $recordCardProductIDhash["PDF_PDI_page"]);
					PDF_close_pdi($pdf, $recordCardProductIDhash["PDF_PDI_doc"]);
				}
			}
		}
		else{
			// Discard the Page objects.
			foreach($pdfPageObjectsArr as $pageObj)
				PDF_close_pdi_page($pdf, $pageObj);
	
			// Discard the PDI objects.
			foreach($pdfPdiObjectsArr as $thisPDIObj)
				PDF_close_pdi($pdf, $thisPDIObj);
		}
	
	
		PDF_end_document($pdf, "");
	
		$data = pdf_get_buffer($pdf);
	
		return $data;
	
	}
	
	
	
	static function _drawOutlineAroundArt(&$pdf, $bleedPicas, $pagewidth, $pageheight){
	
	
		pdf_save($pdf);
	
		pdf_setlinewidth ( $pdf, 0.3);
		pdf_rect($pdf, -($bleedPicas), -($bleedPicas), $pagewidth, $pageheight );
		pdf_stroke($pdf);
	
		pdf_restore($pdf);
	}
	
	
	// Will return an array of 4 boolean elements... telling you if you are on the left edge, right edge, etc.
	// only draw them to the left if the design is on the left-most column... on the top if it is the top most column, etc.
	// If there is only 1 design on the page then crop marks will come off of all 4 corners of the design
	// Also crop marks should be extended if they are across a gap
	static function _checkForCropMarkEdges(&$parameterObj, $currentRow, $currentColBeforeTrans, $backsideFlag){
		
		$retArr = array();
		$retArr["LeftEdge"] = false;
		$retArr["RightEdge"] = false;
		$retArr["TopEdge"] = false;
		$retArr["BottomEdge"] = false;
		
		
		if($backsideFlag)
			$currentCol = -($currentColBeforeTrans - $parameterObj->getColumns()) - 1;
		else
			$currentCol = $currentColBeforeTrans;
		
	
		// Draw crop marks on the outside columns
		if($currentCol == 0)
			$retArr["LeftEdge"] = true;
		if($currentCol == ($parameterObj->getColumns() - 1))
			$retArr["RightEdge"] = true;
		if($currentRow == 0)
			$retArr["BottomEdge"] = true;
		if($currentRow == ($parameterObj->getRows() - 1))
			$retArr["TopEdge"] = true;
	
	
		// Draw Crop mark on the rows after a vetical gap
		if($currentRow !=0 && $parameterObj->getGapr() != 0 && ($currentRow % $parameterObj->getGapr()) == 0)
			$retArr["BottomEdge"] = true;
	
		// Draw Crop mark on the rows before a vetical gap
		if(($currentRow+1) != 0 && $parameterObj->getGapr() != 0 && (($currentRow+1) % $parameterObj->getGapr()) == 0)
			$retArr["TopEdge"] = true;
			
		
		return $retArr;
	}
	
	
	// Possibly draws the Cropmarks and Outlines on top of the PDF at the current pointer position.
	// Pointer should be at the bottom left corner of the "CutLine".
	static function _drawCropMarks(&$pdf, $pagewidth, $pageheight, $bleedPicas, $leftEdgeFlag, $rightEdgeFlag, $topEdgeFlag, $bottomEdgeFlag){
	
		pdf_save($pdf);
		pdf_setlinewidth ($pdf, 0.3);
	
		$cropMarkLength = 6;
	
		// Draw crop marks on the outside columns
		if($leftEdgeFlag){
			pdf_moveto($pdf, -($bleedPicas), 0);
			pdf_lineto($pdf, -($bleedPicas + $cropMarkLength), 0);
			pdf_stroke($pdf);
	
			pdf_moveto($pdf, -($bleedPicas), ($pageheight - $bleedPicas * 2));
			pdf_lineto($pdf, -($bleedPicas + $cropMarkLength), ($pageheight - $bleedPicas * 2));
			pdf_stroke($pdf);
		}
		if($rightEdgeFlag){
			pdf_moveto($pdf, ($pagewidth - $bleedPicas), 0);
			pdf_lineto($pdf, ($pagewidth - $bleedPicas + $cropMarkLength), 0);
			pdf_stroke($pdf);
	
			pdf_moveto($pdf, ($pagewidth - $bleedPicas), ($pageheight - $bleedPicas * 2));
			pdf_lineto($pdf, ($pagewidth - $bleedPicas + $cropMarkLength), ($pageheight - $bleedPicas * 2));
			pdf_stroke($pdf);
		}
	
		// Draw crop marks on the outside rows
		if($bottomEdgeFlag){
			pdf_moveto($pdf, 0, -($bleedPicas));
			pdf_lineto($pdf, 0, -($bleedPicas + $cropMarkLength));
			pdf_stroke($pdf);
	
			pdf_moveto($pdf, ($pagewidth - $bleedPicas * 2), -($bleedPicas));
			pdf_lineto($pdf, ($pagewidth - $bleedPicas * 2), -($bleedPicas + $cropMarkLength));
			pdf_stroke($pdf);
		}
		if($topEdgeFlag){
			pdf_moveto($pdf, 0, ($pageheight - $bleedPicas));
			pdf_lineto($pdf, 0, ($pageheight - $bleedPicas + $cropMarkLength));
			pdf_stroke($pdf);
	
			pdf_moveto($pdf, ($pagewidth - $bleedPicas * 2), ($pageheight - $bleedPicas));
			pdf_lineto($pdf, ($pagewidth - $bleedPicas * 2), ($pageheight - $bleedPicas + $cropMarkLength));
			pdf_stroke($pdf);
		}
	
	
		// Restore from setting line width
		pdf_restore($pdf);
	}
	
	
	// The PDF system measures in points which are 72 points per inch
	static function inchesToPDF($inches){
	
		return $inches * 72;
	}
	
	// Our Flash Coordinates are 96 DPI, and the PDF doc is 72
	static function flashToPDF($flashCoords){
		return $flashCoords * 72/96;
	
	}
	
	
	
	// Translate the PDF coordinates based upon our rotation.  Since the canvas is rotating we also have to change registration point (since that gets rotated too)
	static function _translatePointerForRotation(&$pdf, &$parameterObj){
	
		
		if($parameterObj->getRotatecanvas(true) == "90"){
			pdf_rotate($pdf, -90);
			pdf_translate($pdf, -(PdfDoc::inchesToPDF($parameterObj->getUnitWidth())), 0);
		}
		else if($parameterObj->getRotatecanvas(true) == "270"){
			pdf_translate($pdf, PdfDoc::inchesToPDF($parameterObj->getUnitHeight()), 0);
			pdf_rotate($pdf, 90);
		}
		else if($parameterObj->getRotatecanvas(true) == "180"){
			pdf_translate($pdf, PdfDoc::inchesToPDF($parameterObj->getUnitWidth()), PdfDoc::inchesToPDF($parameterObj->getUnitHeight()));
			pdf_rotate($pdf, 180);
		}
	
	
	}
	
	
	// Pass in a PDF object (by referernce) as well as the parameters / row /column
	// Content Width/Height is the size of the Artwork itself... in PDF measurements
	// Make sure to "Save" the PDF state before calling this function and you can "Restore" after
	// It will translate the cooridinates (or move the cursor) to the given row column
	// Puts cursor on the lower-left corner of the "cut line".
	// Pass in the Backside Flag if you want to translate the current row/col for the backside... which has the columns transposed to line up on the front.
	// .... On the backside it will also measure from the Right margin of the sheet... so that the artwork doesn't have to be plotted symetrically between the sheet margins to have it match up to the front side.
	static function _translatePDFpointerToContentPosition(&$pdf, &$parameterObj, $rowNum, $columnNum, $backsideFlag){
	
		if($rowNum > $parameterObj->getRows() || $columnNum > $parameterObj->getColumns())
			throw new Exception("the function PdfDoc::_translatePDFpointerToContentPosition is being called outside of the row column/limits. Rows: $rowNum : Cols: $columnNum  : Actual Rows: ");
	
		// Find out the total number of possible column gaps if the sheet is totally full.
		if($parameterObj->getGapc() != 0)
			$numberOfColumnGaps = floor(($parameterObj->getColumns() -1) / $parameterObj->getGapc());
		else
			$numberOfColumnGaps = 0;
			
	
		for($k=0; $k < $parameterObj->getColumns(); $k++){
		
			if($backsideFlag)
				$columnNumberTranslated = -($k - $parameterObj->getColumns()) - 1;
			else
				$columnNumberTranslated = $k;
	

			// We may need to add an extra Gap every few columns.  if $parameterObj->getGapc() comes in such as "3" for ex.  That means every 3 columns add an extra space.  The amount of the space is spacified in the Gap Horizontal distance "gapsizeH"
			if($parameterObj->getGapc() != 0)
				$ExtraColumnGap = floor($k / $parameterObj->getGapc()) * PdfDoc::inchesToPDF($parameterObj->getGapsizeH());
			else 
				$ExtraColumnGap = 0;

			
			// When printing duplex, the horizontal gaps need to be inverted.
			if($backsideFlag){
				$ExtraColumnGap = $numberOfColumnGaps * PdfDoc::inchesToPDF($parameterObj->getGapsizeH()) - $ExtraColumnGap;
			}
				
	
			// In case they want to rotate the Artwork by 90 degrees we have to juggle the how the rows and columnts get spaced from each other
			if($parameterObj->getRotatecanvas(true) <> "0" && $parameterObj->getRotatecanvas(true) <> "180"){
				$PDF_HorizontalChange = PdfDoc::inchesToPDF($parameterObj->getUnitHeight());
				$PDF_VerticalChange = PdfDoc::inchesToPDF($parameterObj->getUnitWidth());
			}
			else{
				$PDF_HorizontalChange = PdfDoc::inchesToPDF($parameterObj->getUnitWidth());
				$PDF_VerticalChange = PdfDoc::inchesToPDF($parameterObj->getUnitHeight());
			}
	
	
			// For each column that we replicate we will need to change the x coordinates of where the registration for every image will be in this column.
			$ContentPlacment_X = $columnNumberTranslated * $PDF_HorizontalChange + $columnNumberTranslated * $parameterObj->getHspacing() + PdfDoc::inchesToPDF($parameterObj->getLmargin()) + $ExtraColumnGap;
	
	
			// On the backside we want to measure from the Right-Margin to make sure it lines up with the front.
			if($backsideFlag){

				$totalGapsWidth = $numberOfColumnGaps * PdfDoc::inchesToPDF($parameterObj->getGapsizeH());

				$totalWidthBeforeRightMargin = PdfDoc::inchesToPDF($parameterObj->getLmargin()) + (($parameterObj->getColumns() - 1) * $parameterObj->getHspacing()) + ($parameterObj->getColumns() * $PDF_HorizontalChange) + $totalGapsWidth + PdfDoc::inchesToPDF($parameterObj->getLmargin());
				
				$rightMarginPicas = PdfDoc::inchesToPDF($parameterObj->getPagewidth()) - $totalWidthBeforeRightMargin;
			
				$ContentPlacment_X += $rightMarginPicas;
			}

	
				
	
			$ExtraRowGap = 0;
	
			for($z=0; $z < $parameterObj->getRows(); $z++){
	
				// We may need to add an extra Gap every few rows.  if $parameterObj->getGapr() comes in such as "3" for ex.  That means every 3 rows add an extra space.  The amount of the space is spacified in the Gap Vertical distance "gapsizeV"
				if($z != 0 && $parameterObj->getGapr() <> 0){  //Dont check for a gap on the first row ..... also dont do gap checking if it the frequency is 0.
					if((($z + $parameterObj->getGapr()) % $parameterObj->getGapr()) == 0)
						$ExtraRowGap += PdfDoc::inchesToPDF($parameterObj->getGapsizeV());
				}
	
				// For each row that we replicate we will need to change the y coordinates of where the registration for every image will be in this row.  Remember that for PDFlib the regpoint starts in the lower-left corner.
				$ContentPlacment_Y = $z * $PDF_VerticalChange + $z * $parameterObj->getVspacing() + PdfDoc::inchesToPDF($parameterObj->getBmargin()) + $ExtraRowGap;
	
	
				// Translate the coordinates of PDF lib to where we want begin drawing... whatever comes next
				// We can exit the funcion at this point
				if($rowNum == ($z+1) && $columnNum == ($k+1)){
					pdf_translate($pdf, $ContentPlacment_X, $ContentPlacment_Y);
					return;
				}
			}
		}

	}
	
	
	
	static function DrawColorSeparationBarBetweenRowGaps(&$pdf, &$parameterObj, $ColorCodeObj){
	
	
		if($parameterObj->getGapr() <> 0){
	
			// Take away 1/2 of the vertical spacing to compensate for the Extra space that is added after multiplying by the number of rows
			// Take away 1/2 of the gap size to put us exactly in the middle of the gap
			$Ycoord_GapV = PdfDoc::inchesToPDF($parameterObj->getGapsizeV())/2  + PdfDoc::inchesToPDF($parameterObj->getBmargin()) - $parameterObj->getVspacing()/2;
	
	
			// Now figure out where we are on the page based upon how many rows the gap is accounted from 
			// In case they want to rotate the Artwork by 90 degrees the order numbers are written differently
			if($parameterObj->getRotatecanvas(true) <> "0" && $parameterObj->getRotatecanvas(true) <> "180")
				$Ycoord_GapV += $parameterObj->getGapr() * ($parameterObj->getVspacing() + PdfDoc::inchesToPDF($parameterObj->getUnitWidth()));
			else
				$Ycoord_GapV += $parameterObj->getGapr() * ($parameterObj->getVspacing() + PdfDoc::inchesToPDF($parameterObj->getUnitHeight()));
	
	
			// Draw "Order Separatation" Color Indication bars in the very center of the gap
			// Make the thickness of the bar twice the "Bleed Units".  That way they can find out if the guiltone cut is in accurate and will produce defects
			// In case there is no bleed units then we also need to show a thick bar in the center with the color indication
			// We don't want to go all the way to the edges of the paper though.  
			// A little bit of cut line should be left so that we can see clearly for our laser sites on the guilotene cutter
			$SideMargins = 20;
			$SideMarginsForSolidColorBar = 60;
			$BarThicknessFromBleed = $parameterObj->getBleedUnits() * 2;
			$BarThicknessSolid = 15;
			pdf_save($pdf);
			pdf_setcolor($pdf, "both", "RGB", $ColorCodeObj->red/256, $ColorCodeObj->green/256, $ColorCodeObj->blue/256, 0);
	
			if($BarThicknessFromBleed <> 0){
				PDF_rect($pdf, $SideMargins, ($Ycoord_GapV - $BarThicknessFromBleed/2) , (PdfDoc::inchesToPDF($parameterObj->getPagewidth()) - $SideMargins*2), $BarThicknessFromBleed);
				PDF_fill($pdf);
			}
	
			PDF_rect($pdf, $SideMarginsForSolidColorBar, ($Ycoord_GapV - $BarThicknessSolid/2) , (PdfDoc::inchesToPDF($parameterObj->getPagewidth()) - $SideMarginsForSolidColorBar*2), $BarThicknessSolid);
			PDF_fill($pdf);
	
			pdf_restore($pdf);
	
			// Draw a line for them to Cut on.
			pdf_setlinewidth ($pdf, 0.3);
			pdf_moveto($pdf, 0, $Ycoord_GapV);
			pdf_lineto($pdf, PdfDoc::inchesToPDF($parameterObj->getPagewidth()), $Ycoord_GapV);
			pdf_stroke($pdf);	
	
	
		}
	
	
	}
	
	
	
	
	// Pass in a PDF Object (by reference)
	// It will draw the order number and color bar separation on the PDF
	// Does not return anything.
	static function WriteOrderNumberOnPDF(&$pdf, &$parameterObj, $ColorCodeObj, $isVariableDataFlag){
	
		pdf_save($pdf);
		pdf_set_parameter( $pdf, "FontOutline", "Decker=Decker.ttf");
		$fontRes = pdf_findfont($pdf, "Decker", "winansi", 1);
		pdf_setfont ( $pdf, $fontRes, 6);
	
	
		$unitCanvasWidth  = PdfDoc::inchesToPDF($parameterObj->getUnitWidth()) + $parameterObj->getBleedUnits() * 2;
		$unitCanvasHeight  = PdfDoc::inchesToPDF($parameterObj->getUnitHeight()) + $parameterObj->getBleedUnits() * 2;
	
		// We need to write the order number in different positions depending on the how the PDF doc is built
		if($parameterObj->getGapc() <> 0){
	
			pdf_translate($pdf, 0, PdfDoc::inchesToPDF($parameterObj->getPageheight())/2); //Put the order numbers in the center of the page
	
	
			$NewXcoord_ordernumber = PdfDoc::inchesToPDF($parameterObj->getGapsizeH())/2  + PdfDoc::inchesToPDF($parameterObj->getLmargin()) - $parameterObj->getHspacing();
	
			#-- In case they want to rotate the Artwork by 90 degrees the order numbers are written differently--#
			if($parameterObj->getRotatecanvas(true) <> "0" && $parameterObj->getRotatecanvas(true) <> "180"){
				$NewXcoord_ordernumber += $parameterObj->getGapc() * ($parameterObj->getHspacing() + $unitCanvasHeight);
			}
			else {
				$NewXcoord_ordernumber += $parameterObj->getGapc() * ($parameterObj->getHspacing() + $unitCanvasWidth);
			}
	
			#-- Write the order numbers vertically --#
			pdf_rotate($pdf, 90);
	
			#-- Put the 2 order numbers in between the gap, so when the paper is cut it will be on both pages -#
			pdf_show_xy($pdf, $parameterObj->getOrderno(), 0, -($NewXcoord_ordernumber + 15));
			pdf_show_xy($pdf, $parameterObj->getOrderno(), 0, -($NewXcoord_ordernumber - 10));
	
	
		}
		else if($parameterObj->getGapr() <> 0){
	
			// Take away 1/2 of the vertical spacing to compensate for the Extra space that is added after multiplying by the number of rows
			// Take away 1/2 of the gap size to put us exactly in the middle of the gap
			$Ycoord_GapV = PdfDoc::inchesToPDF($parameterObj->getGapsizeV())/2  + PdfDoc::inchesToPDF($parameterObj->getBmargin()) - $parameterObj->getVspacing()/2;
	
			// Now figure out where we are on the page based upon how many rows the gap is accounted from 
			// In case they want to rotate the Artwork by 90 degrees the order numbers are written differently
			if($parameterObj->getRotatecanvas(true) <> "0" && $parameterObj->getRotatecanvas(true) <> "180")
				$Ycoord_GapV += $parameterObj->getGapr() * ($parameterObj->getVspacing() + PdfDoc::inchesToPDF($parameterObj->getUnitWidth()));
			else
				$Ycoord_GapV += $parameterObj->getGapr() * ($parameterObj->getVspacing() + PdfDoc::inchesToPDF($parameterObj->getUnitHeight()));
	
	
	
			PdfDoc::DrawColorSeparationBarBetweenRowGaps($pdf, $parameterObj, $ColorCodeObj);
	
		
			// Put the 2 order numbers in between the gap, so when the paper is cut it will be on both pages
			pdf_translate($pdf, (PdfDoc::inchesToPDF($parameterObj->getPagewidth()) / 2.8), 0);  //Put the order numbers close to the center of the page
			pdf_show_xy($pdf, $parameterObj->getOrderno(), 0, $Ycoord_GapV + 9.5);
			pdf_show_xy($pdf, $parameterObj->getOrderno(), 0, $Ycoord_GapV - 14);
		}
		else{
	
			// Since we do not have a Horizontal or Vertical GAP we have more control over where we will place the Order Number and Color Separation Bar
			// The color bar will go in relation to the position of the Label in the PDF parameter
			// If the label is rotated, so will be the color bar...  The color bar will always extend to the boundaries of the page... minus $SideMarginsForSolidColorBar
	
	
			// Setup Variables for the Color Bar
			$yCoordDifferenceForColorBar = 6;
			$SideMarginsForSolidColorBar = 50;
			$BarThicknessSolid = 17;
			
			// Setup Variables for the White Box that will go behind the Order Number, on top of the Color Bar
			$yCoordDifferenceForWhiteBox = 3;
			$xCoordDifferenceForWhiteBox = 3;
			$WidthWhiteBox = 350;
			$WhiteBoxThickness = 11;
			
			
			pdf_save($pdf);
			
			// If we are rotated by 90 degress then we want to put the pointer in Bottom-right of the page
			// Rotation by 270 puts us at the top-left, 180 degrees puts the pointer at the top-right
			if($parameterObj->getLabelrotate() == 90 || $parameterObj->getLabelrotate() == 270){
				$barWidth = PdfDoc::inchesToPDF($parameterObj->getPageheight()) - $SideMarginsForSolidColorBar*2;
				
				// The X coordinate of the Label becomes the Y coordinate of the color bar (relatively) after rotation
				if($parameterObj->getLabelrotate() == 90){
					pdf_translate($pdf, PdfDoc::inchesToPDF($parameterObj->getPagewidth()), 0);
					$yCoordColorBar = PdfDoc::inchesToPDF($parameterObj->getPagewidth()) - PdfDoc::inchesToPDF($parameterObj->getLabelx()) - $yCoordDifferenceForColorBar;
				}
				else{
					pdf_translate($pdf, 0, PdfDoc::inchesToPDF($parameterObj->getPageheight()));
					$yCoordColorBar = PdfDoc::inchesToPDF($parameterObj->getLabelx()) - $yCoordDifferenceForColorBar;
				}				
			}
			else{
				$barWidth = PdfDoc::inchesToPDF($parameterObj->getPagewidth()) - $SideMarginsForSolidColorBar*2;
				
				if($parameterObj->getLabelrotate() == 180){
					pdf_translate($pdf, PdfDoc::inchesToPDF($parameterObj->getPagewidth()), PdfDoc::inchesToPDF($parameterObj->getPageheight()));	
					$yCoordColorBar = PdfDoc::inchesToPDF($parameterObj->getPageheight()) - PdfDoc::inchesToPDF($parameterObj->getLabely()) - $yCoordDifferenceForColorBar;
				}
				else{
					$yCoordColorBar = PdfDoc::inchesToPDF($parameterObj->getLabely()) - ($yCoordDifferenceForColorBar);
				}
			}
			
			// Draw the rectangle color bar
			pdf_save($pdf);
			pdf_rotate($pdf, $parameterObj->getLabelrotate());
			pdf_setcolor($pdf, "both", "RGB", $ColorCodeObj->red/256, $ColorCodeObj->green/256, $ColorCodeObj->blue/256, 0);
			PDF_rect($pdf, $SideMarginsForSolidColorBar, $yCoordColorBar, $barWidth, $BarThicknessSolid);
			PDF_fill($pdf);
			pdf_restore($pdf);  // restore from color change
			
			
			pdf_restore($pdf); // Restore from position translation For Color Bar
			
			
			
			
	
			// If we are to write an order number on the document
			if($parameterObj->getOrderno() != ""){
			
				// Translate to Label Postion
				pdf_save($pdf);
				pdf_translate($pdf, PdfDoc::inchesToPDF($parameterObj->getLabelx()), PdfDoc::inchesToPDF($parameterObj->getLabely()));
				pdf_rotate($pdf, $parameterObj->getLabelrotate());
	
			
				// Draw the white box on top of the Color bar and underneath the order number
				// If it is a Variable Data order then show a Yellow background.
				pdf_save($pdf);
			
			
				if($parameterObj->getMailingServices())
					pdf_setcolor($pdf, "both", "RGB", 1, (intval("CC", 16)/255), 1, 0);  //ColorCode: FFCCFF
				else if($isVariableDataFlag)
					pdf_setcolor($pdf, "both", "RGB", 1, 1, 0.4, 0);
				else
					pdf_setcolor($pdf, "both", "RGB", 1, 1, 1, 0);
	
	
				PDF_rect($pdf, -($xCoordDifferenceForWhiteBox) , -($yCoordDifferenceForWhiteBox), $WidthWhiteBox, $WhiteBoxThickness);
				PDF_fill($pdf);
				pdf_restore($pdf); // restore from color change
	
	
				pdf_show_xy($pdf, "Order: " . $parameterObj->getOrderno(), 0, 0);
	
			
				pdf_restore($pdf); // Restore from position translation For Label position
			}
		}
	
	
		pdf_restore($pdf);  //Restoring from writing the order numbers
	}
	
	// This function takes in a PDF file (through memory) and will reproduce the file as many times as specified 
	// If returns one single document (through memory)..  If an artwork is double sided, then the pages would alternate front/back for as many copies that are specified.
	static function ReproducePDF($PDFdoc, $Copies){
	
	
		// Create a new/blank PDF doc that we will paste the pages onto 
		$pdf = pdf_new();
		
		if(!Constants::GetDevelopmentServer())
			pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());
		
		PDF_begin_document($pdf, "", "");
	
		// Put the Original PDF Binary data into a PDF lib virtual file
		$PFV_FileName = "/pfv/pdf/ArtworkFileMaster";
		PDF_create_pvf( $pdf, $PFV_FileName, $PDFdoc, "");
	
	
		// Put the Original PDF file into a PDI object that we can extract page(s) from.
		$OriginalPDF = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
		
		PDF_delete_pvf($pdf, $PFV_FileName);
	
		$TotalPagesInOriginal = PDF_get_pdi_value($pdf, "/Root/Pages/Count", $OriginalPDF, 0, 0);
	
		// Create an array of PDF Page objects.  There should be one array entry for each page 
		$PDI_PagesObjArr = array();
		for($i=0; $i < $TotalPagesInOriginal; $i++)
			$PDI_PagesObjArr[$i] = PDF_open_pdi_page($pdf, $OriginalPDF, ($i+1), "");
	
	
	
		for($j=0; $j<$Copies; $j++){
			for($i=0; $i < $TotalPagesInOriginal; $i++){
	
				$pagewidth = PDF_get_pdi_value($pdf, "width", $OriginalPDF, $PDI_PagesObjArr[$i], 0);
				$pageheight = PDF_get_pdi_value($pdf, "height", $OriginalPDF, $PDI_PagesObjArr[$i], 0);
			
				pdf_begin_page($pdf, $pagewidth, $pageheight);
	
	    			PDF_fit_pdi_page($pdf, $PDI_PagesObjArr[$i], 0, 0, "");
				
				pdf_end_page($pdf);
			}
		}
	
		// Now close all of the PDI page objects
		for($i=0; $i < $TotalPagesInOriginal; $i++)
			PDF_close_pdi_page($pdf, $PDI_PagesObjArr[$i]);
	
		PDF_close_pdi($pdf, $OriginalPDF);
		
		PDF_end_document($pdf, "");
	
		$data = pdf_get_buffer($pdf);
	
		return $data;
	}
	
	
	
	
	
	// It returns the binary PDF file file.
	// $bleedtype can be tile, stretch, none, or natural
	// $bleedunits is in picas... if a bleed type of "none" is selected then $BleedUnits is ignored
	// $rotatecanvas can be 0, 90, 180, 270
	// $Zonebleed and ZoneSafe should be the amount of picas away from the artwork boundaries that dashed lines should be drawn
	// $ShowCutLine should be true or false depending on whether a border should be drawn on the cut-line
	// $ShapeObjectsArr is an array of Shape Objects that should be drawn on top of the artwork.  For examples a Window Envelope may have a Rectangle drawn where the Window is to be punched out.
	static function GetArtworkPDF(DbCmd $dbCmd, $SideNumber, $ArtworkInfoObj, $bleedtype, $bleedunits, $rotatecanvas, $showBleedSafeLines, $ShowCutLine, $ShapeObjectsArr){
	
	
		// To Keep the Inodes from filling up with files too quickly, we want to organize as many subdirectories as possible.
		// We don't ever want more than 10,000 files in a directory.
		$pdfDirName = "Side" . $SideNumber . "_Bleed" . $bleedunits . "_ShowLines" . ($showBleedSafeLines ? "Yes" : "No");
			
		$imageCacheSubDir = Constants::GetImageCacheDirectory() . "/PDFsingles"; 
		if(!file_exists($imageCacheSubDir)){
			mkdir($imageCacheSubDir);
			chmod($imageCacheSubDir, 0777);
		}
		
		$imageCacheSubDir = Constants::GetImageCacheDirectory() . "/PDFsingles/" . $pdfDirName; 
		if(!file_exists($imageCacheSubDir)){
			mkdir($imageCacheSubDir);
			chmod($imageCacheSubDir, 0777);
		}
		
		if(!isset($ArtworkInfoObj->SideItemsArray[$SideNumber])){
			$errorMessage = "Error in PdfDoc::GetArtworkPDF. Side Number does not exist: " . $SideNumber;
			WebUtil::WebmasterError($errorMessage . " ArtworkFile: " . serialize($ArtworkInfoObj));
			throw new Exception($errorMessage);
		}
		
		
		$parametersStr = $bleedtype . $bleedunits . $rotatecanvas . ($ShowCutLine ? "showCut" : "noCutLine") . serialize($ShapeObjectsArr);
		
		$pdfFileName = $imageCacheSubDir . "/" . md5(serialize($ArtworkInfoObj->SideItemsArray[$SideNumber]) . $parametersStr);
	

		if(file_exists($pdfFileName) && filesize($pdfFileName) > 0){
		
			$fd = fopen ($pdfFileName, "r");
			$pdfBinaryData = fread ($fd, filesize ($pdfFileName));
			fclose ($fd);
		
			return $pdfBinaryData;
		}
		
		// ----------  End of Caching Technique  .... below is what happens if the PDF isn't cached --------------------
	
	
	
		// Create the PDF doc with PHP`s extension PDFlib
		$pdf = pdf_new();
		
		// Sets the license key, author, font paths, etc.
		PdfDoc::setParametersOnNewPDF($pdf);
		
		PDF_begin_document($pdf, "", "");
		
	
		// Define the Artwork Ojbect for the side that we will be generating.
		$SideObj = $ArtworkInfoObj->SideItemsArray[$SideNumber];

		// Convert the Content size into PDF coordinates... do not include Bleed units
		$artworkWidthInPicas = PdfDoc::flashToPDF($SideObj->contentwidth);
		$artworkHeightInPicas = PdfDoc::flashToPDF($SideObj->contentheight);
	
	
	
	
		// Selecting a "V"oid bleed type will crop off the image at the "Safe Line".
		// Or you could consider it a negative bleed.
		if($bleedtype == "V")
			$bleedunits = -($bleedunits);
		
	
	
		// Figure out how big to make the PDF document based up on our artwork file and the size of our bleed units.
		$pagewidth = $artworkWidthInPicas + $bleedunits * 2;
		$pageheight = $artworkHeightInPicas + $bleedunits * 2;
		
		if($rotatecanvas == 90 || $rotatecanvas == 270){
			$tempWidth = $pagewidth;
			$pagewidth = $pageheight;
			$pageheight = $tempWidth;
		}
	
	
		// Make a new page
		pdf_begin_page($pdf, $pagewidth, $pageheight);
	
	
	
		// If the bleed type is a "N"atural.. then we want to draw the background slightly larger than where the "cut-line" exists.
		// Tile and stretch always crop the image where the cut line is. --#
		if($bleedtype == "N"){
	
			//$bleedunits are in picas
			// We want to convert that to a Pixels value (which is based upon our DPI)
			$PDFtoPixels_conversion = $SideObj->dpi / 72;
			$NaturalBleedPixels =  $bleedunits * $PDFtoPixels_conversion;
		}
		else if($bleedtype == "V"){
			$NaturalBleedPixels = $bleedunits;
		}
		else if($bleedtype == "S" || $bleedtype == "T"){
			// For Stretch & Tile... we want to use the background data exactly at the "Cut Line".
			$NaturalBleedPixels = 0;
		}
		else
			throw new Exception("illegal bleed type: $bleedtype");
	
	
		// Generate the Image.. The function will return a JPEG for us
		$BackGroundImageData = &ArtworkLib::GetArtworkImage($dbCmd, $ArtworkInfoObj, $SideNumber, $showBleedSafeLines, $NaturalBleedPixels, false);;
	
	
		// Load the Background Image data into a PDF lib virtual file
		PDF_create_pvf( $pdf, "/pfv/images/BackgroundData", $BackGroundImageData, "");
	
		// Get an image resource for the PDF doc through the virtual file
		$pdfimage = PDF_load_image ($pdf, "jpeg", "/pfv/images/BackgroundData", "");
		
		// Now we can release our virtual file since we already have a PDF image resource
		PDF_delete_pvf($pdf, "/pfv/images/BackgroundData");
	
		if(!$pdfimage)
			PdfDoc::PDFErrorMessage("There was an error creating the PDF document.", "The Background image could not be imported.");
		
	
	
		//------------  Now we got the Background image... so finish generating the rest of the PDF --------
	
	
	
	
		// Get the Image width (in PDF coordinates) 
		$Actual_imagewidth = pdf_get_value( $pdf, "imagewidth", $pdfimage);
		$Actual_imageheight = pdf_get_value( $pdf, "imageheight", $pdfimage);
	
	
		// Translate the coordinates of PDF lib to where we want begin drawing the actual artwork (not counting for bleed)
		pdf_save($pdf);
		pdf_translate($pdf, $bleedunits, $bleedunits);
	
	
	
		// Translate the PDF coordinates based upon our rotation.  Since the canvas is rotating we also have to change registration point (since that gets rotated too)
		if($rotatecanvas == "90"){
			pdf_rotate($pdf, -90);
			pdf_translate($pdf, -($artworkWidthInPicas), 0);
		}
		else if($rotatecanvas == "270"){
			pdf_translate($pdf, ($artworkHeightInPicas), 0);
			pdf_rotate($pdf, 90);
		}
		else if($rotatecanvas == "180"){
			pdf_translate($pdf, ($artworkWidthInPicas), ($artworkHeightInPicas));
			pdf_rotate($pdf, 180);
		}
	
	
	
		// If we are supposed to "T"ile the artwork bleed over the edges .. Then we have to be a little tricky 
		// stretch out 3 copies of the background image... 1 distored horizontally, 1 vertically, and one in the very back to cover all 4 left over corners
		// Then we will draw one more on the very top.
		if($bleedunits != 0 && $bleedtype == "T"){
	
			// To make a tile bleed we need 3 images on the background in order to make a perfect bleed
			// 1) On the very back the entire image should be stretched to occupy the full bleed area.
			// 2 & 3) .. Above the full bleed... we will place one which is only stretched horizontal and then another which is only stretched vertical 
			// This will allow the egdges more perfectly blend in with their immediate neighbor.
			// By placing the 3rd image in the very back it will prevent any white corners from appearing.
	
			// On the very back.
			pdf_save($pdf);
			pdf_translate($pdf, -($bleedunits), -($bleedunits));
			$bleed_scale_x = ($artworkWidthInPicas + $bleedunits * 2)/$Actual_imagewidth;
			$bleed_scale_y = ($artworkHeightInPicas + $bleedunits * 2)/$Actual_imageheight;
			PDF_scale($pdf, $bleed_scale_x, $bleed_scale_y);
			PDF_place_image($pdf, $pdfimage, 0, 0, 1);
			pdf_restore($pdf);
	
			// Horizontal.
			pdf_save($pdf);
			pdf_translate($pdf, -($bleedunits), 0);
			$bleed_scale_x = ($artworkWidthInPicas + $bleedunits * 2)/$Actual_imagewidth;
			$bleed_scale_y = $artworkHeightInPicas/$Actual_imageheight;
			PDF_scale($pdf, $bleed_scale_x, $bleed_scale_y);
			PDF_place_image($pdf, $pdfimage, 0, 0, 1);
			pdf_restore($pdf);
	
			// Vertical.
			pdf_save($pdf);
			pdf_translate($pdf, 0, -($bleedunits));
			$bleed_scale_x = $artworkWidthInPicas/$Actual_imagewidth;
			$bleed_scale_y = ($artworkHeightInPicas + $bleedunits * 2)/$Actual_imageheight;
			PDF_scale($pdf, $bleed_scale_x, $bleed_scale_y);
			PDF_place_image($pdf, $pdfimage, 0, 0, 1);
			pdf_restore($pdf);
			
	
	
		}
	
	
	
		// Put the image on the PDF document 
		// If the bleed type is a "stretch" then we scale the image a little differently
		if($bleedunits != 0 && $bleedtype == "S"){
	
			pdf_save($pdf);
			pdf_translate($pdf, -($bleedunits), -($bleedunits));
			$bleed_scale_x = ($artworkWidthInPicas + $bleedunits * 2)/$Actual_imagewidth;
			$bleed_scale_y = ($artworkHeightInPicas + $bleedunits * 2)/$Actual_imageheight;
			PDF_scale($pdf, $bleed_scale_x, $bleed_scale_y);
			PDF_place_image($pdf, $pdfimage, 0, 0, 1);
			pdf_restore($pdf);
	
		}
		else if($bleedunits != 0 && $bleedtype == "N"){
	
			// With a "natual" bleed the image is slightly larger than the cut line... so place the image off center a little bit.
			pdf_place_image( $pdf, $pdfimage, -($bleedunits), -($bleedunits), ($artworkWidthInPicas + $bleedunits *2)/$Actual_imagewidth);
			
		}
		else{
			pdf_place_image( $pdf, $pdfimage, 0, 0, $artworkWidthInPicas/$Actual_imagewidth);
		}
	
		pdf_close_image($pdf, $pdfimage);
	
	
	
		$ArtworkInfoObj->orderLayersByLayerLevel($SideNumber);
		$LayersSorted = $ArtworkInfoObj->SideItemsArray[$SideNumber]->layers;
	
	
		// If there are Vector Images in the Artwork... they should be drawn on top of the background image.
		// Stretch and Tile bleed types have no effect on Vector Images.
		for($j=0; $j<sizeof($LayersSorted); $j++){
	
			$LayerObj = $LayersSorted[$j];
	
			if($LayerObj->LayerType == "graphic" && !empty($LayerObj->LayerDetailsObj->vector_image_id)){
	
	
				$pdfTempVectFile = &ImageLib::getPDFfromVectorImageID($dbCmd, $LayerObj->LayerDetailsObj->vector_image_id);
	
	
	
				// Create a Virtual File for PDF lib to open.
				// Make sure it is totally unique. Use a combination of the VectorImageID and the layer/side number.
				// That is because it is possible to have 2 of the same Vector Image ID's on the same artwork... in case the clipboard created multiple instances.
				$PFV_FileName = "/pfv/pdfTemp/vectFile" . $LayerObj->LayerDetailsObj->vector_image_id . $j . $SideNumber;
				PDF_create_pvf( $pdf, $PFV_FileName, $pdfTempVectFile, "");
	
				
				// Get a PDFLib PDI object so that we can extract page(s) from it.
				$PDI_VectImg_obj = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
	
				// Now we can release our virtual file since we already have a PDF image resource
				PDF_delete_pvf($pdf, $PFV_FileName);
	
				if($PDI_VectImg_obj <= 0)
					throw new Exception("Problem getting PDI Object in function PdfDoc::GetArtworkPDF.");
	
				// Get a PDF Page Object for page #1.  If there are more than one pages, we will discard it.
				$pageNumber = 1;
				$PDI_VectPageObj = PDF_open_pdi_page($pdf, $PDI_VectImg_obj, $pageNumber, "");
				
				if($PDI_VectPageObj <= 0){
	
					// Close the PDI object.
					PDF_close_pdi($pdf, $PDI_VectImg_obj);
					
					
					// Write the file to disk so that we can try to filter it through Ghostscript to make it more compatible.
					// Do this with the origianl PDF file... not the filtered version.
					$originalPDFdata = &ImageLib::getPDFfromVectorImageID($dbCmd, $LayerObj->LayerDetailsObj->vector_image_id, false);
	
					
					$tmpfnameForPDFfix = tempnam (Constants::GetTempDirectory(), "PDFFIX");
					$fp = fopen($tmpfnameForPDFfix, "w");
					fwrite($fp, $originalPDFdata);
					fclose($fp);
					
					chmod ($tmpfnameForPDFfix, 0666);
					
					// Try to convert the File to make it more combatible.
					$convertedFileName = $tmpfnameForPDFfix . "x";
					$ConvertPDFfileComm = "gs -dSAFER -dBATCH -dNOPAUSE -dPDFCrop -sDEVICE=pdfwrite -sOutputFile=$convertedFileName $tmpfnameForPDFfix";
					
					
					// Write the command that we are about to execute to a log file.  I have found some "gs" processes going into an infinite loop.
					$logHandle = fopen(Constants::GetTempDirectory() . "/Ghostscript.log", "a");
					fwrite($logHandle, "Trying to Fix PDF in Function PdfDoc::GetArtworkPDF: " . date("l dS \of F Y h:i:s A") . ": " . $ConvertPDFfileComm . "\n");
					fclose($logHandle);
					
					WebUtil::WebmasterError("Trying to fix PDF problem : TempToFix: $tmpfnameForPDFfix : FixedFile: $convertedFileName ");
					
					WebUtil::mysystem($ConvertPDFfileComm);
									
					// Now Read the converted PDF off of disk
					$PDI_VectImg_obj = PDF_open_pdi($pdf, $convertedFileName, "", 0);
					//@unlink($tmpfnameForPDFfix);
					//@unlink($convertedFileName);
					
					$PDI_VectPageObj = PDF_open_pdi_page($pdf, $PDI_VectImg_obj, $pageNumber, "");
					
					if($PDI_VectPageObj <= 0){
						$errorMsg = "Was not able to automatically fix PDF with Vector Image ID: " . $LayerObj->LayerDetailsObj->vector_image_id . " Attempting to Fix.";
						WebUtil::WebmasterError($errorMsg);
						throw new Exception($errorMsg);
					}	
				}
				
	
				// This is the width of the original PDF file.
				$pdfDimHash = ImageLib::getDimensionsPicasFromVectorImageID($dbCmd, $LayerObj->LayerDetailsObj->vector_image_id);
				$PDFpicasWidth = $pdfDimHash["Width"];
				$PDFpicasHeight = $pdfDimHash["Height"];
				
				// Get the width in Picas of what was specified for the artwork within the editing tool.
				$specifiedPicasWidth = PdfDoc::flashToPDF($LayerObj->LayerDetailsObj->width);
				$specifiedPicasHeight = PdfDoc::flashToPDF($LayerObj->LayerDetailsObj->height);
				
				$pdfScaleWidth = $specifiedPicasWidth / $PDFpicasWidth;
				$pdfScaleHeight = $specifiedPicasHeight / $PDFpicasHeight;
					
				// The image on the editing tool is registered from the Center... and the PDF registers from the bottom left.
				$pdfXcoord = $LayerObj->x_coordinate - $LayerObj->LayerDetailsObj->width / 2;
				$pdfYcoord = $LayerObj->y_coordinate + $LayerObj->LayerDetailsObj->height / 2;
				
				// Translate the coordinates.  Flash registration point is in the middle.  PDF registration point is at the bottom left corner.
				$pdfXcoord = $pdfXcoord + $SideObj->contentwidth/2;
				$pdfYcoord = $SideObj->contentheight - $pdfYcoord  - $SideObj->contentheight/2;
				
				$pdfXcoord = round(PdfDoc::flashToPDF($pdfXcoord), 3);
				$pdfYcoord = round(PdfDoc::flashToPDF($pdfYcoord), 3);
	
				// Paste the Vector "mini PDF" onto the parent.
				pdf_save($pdf);
				
				// Translate the coordinates as if there was no rotation.
				pdf_translate($pdf, $pdfXcoord, $pdfYcoord);
		
	
				// Translate the PDF coordinates based upon the rotation of the layer.
				// Make sure to translate the coordinates first... then translate the rotation.
				if($LayerObj->rotation == "90"){
					pdf_translate($pdf, ($specifiedPicasWidth/2 - $specifiedPicasHeight/2 ), ($specifiedPicasWidth/2 + $specifiedPicasHeight/2 ));
					pdf_rotate($pdf, -90);
				}
				else if($LayerObj->rotation == "270" || $LayerObj->rotation == "-90"){
					pdf_translate($pdf, ($specifiedPicasWidth/2 + $specifiedPicasHeight/2 ), ($specifiedPicasHeight/2 - $specifiedPicasWidth/2 ));
					pdf_rotate($pdf, 90);
				}
				else if($LayerObj->rotation == "180" || $LayerObj->rotation == "-180"){
					pdf_translate($pdf, ($specifiedPicasWidth), ($specifiedPicasHeight));
					pdf_rotate($pdf, 180);
				}
	
	
		
				PDF_scale($pdf, $pdfScaleWidth, $pdfScaleHeight);			
				PDF_fit_pdi_page($pdf, $PDI_VectPageObj, 0, 0, "");
				pdf_restore($pdf);
				
				PDF_close_pdi_page($pdf, $PDI_VectPageObj);
	
				// Close the PDI object.
				PDF_close_pdi($pdf, $PDI_VectImg_obj);
	
	
			}
		}
	
	
	
	
		pdf_save($pdf);
	
		// Draw the border on the cut line
		// However, we never want to do this if an Artwork has a Marker Image.   The Marker Image has its own lines and should be a direct substitue for these PDF functions
		if($ShowCutLine && !$SideObj->markerimage){
			pdf_setlinewidth ( $pdf, 0.4);
			pdf_rect($pdf, 0, 0, $artworkWidthInPicas, $artworkHeightInPicas);
			pdf_stroke($pdf);	
		}
	
		if($showBleedSafeLines && !$SideObj->markerimage){
		
			// Show bleed zone
			pdf_setlinewidth ( $pdf, 0.6);  //Make the line twice as thick... since it will get cut in half.   The other half is hanging over the crop mark of the page.
			pdf_rect($pdf, -$bleedunits, -$bleedunits, ($artworkWidthInPicas + $bleedunits*2), ($artworkHeightInPicas + $bleedunits*2));
			pdf_stroke($pdf);
			
			// Now draw a dashed white line on top of the Black solid line
			pdf_save($pdf);
			pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 0);
			pdf_setdash ($pdf, 2.0, 2.0);
			pdf_setlinewidth ( $pdf, 0.6);  //Make the line twice as thick... since it will get cut in half.   The other half is hanging over the crop mark of the page.
			pdf_rect($pdf, -$bleedunits, -$bleedunits, ($artworkWidthInPicas + $bleedunits*2), ($artworkHeightInPicas + $bleedunits*2));
			pdf_stroke($pdf);
			pdf_restore($pdf);
			
			
			// Show Safe Zone
			pdf_setlinewidth ( $pdf, 0.3);
			pdf_rect($pdf, $bleedunits, $bleedunits, ($artworkWidthInPicas - $bleedunits*2), ($artworkHeightInPicas - $bleedunits*2));
			pdf_stroke($pdf);
			
			// Now draw a dashed white line on top of the Black solid line
			pdf_save($pdf);
			pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 0);
			pdf_setdash ($pdf, 2.0, 2.0);
			pdf_setlinewidth ( $pdf, 0.3);
			pdf_rect($pdf, $bleedunits, $bleedunits, ($artworkWidthInPicas - $bleedunits*2), ($artworkHeightInPicas - $bleedunits*2));
			pdf_stroke($pdf);
			pdf_restore($pdf);
		}
	
	
		PdfDoc::_drawShapesOnPDF($pdf, $ShapeObjectsArr);
	
	
		pdf_restore($pdf);
	
	
		// Since this is not a Variable Data PDF file... there are is not a Artwork Variable Mapping Object.
		$artworkMappingsObj = null;
	
		
		PdfDoc::_drawTextBlocksOnPDFside($pdf, $ArtworkInfoObj, $SideNumber, $artworkMappingsObj);
	
	
		pdf_restore($pdf);  //For the start coordinates
	
	
		pdf_end_page($pdf);
	
		PDF_end_document($pdf, "");
	
		$data = pdf_get_buffer($pdf);
		
		
		// Write the file to disk to caching purposes... we may be able to re-open the PDF if the artwork has changed.
		$fp = fopen($pdfFileName, "w");
		fwrite($fp, $data);
		fclose($fp);
		
		chmod($pdfFileName, 0666);
	
	
		return $data;
	}
	
	
	// There may be a number of shapes that we need to draw on top of the artwork
	// For example, for an Envelope with windows we may need to draw a rectangle where the hole will be punched out
	static function _drawShapesOnPDF(&$pdf, $ShapeObjectsArr){
	
	
		foreach($ShapeObjectsArr as $thisShapeObj){
	
	
			$lineColorCode = $thisShapeObj->getLineColor();
			$lineColorObj = ColorLib::getRgbValues($lineColorCode, false);
	
			$fillColorCode = $thisShapeObj->getFillColorRGB();
			$fillColorObj = ColorLib::getRgbValues($fillColorCode, false);
	
	
	
			//--- PDFlib can create fill and Stroke Opacity with the function PDF_create_gstate
			//--- However these methods are only supported in PDF Version 1.4 and above (which was release in 2003 sometime.
			//--- I am not sure what version Xerox is using for Ripping... or all of the customer's versions of Acrobat
			//--- For now we will avoid transparancies here.
			
			// The only way we are honoring Transparancy is if it is set to 0%, then we don't draw the shape at all.
			$lineAlphaVal = $thisShapeObj->getLineAlpha();
			$fillAlphaVal = $thisShapeObj->getFillAlpha();
			
			
			
			pdf_save($pdf);
			pdf_translate($pdf, $thisShapeObj->getXCoord(), $thisShapeObj->getYCoord());
			pdf_rotate($pdf, $thisShapeObj->getRotation());
			
			
			if($thisShapeObj->getLineStyle() == "solid")
				pdf_setdash ($pdf, 0, 0);
			else if($thisShapeObj->getLineStyle() == "dashed")
				pdf_setdash ($pdf, 2.0, 2.0);
			else if($thisShapeObj->getLineStyle() == "dotted")
				pdf_setdash ($pdf, 0.3, 1.2);
			else if($thisShapeObj->getLineStyle() == "none")
				$lineAlphaVal = 0;
			else
				throw new Exception("Illegal Line Type when generating Shapes on PDF");
			
			// If there is now line thickness, then don't draw a line.
			if($thisShapeObj->getLineThickness() == 0)
				$lineAlphaVal = 0;
	
				
			if($thisShapeObj->getShapeName() == "rectangle"){
			
				if($fillAlphaVal > 0){
				
					if($thisShapeObj->fillColorIsCMYK())
						pdf_setcolor($pdf, "both", "cmyk", ($thisShapeObj->getFillColorCMYK("c")/100), ($thisShapeObj->getFillColorCMYK("m")/100), ($thisShapeObj->getFillColorCMYK("y")/100), ($thisShapeObj->getFillColorCMYK("k")/100));
					else
						pdf_setcolor($pdf, "both", "rgb", ($fillColorObj->red/255), ($fillColorObj->green/255), ($fillColorObj->blue/255), 0);
						
					pdf_rect($pdf, 0, 0, $thisShapeObj->getWidth(), $thisShapeObj->getHeight());
					pdf_fill($pdf);
				}
				
				if($lineAlphaVal > 0){
					pdf_setlinewidth ( $pdf, $thisShapeObj->getLineThickness());
					pdf_setcolor($pdf, "both", "rgb", ($lineColorObj->red/255), ($lineColorObj->green/255), ($lineColorObj->blue/255), 0);
					pdf_rect($pdf, 0, 0, $thisShapeObj->getWidth(), $thisShapeObj->getHeight());
					pdf_stroke($pdf);
				}
	
			}
			else if($thisShapeObj->getShapeName() == "circle"){
				
				if($fillAlphaVal > 0){
					if($thisShapeObj->fillColorIsCMYK())
						pdf_setcolor($pdf, "both", "cmyk", ($thisShapeObj->getFillColorCMYK("c")/100), ($thisShapeObj->getFillColorCMYK("m")/100), ($thisShapeObj->getFillColorCMYK("y")/100), ($thisShapeObj->getFillColorCMYK("k")/100));
					else
						pdf_setcolor($pdf, "both", "rgb", ($fillColorObj->red/255), ($fillColorObj->green/255), ($fillColorObj->blue/255), 0);
						
					PDF_circle($pdf, 0, 0, $thisShapeObj->getRadius());
					pdf_fill($pdf);
				}
				
				if($lineAlphaVal > 0){
					pdf_setlinewidth ( $pdf, $thisShapeObj->getLineThickness());
					pdf_setcolor($pdf, "both", "rgb", ($lineColorObj->red/255), ($lineColorObj->green/255), ($lineColorObj->blue/255), 0);
					PDF_circle($pdf, 0, 0, $thisShapeObj->getRadius());
					pdf_stroke($pdf);
				}
	
			}
			else
				throw new Exception("Illegal Shape Name called when generating Shapes on PDF");
			
	
			pdf_restore($pdf);
		}
	
	
	}
	
	
	// Make sure that PDF cursor has been tranlated to the lower-left corner of the cut-line
	// Will draw all text layers for that Side
	// It only draws 1 copy... so if you need to draw in many positions over a giant parent sheet
	// Then make sure to translate to the lower left corneer of the cutline every time you call this function
	static function _drawTextBlocksOnPDFside(&$pdf, &$artObj, $sideNumber, $artworkVariableMappingObj){
	
		
		$SideObj = $artObj->SideItemsArray[$sideNumber];
	
		// Convert the Content size into PDF coordinates
		$artWidthPicas = PdfDoc::flashToPDF($SideObj->contentwidth);
		$artHeightPicas = PdfDoc::flashToPDF($SideObj->contentheight);
	
		// We need to Sort all layers by their Layer Level
		$SideObj->orderLayersByLayerLevel();
		$LayersSorted = $SideObj->layers;
		
		
		
	
		// Now loop through all of the layers on the side again.  This time just draw the text layers on the PDF doc
		for($j=0; $j<sizeof($LayersSorted); $j++){
	
			if($LayersSorted[$j]->LayerType == "text"){
	
				$textProperties = array();
	
				$LayerObj = $LayersSorted[$j];
	
				// Corrdinates in PDF are 1/72 inch.  in Flash they are 96 dpi
				$X_coordinate = PdfDoc::flashToPDF($LayerObj->x_coordinate);
				$Y_coordinate = PdfDoc::flashToPDF($LayerObj->y_coordinate);
	
				// Translate the coordinates.  Flash registration point is in the middle.  PDF registration point is at the bottom left corner.
				$X_coordinate = $X_coordinate + $artWidthPicas/2;
				$Y_coordinate = $artHeightPicas - ($Y_coordinate  + $artHeightPicas/2);
	
				// Reverse the roation
				$LayerObj->rotation =  360 - $LayerObj->rotation;
	
				// Create a Hash containing the font properties so that we can send it to the Text drawing function
				$textProperties["bold"] = $LayerObj->LayerDetailsObj->bold;
				$textProperties["italics"] = $LayerObj->LayerDetailsObj->italics;
				$textProperties["underline"] = $LayerObj->LayerDetailsObj->underline;
	

				// Setup an Empty Variable Size Restriction Object.  It may get overriden if the text layer is Variable... and it has a Size Restriction set on it.
				$variableSizeRestrictObj = null;
				
				// If this is a Variable Data File... all variable will have been substituted by this point.
				// Based upon the Layer Level (which is a unique ID for the layer) we are going to ask the Artwork Mappings Object is there are any size restrictions on this Text Layer.
				if($artworkVariableMappingObj != null)
					$variableSizeRestrictObj = $artworkVariableMappingObj->getSizeRestrictionObjFromLayerLevel($sideNumber, $LayerObj->level);
					
				$colorCodeOfLayer = 0;
					
				if(sizeof($SideObj->color_definitions) > 0){
					
					// If there are color definitions on this side, then the color code of the layer corresponds to a Color definition ID.
					// In case there is not a match between the Layer's color number and the color definitions, just pick a default.  This should never happen though.
					
					foreach($SideObj->color_definitions as $thisColorDefObj){
						
						// If the loop doesn't break below, then this will become the default color (last element in the array).
						$colorCodeOfLayer = $thisColorDefObj->colorcode; 
						
						if($thisColorDefObj->id == $LayerObj->LayerDetailsObj->color){
							$colorCodeOfLayer = $thisColorDefObj->colorcode;
							break;
						}
					}
				}
				else{
					$colorCodeOfLayer = $LayerObj->LayerDetailsObj->color;
				}
				
				// Get RGB values into a neatly formated Object .. The second parameter tells us to get the values in Dec format (not hex)
				$textColorCodeObj = ColorLib::getRgbValues($colorCodeOfLayer, false);
	
	
				// The function will draw text on the pdf resource we are passing by reference.
				PdfDoc::PDFDrawTextBlock($LayerObj->LayerDetailsObj->message, $LayerObj->LayerDetailsObj->font, $LayerObj->LayerDetailsObj->size, $LayerObj->rotation, $LayerObj->LayerDetailsObj->align, $textColorCodeObj, $pdf, $X_coordinate, $Y_coordinate, $textProperties, $variableSizeRestrictObj);
			}
	
		}
	
	}
	
	
	
	
	
	// If any error message is sent into this function it will Print an Error message a PDF (since the requestee wants an PDF) .. and then immediately exit the script.
	static function PDFErrorMessage ($messageLine1, $messageLine2="") {
	
		print $messageLine1 . "<br>" . $messageLine2;
		WebUtil::WebmasterError("PDF Image Generation Error", $messageLine1 . $messageLine2);
	
		exit;
	}
	
	
	// Will look into the Project info Object etc, and figure out what name to give the PDF
	static function GetFileNameForPDF(&$projectOrderedObj, $prefix){
	
	
		// A description in file name should tell us what order and project we are dealing with
		$projectDescPDF = $projectOrderedObj->getOrderID() . "-P" . $projectOrderedObj->getProjectID();
			
		$PDFfilename = $prefix . "ARTWORK_" . $projectOrderedObj->getQuantity() . "_" . $projectDescPDF . ".pdf";
	
		return $PDFfilename;
	
	}
	
	
	
	static function PDFDrawTextBlock($TextString, $fontName, $fontSize, $rotationAngle, $alignment, $fontColorObj, &$PdfResource, $Layer_coord_x, $Layer_coord_y, $textProperties, &$variableSizeRestrictObj){
	
		// PDF Lib can't handle UTF-8 Characters.
		// Make sure never to call this function twice on the same string.
		$TextString = utf8_decode($TextString);
		
		// Don't create a text layer if there is nothing on the text layer.  Make sure to remove line breaks before trimming.  Line breaks don't count as content.
		if(trim(preg_replace("/!br!/i", "", $TextString)) == "")
			return;
		
		// Convert the angle so it is always a number between 0 and 359
		if($rotationAngle > 359 || $rotationAngle < -359)
			$rotationAngle = intval($rotationAngle % 360);
		if($rotationAngle < 0)
			$rotationAngle = 360 + $rotationAngle;
	
	
		// When the function imagettftext is called, text will be positioned on the BasePoint of the font.  Which is the lower-left corner roughly
		// The problem is that the flash program positions the Text layer at the very top of the Layer box.  It is not so easy to figure out where this is relative to the base point.
		// There are other things asscociated with a font such as Ascent, Descent, Ascender, Desender, Line Spacing ect.  You will need to understand the principals of font creation... visit http://pfaedit.sourceforge.net/overview.html
		// If we can get the Ascent of the font then we can figure out where the Y coordinate of the Base Point should be.  Use trig function to account for the angle.
		// The PDF function to give me the Ascent and Descent of the True Type font didnt seem to work correctly so I created a setup file listing all of the font properties ./library/font_measurements.php
		// This $FntDef is the array from the setup file
	
		global $FntDef;
	
		$TextPropertiesStr = "";
	
		// Set up a string which we can append to the end of the font name to load properties like bold, italic ect.
		// We use the convenstion FONTNAMEb.ttf for bold ... FONTNAMEi.ttf for italic .... and FONTNAMEbi.ttf for bold and italic
		if($textProperties["bold"] == "yes")
			$TextPropertiesStr = "b";
		if($textProperties["italics"] == "yes")
			$TextPropertiesStr .= "i";
	
	
		// The font file is bases off of the name of the font and the properties... EX.   Decker.ttf, Deckerb.ttf, Deckeri.ttf, and Deckerbi.ttf
		$FontFileName = $fontName . $TextPropertiesStr;
	
		// In case we have depreciated any fonts or changed any of the attributes it is allowed to have
		$FontFileName = PossiblySubstitueFont($FontFileName);
	
		if(!isset($FntDef[$FontFileName]))
			PdfDoc::PDFErrorMessage ("There is no font definition for " . $FontFileName,  "Please report this to webmaster.");
		
	
		// Instead of trying to get the file pdflib.upr to work (which wasnt totally easy).. I map the font name to the ttf file in this dynamic call --#
		pdf_set_parameter( $PdfResource, "FontOutline", $FontFileName . "=" . $FontFileName . ".ttf");
	
		$fontRes = pdf_findfont($PdfResource, $FontFileName, "winansi", 1);
	
		if(!$fontRes)
			PdfDoc::PDFErrorMessage ("There is no font definition for " . $fontName,  "Please report this to webmaster.");
		
	
		// This accounts for the "leading" that is set in the paragraph within the flash program.  For some reason this calculation works really well.
		$spacingRatio = ($FntDef[$FontFileName]["ascent"] + $FntDef[$FontFileName]["descent"]) / $FntDef[$FontFileName]["unts_per_em"];
	
		$fontAscent = $FntDef[$FontFileName]["ascent"]/$FntDef[$FontFileName]["unts_per_em"] * PdfDoc::flashToPDF($fontSize);
		$fontDescent = $FntDef[$FontFileName]["descent"]/$FntDef[$FontFileName]["unts_per_em"] * PdfDoc::flashToPDF($fontSize);
	
		// Set the size and color 
		pdf_setfont ( $PdfResource, $fontRes, PdfDoc::flashToPDF($fontSize));
	
	
		// Let the printing press know to print gray/black colors with pure black ink/toner
		if($fontColorObj->red == $fontColorObj->green && $fontColorObj->green == $fontColorObj->blue)
			$ColorSpace = "gray";
		else
			$ColorSpace = "rgb";
	
		pdf_setcolor($PdfResource, "both", $ColorSpace, $fontColorObj->red/256, $fontColorObj->green/256, $fontColorObj->blue/256, 0);
	
		// Save the state of the PDF document because we are going to translate the coord system.. and we want to put it back to normal when done.
		pdf_save($PdfResource);
		pdf_translate($PdfResource, $Layer_coord_x, $Layer_coord_y);
		pdf_rotate($PdfResource, $rotationAngle);
	
		// Put each line into separate array elements.
		$IndividualLinesArr = split("!br!", $TextString);
	
		// This is where the base point of the first letter in the first line should go
		$New_Coord_X = 0;
		$New_Coord_Y = -($fontAscent);
	
		// Loop through all lines within the text block 
		for($i=0; $i<sizeof($IndividualLinesArr); $i++){
	
			// We only need to calculate new starting coordinates for the text line if we are not on the first line 
			// The coordinates are figured out by measuring the distance from the line above it.
			if($i>0){
				// This is a fairly crude calculation.  It accounts for the Leading set for the Paragraph properties in the flash program.
				$New_Coord_Y -= PdfDoc::flashToPDF($fontSize) * $spacingRatio;
			}
	
			// If the font name is a barcode then we may need to add parity bits to the text.
			if(strtoupper($fontName) == "BARCODE128")
				$IndividualLinesArr[$i] = WebUtil::GetBarCode128($IndividualLinesArr[$i]);
			else if(strtoupper($fontName) == "CODE39")
				$IndividualLinesArr[$i] = WebUtil::GetBarCode39($IndividualLinesArr[$i]);
			else if(strtoupper($fontName) == "POSTNET" || strtoupper($fontName) == "PLANET")
				$IndividualLinesArr[$i] = WebUtil::GetBarCodePostnet($IndividualLinesArr[$i], true);
			else if(strtoupper($fontName) == "POSTNETNOPARITY" || strtoupper($fontName) == "PLANETNOPARITY")
				$IndividualLinesArr[$i] = WebUtil::GetBarCodePostnet($IndividualLinesArr[$i], false);
	
	
	
			// If this is a Variable Data layer with Size Restrictions... Draw the Text block within Boundaries... or create Line Breaks, etc.
			if($variableSizeRestrictObj != null && $variableSizeRestrictObj->checkIfSizeRestrictionExists()){
	
				$sizeRestrictionType = $variableSizeRestrictObj->getRestrictionType();
				$sizeRestrictionLimit = $variableSizeRestrictObj->getRestrictionLimit();
				$sizeRestrictionAction = $variableSizeRestrictObj->getRestrictionAction();
	
				if($sizeRestrictionType == "GREATER_THAN_PICAS_WIDTH" && $sizeRestrictionAction == "SHRINK_FONT_SIZE"){
	
					// We have to keep the Font within a certain rectangle.
					// Let's figure out where the bottom of the Rectangle is now (based upon the current font size)
					// We will bottom align our bouding box to the current font size... set the maximum width for the box... and then let the rectangle height go very high (we are setting the height of the box to 1,000... which is a bit extreme).
					// Set the BoxSize to the Picas Width and a Height of 0.  A height of Zero means that only the String Width is considered (and possibly scaled to fit within).
					
					if(strtoupper($alignment) == "CENTER")
						$pdfTextParameter = 'boxsize {'.$sizeRestrictionLimit.' 1000} position {50 0} fitmethod auto';
					else if(strtoupper($alignment) == "RIGHT")
						$pdfTextParameter = 'boxsize {'.$sizeRestrictionLimit.' 1000} position {100 0} fitmethod auto';
					else
						$pdfTextParameter = 'boxsize {'.$sizeRestrictionLimit.' 1000} position {0 0} fitmethod auto';
						
					// Add to the text attibutes if we are supposed to add an underline.
					if($textProperties["underline"] == "yes")
						$pdfTextParameter .= " underline";
						
					// The X coordinate is where the lower left-corner of the bounding box exists.
					// So in case we are doing a center or right aligned text... the xcoord will have to be offset by the width of the box size.
					if(strtoupper($alignment) == "CENTER")
						$xOffsetBox = $New_Coord_X - ($sizeRestrictionLimit / 2);
					else if(strtoupper($alignment) == "RIGHT")
						$xOffsetBox = $New_Coord_X - $sizeRestrictionLimit;
					else
						$xOffsetBox = $New_Coord_X;
					
					PDF_fit_textline($PdfResource, $IndividualLinesArr[$i], $xOffsetBox, $New_Coord_Y, $pdfTextParameter);
	
				}
				else{
					throw new Exception("Illegal Size Restriction Type / Action Combo in function call PdfDoc::_drawTextBlocksOnPDFside");
				}
			
	
			}
			else{	
			
	
			
				// ----- Note... at the time I developed this I was using PDFLib 5.0 and there was a function available (like "PDF_fit_textline")... so this code below should be re-written at some point.
				
				
				// We need the current line with so that we can systematically center or right justify the lines as we loop through the text block.  PDF lib doesnt have a built in function for this :(
				$CurrentLineWidth = pdf_stringwidth ( $PdfResource, $IndividualLinesArr[$i], $fontRes, PdfDoc::flashToPDF($fontSize));
	
				// The current line that we are on needs be be shifted to account for the aligment 
				// By calculating the max line with, the current line width, and the angle of rotation we know how far to slide the text line
				if(strtoupper($alignment) == "CENTER")
					$TextLinePlacement_X = $New_Coord_X - $CurrentLineWidth/2;
				else if(strtoupper($alignment) == "RIGHT")
					$TextLinePlacement_X = $New_Coord_X - $CurrentLineWidth;
				else
					$TextLinePlacement_X = 0;
	
	
				// If this text block is underlined then we are going to draw a line underneath the text.
				if($textProperties["underline"] == "yes"){
	
					// The underline typically falls 1/2 way between the baseline and the descent.  So that is where we will put it for all fonts.
					$Ycoord_for_underline = $New_Coord_Y - $fontDescent/2;
	
					// A little adjustment for the size of the line.  The thickeness will grow with the font size.
					pdf_setlinewidth($PdfResource, ($fontSize / 65 + 0.2));
	
					pdf_moveto($PdfResource, $TextLinePlacement_X, $Ycoord_for_underline);
					pdf_lineto($PdfResource, ($TextLinePlacement_X + $CurrentLineWidth), $Ycoord_for_underline);
					pdf_stroke($PdfResource);
				}
	
	
				// Write the text out
				pdf_show_xy($PdfResource, $IndividualLinesArr[$i], $TextLinePlacement_X, $New_Coord_Y);
			}
		
		}
	
		// Put the coordinates back to normal again
		pdf_restore($PdfResource);
	}
	
	
	
	// Will return a 3D array of PDF documents for re-order cards (by reference)
	// The 1st key to the array is the Account ID the re-order card is from
	// The 2nd Key is the product it is for.  We may be batching up multiple Products into a single artwork (like double-sided and single-sided envelopes)
	// The 3rd Keys are for the bleed Units, the Barcode Locations, and the binary PDF file
	// Must people will use the default Account ID which is the Reorder Card
	// But if the customer came from a Reseller... then it will use the resellers "re-order" card
	// If it does not belong to a reseller then the key labeled "default" will be used
	// *** Don't forgot to close the PDI pages when you are done with the Array
	static function &_getArrayOfReorderCards(DbCmd $dbCmd, &$pdf, $projectIDarr, $rotateCanvas){
	
		$reorderPDFarr = array();
	
		foreach($projectIDarr as $ProjectOrderID){
	
			// We need to find out what Saved Project account we will get the "reorder" card from
			$ProjectUserID = Order::GetUserIDFromProject($dbCmd, $ProjectOrderID);
			$AccountTypeObj = new AccountType($ProjectUserID, $dbCmd);
			$AccountUserID = $AccountTypeObj->GetAccountUserID();
			$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $ProjectOrderID, "admin");
			$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
			
			// If a Reorder card hasn't been defined for this Account ID yet... then we have to extract or create one
			if(!isset($reorderPDFarr["$AccountUserID"]))
				$reorderPDFarr["$AccountUserID"] = array();
				
			
			if(!isset($reorderPDFarr["$AccountUserID"]["$ProductID"])){
				
				$reorderPDFarr["$AccountUserID"]["$ProductID"] = array();
				
				// Get the "Reorder Card" for this AccountType
				$ArtInfoObj =& $AccountTypeObj->GetArtworkObjectForReorder($ProductID);
			
				// Find out how many bleed units there are in the default PDF setting.
				$profileObj = new PDFprofile($dbCmd,$productionProductID);
				$profileObj->loadProfileByName($profileObj->getDefaultProfileName());
				$ReOrderCardBleedUnits = $profileObj->getBleedUnits();
	
				$reorderPDFarr["$AccountUserID"]["$ProductID"]["BleedUnits"] = $ReOrderCardBleedUnits;
				
				
				// Look for the word "barcode" within a text layer in the artwork
				// If we find it, then record those coordinates as the position we will be drawing the barcode at.
				// It we don't find a textlayer with that string in it... then just set the X&Y coordinates of the barcode to 0's, so our program will know to use default values or whatever
				// If we find it... then we also want to get rid of the text layer so it is not drawn... we are only interested in the coordinates.
				// Only bother searching on the first side
				$reorderPDFarr["$AccountUserID"]["$ProductID"]["BarcodeX"] = 0;
				$reorderPDFarr["$AccountUserID"]["$ProductID"]["BarcodeY"] = 0;
				
				foreach($ArtInfoObj->SideItemsArray[0]->layers as $layerObj){
					if($layerObj->LayerType == "text" && preg_match("/barcode/i", $layerObj->LayerDetailsObj->message)){
						// convert between our Center based Artwork in XML to Bottom-left coordinates for PDF.
						$reorderPDFarr["$AccountUserID"]["$ProductID"]["BarcodeX"] = ($ArtInfoObj->SideItemsArray[0]->contentwidth / 2) + $layerObj->x_coordinate;
						$reorderPDFarr["$AccountUserID"]["$ProductID"]["BarcodeY"] = ($ArtInfoObj->SideItemsArray[0]->contentheight / 2) - $layerObj->y_coordinate; 
						
						$ArtInfoObj->RemoveLayerFromArtworkObj(0, $layerObj->level);
						break;
					}
				}
				
	
				// Gets a binary PDF file in memory. 
				$ReorderPDF = PdfDoc::GetArtworkPDF($dbCmd, 0, $ArtInfoObj, "N", $ReOrderCardBleedUnits, $rotateCanvas, false, false, array());
			
				// Put the PDF Binary data into a PDF lib virtual file
				$PFV_FileName = "/pfv/pdf/ReorderCard" . $AccountUserID . "-" . $ProductID . microtime() . $ProjectOrderID;
				PDF_create_pvf( $pdf, $PFV_FileName, $ReorderPDF, "");
				
				// Put the Reorder PDF file into a PDI object that we can extract the 1st page from.
				$reorderPDFarr["$AccountUserID"]["$ProductID"]["PDF_PDI_doc"] = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
				$reorderPDFarr["$AccountUserID"]["$ProductID"]["PDF_PDI_page"] = PDF_open_pdi_page($pdf, $reorderPDFarr["$AccountUserID"]["$ProductID"]["PDF_PDI_doc"], 1, "");
				PDF_delete_pvf($pdf, $PFV_FileName);
	
			}
		}
		
		return $reorderPDFarr;
	
	}
	
	
	// PDF Pointer should be positioned at the bottom left of the card (taking into account bleed area).
	// The canvas should already be rotated (if needed).
	// $barCodeX, $barCodeY are in Flash Coordinates, not Picas.
	// ReorderCardWidth and Height are in picas.
	static function _drawBarcodeAndThumbnailImage(DbCmd $dbCmd, &$pdf, &$projectObj, $Barcode_X, $Barcode_Y, $ReorderCardWidth, $ReorderCardHeight){
	
		$Barcode_X = PdfDoc::flashToPDF($Barcode_X );
		$Barcode_Y = PdfDoc::flashToPDF($Barcode_Y );
		
	
		$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
		$BarcodeFont = pdf_findfont($pdf, "C128M", "winansi", 1);
	
		$productObj = $projectObj->getProductObj();
		
		// Some Products require us to draw a thumbnail image on top of the re-order card.  This makes Production go easier for some things.
		if($productObj->checkIfThumbnailImageShouldPrintOnReorderCard()){
		
			$ArtworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());
			
			$artworkDPI = $ArtworkInfoObj->SideItemsArray[0]->dpi;
			
			$pixelWidthOfArtwork = $ArtworkInfoObj->SideItemsArray[0]->contentwidth / 96 * $artworkDPI;
			$pixelHeightOfArtwork = $ArtworkInfoObj->SideItemsArray[0]->contentheight / 96 * $artworkDPI;
			
			$thumbPixWidth = round($pixelWidthOfArtwork * $productObj->getScaleOfThumbnailImageOnReorderCard() / 100);
			$thumbPixHeight = round($pixelHeightOfArtwork * $productObj->getScaleOfThumbnailImageOnReorderCard() / 100);
			
			$tempThumbnail = ArtworkLib::GetArtworkImageWithText($dbCmd, 0, $ArtworkInfoObj, "V", 0, 0, false, false, array(), $thumbPixWidth, $thumbPixHeight);
	
			$thumbImage = PDF_load_image ($pdf, "jpeg", $tempThumbnail, "");
			
			$imageScale = 72/$artworkDPI;
			
			pdf_place_image($pdf, $thumbImage, $productObj->getXcoordOfThumbnailImageOnReorderCard(), $productObj->getYcoordOfThumbnailImageOnReorderCard(), $imageScale);
			pdf_close_image($pdf, $thumbImage);
			
			unlink($tempThumbnail);	
	
		}
		
		
		// We may have have defined a precise place to place the barcode... If not then draw one in the default location.
		if($Barcode_X == 0){
	
			// Otherwise pick a default location
			// Move it away from the top-right edge, so that it has a little room to fit
			$Barcode_X = $ReorderCardWidth - 110;
			$Barcode_Y = $ReorderCardHeight - 40;
		}
	
		// Draw a white Box to cover up the "Reorder Card" and go under the barcode
		pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 0);
		pdf_rect($pdf, $Barcode_X, ($Barcode_Y - 5), 105, 35);
		pdf_fill($pdf);
	
		$OrderID = $projectObj->getOrderID();
		
		$arrivalDateDesc = TimeEstimate::formatTimeStamp($projectObj->getEstArrivalDate(), "M jS");
		
	
		// Put the order description ... Just the Project number and the Quantity and Shipping Method
		$ProjectQuantity = $projectObj->getQuantity();
		
		// Color code the description depending on the shipping priority
		$projectShippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $OrderID);
		$shippingChoicesObj = new ShippingChoices(Order::getDomainIDFromOrder($OrderID));
		$expeditedFlag = $shippingChoicesObj->getPriorityOfShippingChoice($projectShippingChoiceID);
		
		PdfDoc::_setPDFcolorBasedUponExpeditedFlag($pdf, $expeditedFlag);

		pdf_setfont ( $pdf, $DeckerFont, 8);
		pdf_show_xy($pdf, "P" . $projectObj->getProjectID()  . " - " . $ProjectQuantity . " - " . $arrivalDateDesc, ($Barcode_X + 6), ($Barcode_Y -2));
	
		// Put the barcode
		pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
		pdf_setfont ( $pdf, $BarcodeFont, 14);
		pdf_show_xy($pdf, WebUtil::GetBarCode128("P" . $projectObj->getProjectID()), ($Barcode_X + 6), ($Barcode_Y + 5));
		
		
		
	
	}
	
	
	
	static function _placeReorderCardOnPageAndDrawBarcode(DbCmd $dbCmd, &$pdf, &$ReorderCards_PDFarr, $pdfParameterObj, $AccountUID, $projectID, $productIDforReorderCard){
	
	
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectID);
	
		$reOrderCardBleedUnits = $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["BleedUnits"];
	
		// We are going to place the reorder card on the first column/first row  ... It will overlap an existing card
		// We just need to find out the bottom and left margins from the current PDF profile we are generating
		// We also need to subtract the amount of bleed pixels was used for the "Reorder card"
		$ReorderCard_X = PdfDoc::inchesToPDF($pdfParameterObj->getLmargin()) - $reOrderCardBleedUnits;
		$ReorderCard_Y = PdfDoc::inchesToPDF($pdfParameterObj->getBmargin()) - $reOrderCardBleedUnits;
	
		$canvasW = PdfDoc::inchesToPDF($pdfParameterObj->getUnitWidth()) + $reOrderCardBleedUnits * 2;
		$canvasH = PdfDoc::inchesToPDF($pdfParameterObj->getUnitHeight()) + $reOrderCardBleedUnits * 2;
	
		$ReorderCardWidth = PDF_get_pdi_value($pdf, "width", $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["PDF_PDI_doc"], $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["PDF_PDI_page"], 0);
		$ReorderCardHeight = PDF_get_pdi_value($pdf, "height", $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["PDF_PDI_doc"], $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["PDF_PDI_page"], 0);
	
		pdf_save($pdf);
		
		pdf_translate($pdf, $ReorderCard_X, $ReorderCard_Y);
	
		// Translate the PDF coordinates based upon our rotation.  Since the canvas is rotating we also have to change registration point (since that gets rotated too)
		if($pdfParameterObj->getRotatecanvas(true) == "90"){
			pdf_rotate($pdf, -90);
			pdf_translate($pdf, -($canvasW), 0);
		}
		else if($pdfParameterObj->getRotatecanvas(true) == "270"){
			pdf_translate($pdf, ($canvasH), 0);
			pdf_rotate($pdf, 90);
		}
		else if($pdfParameterObj->getRotatecanvas(true) == "180"){
			pdf_translate($pdf, ($canvasW), ($canvasH));
			pdf_rotate($pdf, 180);
		}
	
		
		// If we have chosen to Force the aspect ratio, then make sure that the PDF fills in the Specified Width/Height of the PDF profile, even if it means distorting.
		if($pdfParameterObj->getForceAspectRatio()){
	
			$artwork_scale_x = $canvasW / $ReorderCardWidth;
			$artwork_scale_y = $canvasH / $ReorderCardHeight;
	
			PDF_scale($pdf, $artwork_scale_x, $artwork_scale_y);	
		}
		
		
		PDF_fit_pdi_page($pdf, $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["PDF_PDI_page"], 0, 0, "");
	
	
		$barcodeX = $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["BarcodeX"];
		$barcodeY = $ReorderCards_PDFarr["$AccountUID"]["$productIDforReorderCard"]["BarcodeY"];
		
		
		PdfDoc::_drawBarcodeAndThumbnailImage($dbCmd, $pdf, $projectObj, $barcodeX, $barcodeY, $ReorderCardWidth, $ReorderCardHeight);
	
		
		pdf_restore($pdf);
		
		
	
		// If there are any Global Shapes... put them on the very top of the PDF.
		// The re-order Card may have covered up any previous Shape Objects.
		$shapesContainerObjGlobal = $pdfParameterObj->getShapeContainerObjGlobal();
		$shapesGlobalObjectsArr = $shapesContainerObjGlobal->getShapeObjectsArr($pdfParameterObj->getPdfSideNumber());
		PdfDoc::_drawShapesOnPDF($pdf, $shapesGlobalObjectsArr);
	
	}
	
	
	// Show Page number in the lower left corner
	// Pass in an ExpitedFlag string...  Must be one of the Constant Choices in ShippingChoices::NORMAL_PRIORITY
	// $priorityIndicator is a single Character "N"ormal or "U"rgent
	static function _showPageNumberOnPage(&$pdf, $PageNumber, $expeditedFlag, $priorityIndicator){
	
		pdf_save($pdf);
		
		// Anything other than a NORMAL priority will be in Bold
		if($expeditedFlag == ShippingChoices::NORMAL_PRIORITY){
			$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
			pdf_setfont ( $pdf, $DeckerFont, 8);
		}
		else{
			$boldCommerical = pdf_findfont($pdf, "Commercialb", "winansi", 1);
			pdf_setfont ( $pdf, $boldCommerical, 9);
		}
		
		PdfDoc::_setPDFcolorBasedUponExpeditedFlag($pdf, $expeditedFlag);
		
		// If the priority is Urgent then we want to put a Solid Square background with White Knockout text.
	
		if($priorityIndicator == "U"){
		
			// The bigger the page number... the wider the rectangle.
			$backgroundBoxWidth = strlen($PageNumber) * 5;
			$backgroundBoxWidth += 5; // To account for the margins around the text.
	
		
			pdf_rect($pdf, 12, 10, $backgroundBoxWidth, 10 );
			pdf_fill($pdf);
			
			// Set the color to white for the text.
			pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 0);
		}
	
	
		pdf_show_xy($pdf, $PageNumber, 15, 12);
		pdf_restore($pdf);
	}
	
	
	
	// "URGENT" = Green, "HIGH" = Red, "ELEVATED" = blue, "MEDIUM" = brown, "NORMAL" = black
	static function _setPDFcolorBasedUponExpeditedFlag(&$pdf, $expeditedFlag){
	
		if($expeditedFlag == ShippingChoices::URGENT_PRIORITY)	
			pdf_setcolor($pdf, "both", "rgb", 0, 0.75, 0, 0);
		else if($expeditedFlag == ShippingChoices::NORMAL_PRIORITY)	
			pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
		else if($expeditedFlag == ShippingChoices::HIGH_PRIORITY)		
			pdf_setcolor($pdf, "both", "rgb", 0.75, 0, 0, 0);
		else if($expeditedFlag == ShippingChoices::ELEVATED_PRIORITY)
			pdf_setcolor($pdf, "both", "rgb", 0, 0, 0.75, 0);
		else if($expeditedFlag == ShippingChoices::MEDIUM_PRIORITY)
			pdf_setcolor($pdf, "both", "rgb", 0.56, 0.56, 0, 0);
		else
			throw new Exception("Error in PDF function PdfDoc::_setPDFcolorBasedUponExpeditedFlag.  The Expedited flag is undefined.");
	
	
	}
	
	// Show Confirmation number slightly to the right of the page number
	static function _showPrintingConfirmationNumberOnPage(&$pdf, $confirmNumber){
	
		// Draw a white box behind the confirmation number... on some profiles it may be written in the color indication bar.
		pdf_save($pdf);
		pdf_setcolor($pdf, "both", "rgb", 1, 1, 1, 1);
		pdf_rect($pdf, 72, 10, 78, 7 );
		pdf_fill($pdf);
		pdf_restore($pdf);
	
	
		$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
		
		pdf_setfont ( $pdf, $DeckerFont, 7);
		pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
		pdf_show_xy($pdf, ("Confirmation > " . $confirmNumber), 75, 12);
	}
	
	// Generates a single PDF file with all project ID's that are passed into the array
	// This will combine all Projects into a single file and contain the exact quality... example 1000 cards may take 50 pages..  100 cards, only 5 pages.
	// Also writes a "redorder" card and barcode into 1 position on the first 1 page
	// Pass in a string for "OutputProgress"... For instance, one type of out put progress may print a new "percentage completed" out to a Javascript Command
	// Returns the filename of the document that is written into the PDF directory
	static function GenerateSingleFilePDF(DbCmd $dbCmd, $projectIDarr, $pdf_profile, $FileNamePrefix, $batchTimeStamp, $OutputProgress = "none"){
	
	
		// Create a new/blank PDF doc that we will paste the pages onto
		$pdf = pdf_new();
		
		PDF_set_parameter($pdf, "SearchPath", Constants::GetFontBase());
	
		if(!Constants::GetDevelopmentServer())
			pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());
		
		// The desired filename should always have the date appended to the end of it.
		$PDFfileName = $FileNamePrefix . "-" . date("m-d_H-i-s") . ".pdf";
		
		PDF_begin_document($pdf, DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfileName, "" );
	
		// Each order should have a different color box written next to the order number.  This will allow us to separate order from a giant stack.
		$BoxColorArr = array("#996666", "#669966", "#666699", "#999966", "#996699", "#669999", "#999933", "#993399","#339999", "#333399", "#339933", "#993333");
	
		$ProfileFound = false;
		
		
		// We need to right the page number on each sheet... in case the press stops at a certain point with problems, we need to know where to restart from
		$PageNumber = 1;
		
		if(sizeof($projectIDarr) == 0)
			throw new Exception("The Single File Generation with the prefix $FileNamePrefix has an empty Project List");
	
	
		// Each element in this array will contain a PDF resource for the "Reorder Card" / Bar Code that will go in the very first slot.
		// The key to this array will be the User ID of a reseller.  
		// When we are generating a document we can see if the key has been set.  If so, just paste the PDF into the first slot.... otherwise we need to generate a new one.. then paste. 
		$ReorderCardsArr =& PdfDoc::_getArrayOfReorderCards($dbCmd, $pdf, $projectIDarr, 0);
	
	
		// Take the first Project within the list... If it has a Summary Sheet flag set (from the Paramaters Object)... then we will create a Summary Sheet for the entire Batch
		// The Summary Sheet basically tells how many Projects are in the Batch and the Total number of Sheets printed.
		PdfDoc::addSummarySheetPossibly($dbCmd, $pdf, $projectIDarr, $pdf_profile, $batchTimeStamp);
	
		
		
		// The printing confirmation number is basically a sum of all project numbers
		// Then we only use the last 3 digits
		$confirmNumber = 0;
		foreach($projectIDarr as $projectID)
			$confirmNumber += $projectID;
		if(strlen($confirmNumber) > 3)
			$confirmNumber = substr($confirmNumber, -3);
	
	
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$allUserDomainsArr = $passiveAuthObj->getUserDomainsIDs();
		
		// Gather all of the PDF pages... and then paste them together in a single File
		$ProjectCounter = 0;
		foreach($projectIDarr as $ProjectOrderID){
	
			$ProjectCounter++;
	
			// We need to find out what Saved Project account we will get the "reorder" card from
			$ProjectUserID = Order::GetUserIDFromProject($dbCmd, $ProjectOrderID);
			$AccountTypeObj = new AccountType($ProjectUserID, $dbCmd);
			$AccountUserID = $AccountTypeObj->GetAccountUserID();
			
			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $ProjectOrderID);
			$productID = $projectObj->getProductID();
			$orderID = $projectObj->getOrderID();
			$priorityChar = $projectObj->getPriority();
			
			if(!in_array($projectObj->getDomainID(), $allUserDomainsArr))
				throw new Exception("Can not generate a PDF document for Project: P$ProjectOrderID because the Domain is invalid.");
			
			
			$shippingChoicesObj = new ShippingChoices(Order::getDomainIDFromOrder($orderID));
			
			
			$PDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $ProjectOrderID, $pdf_profile);
	
			$projectShippingChoiceID = Order::GetShippingChoiceIDFromProjectID($dbCmd, $ProjectOrderID);
			
			$expeditedFlag = $shippingChoicesObj->getPriorityOfShippingChoice($projectShippingChoiceID);
	
			$xmlString = ArtworkLib::GetArtXMLfile($dbCmd, "ordered", $ProjectOrderID);
			$ArtworkInfoObj = new ArtworkInformation($xmlString);
			
			// Skip over projects which don't have the PDF settings saved Yet.... kind of fails silently
			if(sizeof($PDFobjectsArr) == 0)
				continue;
			else
				$ProfileFound = true;
	
			
				
			// Put the Timestamp of this batch next to the order number.
			for($j=0; $j<sizeof($PDFobjectsArr); $j++){
				
				$orderDescription = $PDFobjectsArr[$j]->getOrderno() . " - " . $batchTimeStamp . "   ---   (" . $projectObj->getOptionsAliasStr(true) . ")";
				
				// If there is a custom color palette, write those colors on the PDF in the order area.
				if(isset($ArtworkInfoObj->SideItemsArray[$j])){
					
					$colorDefArr = $ArtworkInfoObj->GetColorDescriptions($j);
					
					if(!empty($colorDefArr))
						$orderDescription .= " --- Palette: " . implode(", ", $colorDefArr);
				}
				
				$PDFobjectsArr[$j]->setOrderno($orderDescription);
			}
	
	
			// Keep cycling through the colors
			$ThisBoxColor = current($BoxColorArr);
			if(!next($BoxColorArr))
				reset($BoxColorArr);
	
			// For Variable data projects... get the entire PDF document with all data subsituted
			// Otherwise Get a PDF document for the Project... This will have all rows/columns already replicated for only 1 Sheet	
			if($projectObj->isVariableData()){
	
				if($projectObj->checkIfVariableDataIsLarge())
					$outputProgress = "space";
				else
					$outputProgress = "none";
					
				// If the user is generating a "Mailing Batch" through a SingleFile PDF (will be out of sequence for CASS certification)
				// .... however we don't need a re-order card or a BarCode if they are going to be mailed.
				if($PDFobjectsArr[0]->getMailingServices())
					$showReorderCardFlag = false;
				else
					$showReorderCardFlag = true;
	
	
				$ThisProjectArtworkPDF = PdfDoc::generateVariableDataPDF($dbCmd, $ProjectOrderID, "ordered", $showReorderCardFlag, $PDFobjectsArr, $ThisBoxColor, "", $outputProgress);
			}
			else{
				$ThisProjectArtworkPDF = PdfDoc::GeneratePDFsheet($dbCmd, $PDFobjectsArr, $ArtworkInfoObj, $ThisBoxColor, "", $projectObj->isVariableData());
			}
	
			// Put the Original PDF Binary data into a PDF lib virtual file
			$PFV_FileName = "/pfv/pdf/ArtworkFileMaster" . $ProjectOrderID;
			PDF_create_pvf( $pdf, $PFV_FileName, $ThisProjectArtworkPDF, "");
	
	
			// Put the Original PDF file into a PDI object that we can extract page(s) from.
			$OriginalPDF = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
	
			PDF_delete_pvf($pdf, $PFV_FileName);
	
			$TotalPagesInOriginal = PDF_get_pdi_value($pdf, "/Root/Pages/Count", $OriginalPDF, 0, 0);
	
			// Create an array of PDF Page objects.  There should be one array entry for each page
			$PDI_PagesObjArr = array();
			for($i=0; $i < $TotalPagesInOriginal; $i++)
				$PDI_PagesObjArr[$i] = PDF_open_pdi_page($pdf, $OriginalPDF, ($i+1), "");
	
	
			// Generate a coversheet (if the profile says we should have one)
			if($PDFobjectsArr[0]->getDisplayCoverSheet()){
				$orderNumberDesc = ($orderID . " - P" . $ProjectOrderID);
				
				PdfDoc::addCoverSheet($dbCmd, $pdf, $PDFobjectsArr, $ArtworkInfoObj, $projectObj->isVariableData(), $projectObj->GetQuantity(), $PageNumber, $orderNumberDesc, $expeditedFlag, $priorityChar, $ThisBoxColor);
	
				// Adding a coversheet means we need an additional page.
				$PageNumber++;
				
				// Double-sided artworks adds another page number (for the back of it).
				if(sizeof($ArtworkInfoObj->SideItemsArray) > 1)
					$PageNumber++;
			}
	
			if($projectObj->isVariableData()){
			
				for($i=0; $i < $TotalPagesInOriginal; $i++){
	
					$pagewidth = PDF_get_pdi_value($pdf, "width", $OriginalPDF, $PDI_PagesObjArr[$i], 0);
					$pageheight = PDF_get_pdi_value($pdf, "height", $OriginalPDF, $PDI_PagesObjArr[$i], 0);
	
					pdf_begin_page($pdf, $pagewidth, $pageheight);
	
					PDF_fit_pdi_page($pdf, $PDI_PagesObjArr[$i], 0, 0, "");
	
					// If we are on the first page of the project and on the first side, then display the reorder card
					// Profiles with Mailing Services never get a re-Order Barcode though because they won't get shipped or processed in the same way.
					if($i==0 && !$PDFobjectsArr[0]->getMailingServices())
						PdfDoc::_placeReorderCardOnPageAndDrawBarcode($dbCmd, $pdf, $ReorderCardsArr, $PDFobjectsArr[0], $AccountUserID, $ProjectOrderID, $productID);
					
					// On the last 2 page numbers show the confirmation page (if this is the last project in the batch)
					// We are printing on the last 2 pages because we don't have a good way to detect duplex at this point in the code.
					// We want to be sure that the print operator doesn't have to look on the bottom of the sheet.
					if(($ProjectCounter == sizeof($projectIDarr)) && ($i+1 == $TotalPagesInOriginal || $i+2 == $TotalPagesInOriginal) )
						PdfDoc::_showPrintingConfirmationNumberOnPage($pdf, $confirmNumber);
	
					PdfDoc::_showPageNumberOnPage($pdf, $PageNumber, $expeditedFlag, $priorityChar);
	
					$PageNumber++;
	
					pdf_end_page($pdf);
				}		
			}
			else{
			
				// Find out how many parent sheets are needed to reach the desired quantity
				// We are only going to find out how many artworks per sheet there are on the first page... don't worry about counting the backside.   It should always match
				$ArtworksPerSheet = $PDFobjectsArr[0]->getQuantity();
				
				// Find out the quantity of items we need... The PDF Profile may specify a certain percentage of extras
				$extraItemsFromPDFprofile = round($PDFobjectsArr[0]->getExtraQuantityPercentage() / 100 * $projectObj->getQuantity());
				
	
				$extraQuantityMaximum = $PDFobjectsArr[0]->getExtraQuantityMaximum();
				
				// If the extra Items happens to be a negative value, then don't let us short more quantity than the maximum.  The Maximum amount is always a postivie number.
				// Don't put a limit if the max quantity is set to Zero.
				if(!empty($extraQuantityMaximum)){
					if($extraItemsFromPDFprofile > 0 && $extraItemsFromPDFprofile > $extraQuantityMaximum)
						$extraItemsFromPDFprofile = $extraQuantityMaximum;
					if($extraItemsFromPDFprofile < 0 && abs($extraItemsFromPDFprofile) > $extraQuantityMaximum)
						$extraItemsFromPDFprofile = $extraQuantityMaximum * -1;
				}
				
				
				$totalItemQuantity = $projectObj->getQuantity() + $extraItemsFromPDFprofile;
				
				// The getExtraQuantityPercentage can also be negative... 
				// If so, we want to check and make sure the Extra Quantity Percentage does put the "Overall Quantity" below the minimum starting level...
				// ... this functionality will let the Overall Quantity drop down to exactly the Minimum starting level... but nothing more.
				if($extraItemsFromPDFprofile < 0 && $PDFobjectsArr[0]->getExtraQuantityMinimumStart() > $totalItemQuantity){
					
					// If our minimum starting quantity is less than the "Project Quantity"... then there is no room for any extras.
					// Otherwise we will take off as many as possible until the Minimum starting quantity is reached.
					if($PDFobjectsArr[0]->getExtraQuantityMinimumStart() > $projectObj->getQuantity() )
						$extraItemsFromPDFprofile = 0;
					else
						$extraItemsFromPDFprofile = $PDFobjectsArr[0]->getExtraQuantityMinimumStart() - $projectObj->getQuantity();	
				}
			
				$totalItemQuantity = $projectObj->getQuantity() + $extraItemsFromPDFprofile;
				
				$projectPages = ceil($totalItemQuantity / $ArtworksPerSheet);
	
				for($j=0; $j<$projectPages; $j++){
					for($i=0; $i < $TotalPagesInOriginal; $i++){
	
						$pagewidth = PDF_get_pdi_value($pdf, "width", $OriginalPDF, $PDI_PagesObjArr[$i], 0);
						$pageheight = PDF_get_pdi_value($pdf, "height", $OriginalPDF, $PDI_PagesObjArr[$i], 0);
	
						pdf_begin_page($pdf, $pagewidth, $pageheight);
	
						PDF_fit_pdi_page($pdf, $PDI_PagesObjArr[$i], 0, 0, "");
	
						// If we are on the first page of the project and on the first side, then display the reorder card
						if($i==0 && $j==0)
							PdfDoc::_placeReorderCardOnPageAndDrawBarcode($dbCmd, $pdf, $ReorderCardsArr, $PDFobjectsArr[0], $AccountUserID, $ProjectOrderID, $productID);
	
						// On the very last page show a printing confirmation number (on front and maybe also on the back).
						if($j+1 == $projectPages && $ProjectCounter == sizeof($projectIDarr))
							PdfDoc::_showPrintingConfirmationNumberOnPage($pdf, $confirmNumber);
	
						PdfDoc::_showPageNumberOnPage($pdf, $PageNumber, $expeditedFlag, $priorityChar);
	
						$PageNumber++;
	
						pdf_end_page($pdf);
					}
				}
			}
	
			// Close all of the PDI page objects
			for($i=0; $i < $TotalPagesInOriginal; $i++)
				PDF_close_pdi_page($pdf, $PDI_PagesObjArr[$i]);
	
			PDF_close_pdi($pdf, $OriginalPDF);
	
	
			if($OutputProgress == "javascript_percent"){
				print "\n<script>\n";
				print "PercentComplete(" . ceil( $ProjectCounter / sizeof($projectIDarr) * 100 ) . ", '" . $FileNamePrefix . "');";
				print "\n</script>\n";
				Constants::FlushBufferOutput();
			}
			else if($OutputProgress == "xml_progress"){
				print "<percent>" . ceil( $ProjectCounter / sizeof($projectIDarr) * 100 ) . "</percent>\n";
				print "<pages>" . ($PageNumber - 1) . "</pages>\n";
				Constants::FlushBufferOutput();
			}
			else if($OutputProgress == "none"){
				// Do nothing
			}
			else{
				throw new Exception("Illegal OutputProgress");
			}
			
			
			// Make the loop wait 1/10 of a second between PDF files
			usleep(100000);
		}
		
		if(!$ProfileFound)
			throw new Exception("No projects were found with PDF Settings.... or the PDF profile was not found.");
		
	
	
		// Close all of the PDI pages object for the reorder cards
		foreach($ReorderCardsArr as $ReorderCard_AccountIDHash){
			foreach($ReorderCard_AccountIDHash as $recordCardProductIDhash){
				PDF_close_pdi_page($pdf, $recordCardProductIDhash["PDF_PDI_page"]);
				PDF_close_pdi($pdf, $recordCardProductIDhash["PDF_PDI_doc"]);
			}
		}
	
	
		PDF_end_document($pdf, "");
		
		return $PDFfileName;
	}
	
	
	// Pass in an array of ProjectID's and this method will take the first Project within the list.
	// If it has a Summary Sheet flag set (from the Paramaters Object)... then we will create a Summary Sheet for the entire Batch
	// The Summary Sheet basically tells how many Projects are in the Batch and the Total number of Sheets printed.
	// It will add the page to the current document.  It will use the Parent Sheet size on the PDF parameters... belonging to the PDF Project.
	// Return TRUE if a Summary Sheet was added... FALSE otherwise.
	static function addSummarySheetPossibly(DbCmd $dbCmd, &$pdf, &$projectIDarr, $pdf_profile, $batchTimeStamp){
	
		if(empty($projectIDarr))
			throw new Exception("Error in method addSummarySheet... the ProjectID Array can not be null.");
	
		$PDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $projectIDarr[0], $pdf_profile);
	
		// If there haven't been any Parameters set.. then just return.  This should never happen though.
		if(sizeof($PDFobjectsArr) == 0)
			return false;
	
		// Find out if we should create a summary sheet for this batch
		if(!$PDFobjectsArr[0]->getDisplaySummarySheet())
			return false;
		
		
		$summarySheet_Width = PdfDoc::inchesToPDF($PDFobjectsArr[0]->getPagewidth());
		$summarySheet_Height = PdfDoc::inchesToPDF($PDFobjectsArr[0]->getPageheight());
		$summarySheet_Sides = sizeof($PDFobjectsArr);
	
	
	
		for($i=0; $i<$summarySheet_Sides; $i++){
			pdf_begin_page($pdf, $summarySheet_Width, $summarySheet_Height);
	
			// Don't put anything on the back of the summary sheet.... just a blank sheet (if there is one)
			if($i==0){
				pdf_save($pdf);
	
				// Starting writing stuff onto the summary sheet 1 inch from the top/left
				pdf_translate($pdf, 72, ($summarySheet_Height - 72));
	

	
	
				// List all of the Projects in the Batch
				$summaryYcoordinate = -330;
	
				$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
				pdf_setfont ( $pdf, $DeckerFont, 15);
	
				foreach($projectIDarr as $ProjectOrderID){
	
					$projectSheetCount = PdfDoc::getPageCountsByProjectList($dbCmd, array($ProjectOrderID), $pdf_profile, "SheetCount");
					$quantityCount = OrderQuery::getTotalQuantityByProjectList($dbCmd, array($ProjectOrderID)) + 1; // Add 1 for the Re-order slot.
	
					pdf_show_xy($pdf, ("P" . $ProjectOrderID . "    Quantity: " . $quantityCount . "    Sheet Count: " . $projectSheetCount), 0, $summaryYcoordinate);
	
					$summaryYcoordinate -= 19;
				}
	
	
				pdf_restore($pdf);
			}
			pdf_end_page($pdf);
		}
		
		return true;
	
	}
	
	
	// Pass in a project list and a PDF profile 
	// This function will return the number sheets in the Batch.
	// It does not matter if an artwork is single-sided or double-sided... the sheet count would be the same.
	// Report Type can be "SheetCount" or "ImpressionCount".  A double sided sheet of paper counts as 2 impressions.
	static function getPageCountsByProjectList(DbCmd $dbCmd, $projectIDarr, $pdf_profile, $reportType){
	
		$totalSheets = 0;
		$totalImpressions = 0;
		
		// In case the Project List is big... we don't want to have to make multiple database calls if we just got the value.
		$profileQuantityForProductArr = array();
		foreach($projectIDarr as $ProjectOrderID){
			
			$dbCmd->Query("SELECT Quantity, ProductID, ArtworkSides FROM projectsordered WHERE ID=$ProjectOrderID");
			if($dbCmd->GetNumRows() == 0)
				throw new Exception("Error in function call PdfDoc::getPageCountsByProjectList getting the quantity");
			$projectRow = $dbCmd->GetRow();
			
			// Add 1 for the reorder card
			$projectQuantity = $projectRow["Quantity"] + 1;
			$productID = $projectRow["ProductID"];
			$numberOfSides = $projectRow["ArtworkSides"];
			
			$productionProductID = Product::getProductionProductIDStatic($dbCmd, $productID);
			
			
			if(!array_key_exists($productID, $profileQuantityForProductArr)){
	
				// Get the default PDF profile used for production.
				$profileObj = new PDFprofile($dbCmd, $productionProductID);
				$defaultProfile = $profileObj->getDefaultProfileName();
	
				// If no default exists then skip counting the Product.
				if(empty($defaultProfile))
					continue;
	
				$profileObj->loadProfileByName($defaultProfile);
				
				$profileQuantityForProductArr[$productID] = $profileObj->getQuantity();
			}
	
	
	
			$sheetsInProject = ceil($projectQuantity / $profileQuantityForProductArr[$productID]);
			
			$totalSheets += $sheetsInProject;
			
			// The total number of impressions depends on how many sides are in the Project.
			$totalImpressions += ($sheetsInProject * $numberOfSides);
		}
		
		
		if($reportType == "SheetCount")
			return $totalSheets;
		else if($reportType == "ImpressionCount")
			return $totalImpressions;
		else
			throw new Exception("Error in function PdfDoc::getPageCountsByProjectList.  Illegal Sheet count.");
		
	}
	
	
	
	
	// Will add cover sheet(s) to the given PDF object. 
	// If there are 2 sides to the artwork... then this function will add 2 pages... but the second side will be blank.
	// The $ArtworkInfoCoverSheetObj can have layers on it... or it doesn't need to.  This will remove all layers (if any) and just use the dimensions of it.
	static function addCoverSheet(DbCmd $dbCmd, &$pdf, $PDFobjectsArr, $ArtworkInfoTemplateObj, $isVariableData, $unitQuantity, $PageNumber, $orderNumOrTitle, $expeditedFlag, $priorityChar, $backgroundColor){
	
	
		$ArtworkInfoCoverSheetObj = unserialize(serialize($ArtworkInfoTemplateObj));
		$PDFobjectsArr = unserialize(serialize($PDFobjectsArr));
	
		
	
		// Cover sheets must always be accompanied by a Matrix array
		$matrixObj = $PDFobjectsArr[0]->getCoverSheetMatrix();
		if(!$matrixObj)
			throw new Exception("Error generating PDF document.  Cover sheets must also contain a Matrix Object.");
	
	
		// We are going to make the dimensions for the Cover Sheet based upon the artwork file.
		// What is on the artwork doesn't really matter. We are going to take the first side and remove all of the layers.  
		// We could generate a blank PDF document, but it is easier to go through our function for drawing all of the crop marks, borders, etc.
		for($i=1; $i<sizeof($ArtworkInfoCoverSheetObj->SideItemsArray); $i++)
			unset($ArtworkInfoCoverSheetObj->SideItemsArray[$i]);
	
		$ArtworkInfoCoverSheetObj->SideItemsArray[0]->layers = array();
	
		// Now take the PDF profile and only use the settings for the first side.
		$PDFprofileArrCopy = $PDFobjectsArr;
		for($i=1; $i<sizeof($PDFprofileArrCopy); $i++)
			unset($PDFprofileArrCopy[$i]);
	
		// We don't want to include the order number on our master Cover Sheet, because it will be shared between orders.
		$PDFprofileArrCopy[0]->setOrderno(""); 
	
		$CoverSheetArtworkPDF = PdfDoc::GeneratePDFsheet($dbCmd, $PDFprofileArrCopy, $ArtworkInfoCoverSheetObj, "#FFFFFF", "", $isVariableData);
	
		// Put the Master Cover Sheet File into a PDF lib virtual file
		$PFV_CoverSheetName = "/pfv/pdf/CoverSheetFile" . microtime(); // Make sure it is unique because the Virtual File Name may be created many times within a Batch... Even through we immediately release the FileName... PDFlib complains.
		PDF_create_pvf( $pdf, $PFV_CoverSheetName, $CoverSheetArtworkPDF, "");
	
		// Put the Cover Sheet PDF file into a PDI object.
		$CoverSheetPDI = PDF_open_pdi($pdf, $PFV_CoverSheetName, "", 0);
		$CoverSheetPDI_page = PDF_open_pdi_page($pdf, $CoverSheetPDI, 1, "");
	
		PDF_delete_pvf($pdf, $PFV_CoverSheetName);
	
	
		$coverSheetPageWidth = PDF_get_pdi_value($pdf, "width", $CoverSheetPDI, $CoverSheetPDI_page, 0);
		$coverSheetPageHeight = PDF_get_pdi_value($pdf, "height", $CoverSheetPDI, $CoverSheetPDI_page, 0);
	
		pdf_begin_page($pdf, $coverSheetPageWidth, $coverSheetPageHeight);
	
		PDF_fit_pdi_page($pdf, $CoverSheetPDI_page, 0, 0, "");
	
		// Write the slot number onto every position
		for($k=1; $k <= $PDFprofileArrCopy[0]->getColumns(); $k++){
			for($z=1; $z <= $PDFprofileArrCopy[0]->getRows(); $z++){
	
				pdf_save($pdf);
	
				PdfDoc::_translatePDFpointerToContentPosition($pdf, $PDFprofileArrCopy[0], $z, $k, false);
	
				// Get RGB values from our hex number
				$ColorCodeObj = ColorLib::getRgbValues($backgroundColor, false);
	
				// If the canvas has been rotated then the height/width of the internal color indicator box will need to be adjusted accordingly
				if($PDFprofileArrCopy[0]->getRotatecanvas(true) == 90 || $PDFprofileArrCopy[0]->getRotatecanvas(true) == 270){
					$InsideContentWidth_PDF_afterRotate = PdfDoc::inchesToPDF($PDFobjectsArr[0]->getUnitHeight());
					$InsideContentHeight_PDF_afterRotate = PdfDoc::inchesToPDF($PDFobjectsArr[0]->getUnitWidth());
				}
				else{
					$InsideContentWidth_PDF_afterRotate = PdfDoc::inchesToPDF($PDFobjectsArr[0]->getUnitWidth());
					$InsideContentHeight_PDF_afterRotate = PdfDoc::inchesToPDF($PDFobjectsArr[0]->getUnitHeight());
				}
	
				$coverPageBleedUnits = $PDFprofileArrCopy[0]->getBleedUnits();
				// Make the coverage area fall slightly inside the borders
				if($coverPageBleedUnits > 1)
					$coverPageBleedUnits -= 0.2;
	
	
				// Draw a color indicator box the same color as the color separation bar between orders
				pdf_save($pdf);
				pdf_setcolor($pdf, "both", "RGB", $ColorCodeObj->red/256, $ColorCodeObj->green/256, $ColorCodeObj->blue/256, 0);
				PDF_rect($pdf, -($coverPageBleedUnits), -($coverPageBleedUnits) , $InsideContentWidth_PDF_afterRotate + $coverPageBleedUnits*2, $InsideContentHeight_PDF_afterRotate + $coverPageBleedUnits*2);
				PDF_fill($pdf);
				pdf_restore($pdf);
	
	
				// Draw a Shipping Color Urgency Box in the bottom-right corner.
				// No point in doing this for Mailed orders because they won't be shipped.
				if(!$PDFprofileArrCopy[0]->getMailingServices()){
					pdf_save($pdf);
					PdfDoc::_setPDFcolorBasedUponExpeditedFlag($pdf, $expeditedFlag);
					PDF_rect($pdf, -($coverPageBleedUnits), -($coverPageBleedUnits) , 30, 30);
					PDF_fill($pdf);
					pdf_restore($pdf);
				}
	
	
				// Draw a Big White Box Inside so that we have a place to put the Order Numbers.
				// Make it yellow to alert for Variable data
				pdf_save($pdf);
	
				if($PDFprofileArrCopy[0]->getMailingServices())
					pdf_setcolor($pdf, "both", "RGB", 0.9, 0.6, 0.9, 0);
				else if($isVariableData)
					pdf_setcolor($pdf, "both", "RGB", 1, 1, 0.4, 0);
				else
					pdf_setcolor($pdf, "both", "RGB", 1, 1, 1, 0);
				PDF_rect($pdf, $InsideContentWidth_PDF_afterRotate/8, $InsideContentHeight_PDF_afterRotate/8 , $InsideContentWidth_PDF_afterRotate/8*6, $InsideContentHeight_PDF_afterRotate/8*6);
				PDF_fill($pdf);
				pdf_restore($pdf);
	
	
				$centerX = $InsideContentWidth_PDF_afterRotate/2;
				$centerY = $InsideContentHeight_PDF_afterRotate/2;
	
				$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
				pdf_setfont ( $pdf, $DeckerFont, 25);
				pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
	
				// Figure out what number to display in the given slot (for the given row/colum) based upon the MatrixOrder object that is set in the PDF profile.
				// The PDF is going from bottom to top... 
				// Our matrix is organized with Rows starting at the top going down.
				$rowForMatrix = ($PDFprofileArrCopy[0]->getRows() + 1) - $z;
				pdf_show_xy($pdf, ("Slot " . $matrixObj->getMatrixOrderValueAt($rowForMatrix, $k)), ($centerX - 25), ($centerY - 6));
	
				pdf_setfont ( $pdf, $DeckerFont, 15);
				pdf_show_xy($pdf, $orderNumOrTitle, $centerX-65, $centerY-28);
	
				pdf_setfont ( $pdf, $DeckerFont, 18);
				pdf_show_xy($pdf, ("Total Quantity: " . $unitQuantity), $centerX-65, $centerY-58);
	
				pdf_restore($pdf);
			}
		}
	
	
	
		PdfDoc::_showPageNumberOnPage($pdf, $PageNumber, $expeditedFlag, $priorityChar);
	
		pdf_end_page($pdf);
	
	
		// If the artwork is double-sided then we need a blank backside to the coversheet
		if(sizeof($ArtworkInfoTemplateObj->SideItemsArray) > 1){
			pdf_begin_page($pdf, $coverSheetPageWidth, $coverSheetPageHeight);
			$PageNumber++;
			PdfDoc::_showPageNumberOnPage($pdf, $PageNumber, $expeditedFlag, $priorityChar);
			pdf_end_page($pdf);
		}
	
	
	
		PDF_close_pdi_page($pdf, $CoverSheetPDI_page);
		PDF_close_pdi($pdf, $CoverSheetPDI);
	
	}
}

?>
