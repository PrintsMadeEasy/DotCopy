<?


// Specify a source artwork (using a number of different ways)
// Specify the Target Product ID.
// Optionaly specify an "overlay artwork" (using a number of different methods)
// Transforms the source artwork into the Destination Product ID and possibly overlays another artwork.

class ArtworkCrossell {

	private $_sourceArtworkInitialized;
	private $_overlayArtworkInitialized;
	private $_sourceArtworkXML;
	private $_overlayArtworkXML;
	private $_targetProductID;
	
	// These are public Members... set their properties before calling the getConvertedArtworkXML properties in this class
	public $artworkMergeObj;
	public $artworkConversionObj;
	
	private $_dbCmd;


	//  Constructor 
	function ArtworkCrossell(DbCmd $dbCmd){
	
		$this->_sourceArtworkInitialized = false;
		$this->_overlayArtworkInitialized = false;
		$this->_dbCmd = $dbCmd;
		$this->_targetProductID = null;
		
		$this->artworkMergeObj = new ArtworkMerge($dbCmd);
		$this->artworkConversionObj = new ArtworkConversion($dbCmd);
		
	}
	
	
	
	// Returns true or false depending on whether a Cross Selling command is available...
	// This has to be coordinated with the function below for GetPDFdocFromForCrossSelling.
	// Sometimes you should check if the command is OK before getting hit with an error message.
	function CheckIfCrossSellCommandAvailable($commandName){
	
		if(in_array($commandName, array("PenJumbo")))
			return true;
		else
			return false;
	}
	
	
	
	// Static Function
	// Will return a PDF document depending on a "Cross Selling Command"
	function &GetPDFdocFromForCrossSelling(DbCmd $dbCmd, $command, $projectOrderID){
	
		
		$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $projectOrderID);
		
