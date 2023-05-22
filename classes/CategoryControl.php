<?


###-- Category Class contains logic for organizing multi-level hierarchy ---##
###-- It can work on any category system that has 3 elements... 1) Category ID   2)  Category Name   3)  Category Parent ID
###-- You can check for siblines... parents... change category names, add/remove categories etc.
class CategoryControl {

	private $dbCmd;
	private $allCategories = array();
	private $error_message = "";
	private $DB_TableName;
	private $DBField_CategoryID;
	private $DBField_CategoryName;
	private $DBField_CategoryParent;


	##----------------------------------------------------------------------------------------------------------------#
	##-- All arrays containing categories will be a 2D array 							--#
	##-- The Format is as follows....										--#
	##-- $allCategories[x]["ID"]  contains the unique category ID							--#
	##-- $allCategories[x]["CatName"]  contains a string with the category Name					--#
	##-- $allCategories[x]["PID"]  contains the a reference to a Parent category ID  "0" means there is no parent 	--#
	##----------------------------------------------------------------------------------------------------------------#


	##--  Constructor gets all category information from the database.  And stores information in a 2D array for access throughout the rest of this objects life --##
	##--  Pass in a Database Object... as well as the Table Name and the field names from the database
	function CategoryControl($dbCmd, $DB_TableName, $DBField_CategoryID, $DBField_CategoryName, $DBField_CategoryParent){
		
		$this->dbCmd = $dbCmd;
		$this->DB_TableName = $DB_TableName;
		$this->DBField_CategoryID = $DBField_CategoryID;
		$this->DBField_CategoryName = $DBField_CategoryName;
		$this->DBField_CategoryParent = $DBField_CategoryParent;

		$this->dbCmd->Query( "SELECT $DBField_CategoryID, $DBField_CategoryName, $DBField_CategoryParent FROM $DB_TableName" );


		$i = 0;
		while($row=$this->dbCmd->GetRow()){
	
			$this->allCategories[$i] = array();
			$this->allCategories[$i]["ID"] = $row[$DBField_CategoryID];
			$this->allCategories[$i]["CatName"] = $row[$DBField_CategoryName];
			$this->allCategories[$i]["PID"] = $row[$DBField_CategoryParent];
			$i++;
		}
	}

	#-- Returns true if the category ID exists --#
	function categoryExists($categoryID){

		for ($i=0; $i<count($this->allCategories); $i++){

			if ($this->allCategories[$i]["ID"] == $categoryID)
				return true;
		}

		return false;

	}

	#-- Returns true if the category ID has categories underneath it.
	function hasChildren($categoryID){

		for ($i=0; $i<count($this->allCategories); $i++){

			if ($this->allCategories[$i]["PID"] == $categoryID)
				return true;
		}

		return false;

	}

	#-- Returns the parent ID of a category...   If the category is at the top of the hierarchy then it will return 0
	function getParentID($categoryID){

		for ($i=0; $i<count($this->allCategories); $i++){

			if ($this->allCategories[$i]["ID"] == $categoryID)
				return $this->allCategories[$i]["PID"];
		}
		
		return 0;

	}

	function getCategoryName($categoryID){

		for ($i=0; $i<count($this->allCategories); $i++){

			if ($this->allCategories[$i]["ID"] == $categoryID)
				return $this->allCategories[$i]["CatName"];
		}
		
		return null;
	}


	##-- Pass in an a category ID.  It builds a 2D array of the category's children. 
	##-- Returns the 2-d array with 2 elements like   $TheArr[x]["ID"]   &   $TheArr[x]["CatName"]
	function getChildren($categoryID) {

		$childrenList = array();

		$childCount = 0;

		for ($i=0; $i<count($this->allCategories); $i++){

			if ($categoryID == $this->allCategories[$i]["PID"]){
				$childrenList[$childCount] = array();
				$childrenList[$childCount]["ID"] = $this->allCategories[$i]["ID"];
				$childrenList[$childCount]["CatName"] = $this->allCategories[$i]["CatName"];

				$childCount++;
			}
		}

		return $childrenList;

	}

	### Returns 2D Array of Categories.. Begins with the Category at the highest level and continues unitl the specified category
	### EX:   array(      array("ID"=>34, "CatName" =>Recipies", "PID"=>0),   array("ID"=>46, "CatName" =>Deserts", "PID"=>34),      array("ID"=>52, "CatName" =>Cakes", "PID"=>46)     );  ... Deserts is the parent of cakes... and Recipies is the parent of Deserts
	### Last parameter is boolean, it tells us if we shoud include the current cateory within the list... or just everythign in front.
	function getAncestors($categoryID, $ShowCurrent){
	
		//If we are trying to get the ancestors of the root, then just return an empty array
		if($categoryID == 0)
			return array();

		if ($this->categoryExists($categoryID)){


			$ancestorList = array();
			$ancestorCount=0;
			$keepGoing = true;

			$parentID = $this->getParentID($categoryID);
			
			if($ShowCurrent){
				$ancestorList[$ancestorCount]["ID"] = $categoryID;
				$ancestorList[$ancestorCount]["CatName"] = $this->getCategoryName($categoryID);
				$ancestorList[$ancestorCount]["PID"] = $parentID;
				
				$ancestorCount++;
			}
			

			while($parentID <> 0 && $keepGoing == true){

				$keepGoing = false;

				for ($i=0; $i<sizeof($this->allCategories); $i++){
				
					if ($parentID == $this->allCategories[$i]["ID"]){
						$ancestorList[$ancestorCount]["ID"] = $this->allCategories[$i]["ID"];
						$ancestorList[$ancestorCount]["CatName"] = $this->allCategories[$i]["CatName"];
						$ancestorList[$ancestorCount]["PID"] = $this->allCategories[$i]["PID"];

						$parentID = $this->allCategories[$i]["PID"];

						$ancestorCount++;
						$keepGoing = true;

						break;
					}
				}

			}

			//Reverse array to show proper ancestor hierarchy
			$result = array_reverse ($ancestorList);

			return $result;
		}
		else {
			$this->error_message = "Category does not exist.";
			return array();
		}

	}
	
