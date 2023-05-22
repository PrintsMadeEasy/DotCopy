<?

class ProjectStatus {

	
	
	
	#-- Will format the Order status ... second paramater as TRUE will return status with diferent colors etc. for HTML --#
	static function GetProjectStatusDescription($OrdersStatusChar, $HTMLformat=false, $fontSize = "12px"){
	
		$DescHash = ProjectStatus::GetStatusDescriptionsHash();
		
		if(!isset($DescHash[$OrdersStatusChar])){
			print "Error in function call GetProjectStatusDescription";
			exit;
		}
		else{
			if($HTMLformat){
				return "<font color='" . $DescHash[$OrdersStatusChar]["COLOR"] . "' style='font-size:". $fontSize . ";'><b>" . WebUtil::htmlOutput($DescHash[$OrdersStatusChar]["DESC"]) . "</b></font>";
			}
			else{
				return $DescHash[$OrdersStatusChar]["DESC"];
			}
		}
	}
	

	
	
	
	#-- Get the Single characters and their desciptions --#
	static function GetStatusDescriptionsHash(){
	
		$retArray = array();
		
		$retArray["N"]["DESC"] = "New";
		$retArray["N"]["COLOR"] = "#666666";
		
		$retArray["P"]["DESC"] = "Proofed";
		$retArray["P"]["COLOR"] = "#333333";
		
		$retArray["S"]["DESC"] = "Part-way";
		$retArray["S"]["COLOR"] = "#333333";
		
		$retArray["T"]["DESC"] = "Printed";
		$retArray["T"]["COLOR"] = "#006600";
		
		$retArray["Q"]["DESC"] = "Queued";
		$retArray["Q"]["COLOR"] = "#000066";
		
		$retArray["B"]["DESC"] = "Boxed";
		$retArray["B"]["COLOR"] = "#000000";
		
		$retArray["H"]["DESC"] = "On Hold";
		$retArray["H"]["COLOR"] = "#666600";
	
		$retArray["F"]["DESC"] = "Finished";
		$retArray["F"]["COLOR"] = "#660000";
		
		$retArray["C"]["DESC"] = "Canceled";
		$retArray["C"]["COLOR"] = "#660066";
		
		$retArray["G"]["DESC"] = "Mailing Batch New";
		$retArray["G"]["COLOR"] = "#996600";
		
		$retArray["Y"]["DESC"] = "Mailing Batch Ready";
		$retArray["Y"]["COLOR"] = "#663399";
		
		$retArray["D"]["DESC"] = "For Offset";
		$retArray["D"]["COLOR"] = "#669966";
		
		$retArray["E"]["DESC"] = "Plated";
		$retArray["E"]["COLOR"] = "#3399CC";
		
		$retArray["A"]["DESC"] = "Artwork Problem";
		$retArray["A"]["COLOR"] = "#990000";
		
		$retArray["L"]["DESC"] = "Artwork Help";
		$retArray["L"]["COLOR"] = "#990066";
	
		$retArray["W"]["DESC"] = "Waiting For Reply";
		$retArray["W"]["COLOR"] = "#669966";
	
		$retArray["V"]["DESC"] = "Variable Data Problem";
		$retArray["V"]["COLOR"] = "#996666";
	
		return $retArray;
	}
	
	
	
	
	// The statuses that allow Customers to still edit their artwork after the order is placed.
	static function getStatusCharactersCanStillEditArtwork(){
		return array("N", "P", "W", "H");
	}
	
	// returns an array of status characters, that are not Finished or Canceled
	// In Mysql you can do a NOT EQUALS query... except the JOIN is very ineficient doing it this way.  Try using the EXPLAIN command in Mysql to see how many ROWS get joined on indexes each way
	static function getStatusCharactersNotFinishedOrCanceled(){
		
		$retArr = array();
		
		$totalStatusHash = array_keys(ProjectStatus::GetStatusDescriptionsHash());
		
		foreach($totalStatusHash as $thisStatusChar){
			if($thisStatusChar == "C" || $thisStatusChar == "F")
				continue;
	
			$retArr[] = $thisStatusChar;
		}
		return $retArr;
	}
	
	static function getStatusCharactersNotCanceled(){
		$retArr = array();
		
		$totalStatusHash = array_keys(ProjectStatus::GetStatusDescriptionsHash());
		
		foreach($totalStatusHash as $thisStatusChar){
			if($thisStatusChar == "C" )
				continue;
	
			$retArr[] = $thisStatusChar;
		}
		return $retArr;
	}
	
	
	






}

?>