		if($command == "PenJumbo"){
			
			$productIDofPromo = 91;
			
			$domainIDofPromo = Product::getDomainIDfromProductID($dbCmd, $productIDofPromo);
			if($domainIDofPromo != $domainIDofProject)
				throw new Exception("Error with Fetching the Promo Artwork because there is a domain Conflict.");
		
			$artworkCrossSellObj = new ArtworkCrossell($dbCmd);
			
			$artworkCrossSellObj->setTargetProductID(91);
			
			// Extract the source artwork from the project ID.
			$artworkCrossSellObj->setSourceArtworkByProjectID($projectOrderID, "ordered");
				
			// Get the Overlay Artwork out of My Saved Projects
			$projectObj = ProjectSaved::GetProjectObjFromSavedNotes($dbCmd, 4, "PensOversizedOverlayPromotion");
			$artworkCrossSellObj->setOverlayArtworkByXML($projectObj->getArtworkFile());
			

			// Stretch all images on the Pen... because we want it to cover the whole area
			// There may be some distorion, but it will be minimal
			$artworkCrossSellObj->artworkConversionObj->stretchTemplateImages(true);
			$artworkCrossSellObj->artworkConversionObj->stretchUserImages(true);
			$artworkCrossSellObj->artworkMergeObj->stretchTemplateImages(true);
			$artworkCrossSellObj->artworkMergeObj->stretchUserImages(true);
		
			// Pens don't have a backside
			$artworkCrossSellObj->artworkMergeObj->removeBacksideFlag(true);
			
			// Shift the User's artwork up a big and squash it to make room for the marketing information underneath it (overlay)
			$artworkCrossSellObj->artworkMergeObj->changeBottomArtworkProportions(0, -19);
			$artworkCrossSellObj->artworkMergeObj->offsetBottomArtworkCoords(0, -16);
			
			$convertedArtworkXML = $artworkCrossSellObj->getConvertedArtworkXML();
			
			
			// Within the Overlay artwork... we are going to search and replace for the User's phone number.
			$dbCmd->Query("SELECT UserID FROM projectsordered JOIN orders ON projectsordered.OrderID = orders.ID WHERE projectsordered.ID=$projectOrderID");
			$userIDfromProject = $dbCmd->GetValue();
			$dbCmd->Query("SELECT PhoneSearch FROM users WHERE ID=$userIDfromProject");
			$phoneFromUser = $dbCmd->GetValue();
			
			// Separate area code with dashes
			$phoneFromUser = substr($phoneFromUser, 0, 3) . "-" . substr($phoneFromUser, 3, 3) . "-" . substr($phoneFromUser, 6);
			
			$convertedArtworkXML = preg_replace("/\{PHONENUMBER\}/", $phoneFromUser, $convertedArtworkXML);
			
			
			$penSampleArtowrkObj = new ArtworkInformation($convertedArtworkXML);
			
			// Get a PDF document with 4 units of Bleed.
			$penSamplePDF = PdfDoc::GetArtworkPDF($dbCmd, 0, $penSampleArtowrkObj, "N", 4, 0, false, false, array());
	
			return $penSamplePDF;
	
		
		}
		else{
			throw new Exception("Illegal Command in in ArtworkCrossell::GetPDFdocFromForCrossSelling");
		}
	
	}
	
	
	
	function setTargetProductID($x){
	
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Error in method ArtworkCrossell->setProductID.  Not an integer");
			
		if(!Product::checkIfProductIDisActive($this->_dbCmd, $x))
			throw new Exception("Error in method ArtworkCrossell->setProductID.  The ProductID does not exist: " . $x);
		
		$this->_targetProductID = $x;
	}



	function setSourceArtworkByXML($sourceArtworkXML){
		

		$this->_sourceArtworkXML = $sourceArtworkXML;
		$this->_sourceArtworkInitialized = true;
	
	}
	
	
	// returns false if the Project ID does not exist.
	function setSourceArtworkByProjectID($projectID, $viewType){
		
		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("Error in method ArtworkCrossell->setSourceArtworkByProjectID.  Project ID is not an integer");
		
		$projectObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, $viewType, $projectID);
		
		
		$projectObj->convertToProductID($this->_targetProductID);
		
		$this->setSourceArtworkByXML($projectObj->getArtworkFile());
	
	}
	
	
	function setOverlayArtworkByXML($x){
		
		$this->_overlayArtworkXML = $x;
		
		$this->_overlayArtworkInitialized = true;
	
	}
	
	
	
	// returns false if the Project ID does not exist.
	function setOverlayArtworkByProjectID($projectID, $viewType){
		
		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("Error in method ArtworkCrossell->setOverlayArtworkByProjectID.  Project ID is not an integer");
		
		$projectObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, $viewType, $projectID, false);
		
		$this->setOverlayArtworkByXML($projectObj->getArtworkFile());
	
	}
	
	

	
	function getConvertedArtworkXML(){

		if(empty($this->_targetProductID))
			throw new Exception("Error in method ArtworkCrossell->getConvertedArtworkXML.  The Target ProductID must be set before calling this method.");

		if(empty($this->_sourceArtworkInitialized))
			throw new Exception("Error in method ArtworkCrossell->getConvertedArtworkXML.  The Source Artwork has not been set.");


		// First Convert our Artwork into the Appropriate format for the product.
		$this->artworkConversionObj->setFromArtwork(0, $this->_sourceArtworkXML);
		$this->artworkConversionObj->setToArtwork($this->_targetProductID);
		
		$convertedToProductXML = $this->artworkConversionObj->getConvertedArtwork();
	
		
		// Now if we have an Overlay, let's merge it together.
		if($this->_overlayArtworkInitialized){
			$this->artworkMergeObj->setBottomArtwork($convertedToProductXML);
			$this->artworkMergeObj->setTopArtwork($this->_overlayArtworkXML);
			
			$convertedToProductXML = $this->artworkMergeObj->getMergedArtwork();
		}
	
	
		return $convertedToProductXML;
		
	
	}
	
	
}

?>