	### Returns a 1D array of all CategoryID's... starting from the Specified CategoryID (including it) 
	// Pass in RootFlag of TRUE if you want it to start with Root 0 as the first element in the array
	function getAncestorList($categoryID, $includeRootFlag){
	
		//If we are trying to get the ancestors of the root, then just return an empty array
		if($categoryID == 0)
			return array();

		if ($this->categoryExists($categoryID)){


			$ancestorList = array();
			$keepGoing = true;

			$parentID = $this->getParentID($categoryID);

			if($includeRootFlag || $parentID != 0)
				$ancestorList[] = $parentID;

			while($parentID <> 0 && $keepGoing == true){

				$keepGoing = false;

				for ($i=0; $i<sizeof($this->allCategories); $i++){
				
					if ($parentID == $this->allCategories[$i]["ID"]){
					
						$parentID = $this->allCategories[$i]["PID"];
						
						if($includeRootFlag || $parentID != 0)
							$ancestorList[] = $parentID;

						$keepGoing = true;

						break;
					}
				}

			}

			//Reverse array to show proper ancestor hierarchy
			$result = array_reverse ($ancestorList);

			return $result;
		}
		else {
			$this->error_message = "Category does not exist.";
			return false;
		}

	}





	##### --------  Following Functions modify the Database -----#######


	function insertCategory($categoryName, $parentID){

		if ($this->categoryExists($parentID) || $parentID==0){

			$DBInsertArr[$this->DBField_CategoryName] = $categoryName;
			$DBInsertArr[$this->DBField_CategoryParent] = $parentID;
			$this->dbCmd->InsertQuery($this->DB_TableName, $DBInsertArr);

			return true;
		}
		else {
			$this->error_message = "Parent does not exist.";
			return false;
		}


	}

	function moveCategory($categoryID, $parentID){
	
		if($categoryID == 0){
			$this->error_message = "You can't move the Root ID 0";
			return false;
		}
		
		if (!$this->categoryExists($categoryID)){
			$this->error_message = "The Current Category ID does not exist.";
			return false;
		}
		
		// Moving to the Root 0 is ok... and that is technically not a category ID.
		if ($parentID != 0 && !$this->categoryExists($parentID)){
			$this->error_message = "The Move-To category ID does not exist.";
			return false;
		}
		
		if ($categoryID == $parentID){
			$this->error_message = "It is illegal to move a category into itself.";
			return false;
		}
		
		// We can't move a Category into one of its children or Grandchildren
		// So we can get a list of all of the MoveTo's ancestors... and get a list of all of the current categories ancestors.
		// If the CurrentcategoryID  exists within the MoveTo's ancestors hierarchy... but it does not in the Current ancestor hierarchy... then we can infer that it is beneath it.
		// ... If it exists in Both then we can assume that it is going to a higher level in the chain which is OK.
		$currentCategoryAncestorsArr = $this->getAncestorList($categoryID, false);
		$moveToAncestorsArr = $this->getAncestorList($parentID, false);


		if(in_array($categoryID, $moveToAncestorsArr) && !in_array($categoryID, $currentCategoryAncestorsArr)){
			$this->error_message = "You can not move a category into one of its children or Grandchildren.  It would cause an infinite loop.";
			return false;
		}
			

		if ($this->categoryExists($parentID) || $parentID==0){
			$UpdateDBarr[$this->DBField_CategoryParent] = $parentID;
			$this->dbCmd->UpdateQuery($this->DB_TableName, $UpdateDBarr, "$this->DBField_CategoryID = $categoryID");
			return true;
		}
		else {
			$this->error_message = "Parent does not exist.";
			return false;
		}
	}

	function deleteCategory($categoryID){

		if ($this->hasChildren($categoryID)){
			$this->error_message = "Category has children.";
			return false;
		}
		else if(!$this->categoryExists($categoryID)){
			$this->error_message = "Category does not exist.";
			return false;
		}
		else {
			$this->dbCmd->Query( "DELETE FROM $this->DB_TableName WHERE $this->DBField_CategoryID =$categoryID" );

			return true;
		}
	}


	function renameCategory($categoryName, $categoryID){

		if ($this->categoryExists($categoryID)){

			$UpdateDBarr[$this->DBField_CategoryName] = $categoryName;

			$this->dbCmd->UpdateQuery($this->DB_TableName, $UpdateDBarr, "$this->DBField_CategoryID = $categoryID");

			return true;
		}
		else {
			$this->error_message = "Category does not exist.";
			return false;
		}

	}
	
	function getErrorMessage(){
		return $this->error_message;
	}

}


?>
