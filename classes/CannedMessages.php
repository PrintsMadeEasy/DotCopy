<?php


class CannedMessages {
	
	// Rebuilds the tree menu for the Cannee Message 
	// Writes the Javascript data into an external JS file in a temp directory 
	// This way we only generate the tree when there is a change, not everytime the page loads 
	// Pass in a category object that contains the hierarchy of all of the folders
	static function GenerateTree_JSON(DbCmd $dbCmd, $CategoryObj){
	
		$js = "var TREE_ITEMS = [ [ 'Main', 'javascript:ShowCat(0)', 0, ";
		
		//Start the recursive building of the tree... Start of with a categoryID or 0, which is the root.
		//$js is a var passed by reference... so it can keep collecting data as it goes through the recursive functions
		CannedMessages::GenerateTree_JSON_Recursive($dbCmd, $CategoryObj, 0, $js);
		
		
		$js .= "] ]; ";
		
	
		// Put the Javascript tree file on disk..
		// Just put it in the proof directory since we have access to write to disk there
		$TreeName = DomainPaths::getPdfSystemPathOfDomainInURL() . "/cs_cannedmsg_tree_items.js";
		
		#// Put image data into the temp file 
		$fp = fopen($TreeName, "w");
		fwrite($fp, $js);
		fclose($fp);
	
	
	}
	
	static function GenerateTree_JSON_Recursive(DbCmd $dbCmd, &$CategoryObj, $CategoryID, &$js){
		
	
		$childrenList = $CategoryObj->getChildren($CategoryID);
		
		foreach($childrenList as $ThisChild){
		
			$ChildCatName = addslashes(WebUtil::htmlOutput($ThisChild["CatName"]));
			$ChildCatID = $ThisChild["ID"];
		
			// Find out how many messages are within a particular category.  
			// If there are no messages then the Tigra Tree menu will try to make it a "leaf" since there is nothing below it
			// So if there are 0 messages for a category, create a custom icon to show it is an "empty Folder"
			$dbCmd->Query("SELECT COUNT(*) FROM cannedmsgdata WHERE CategoryID=$ChildCatID");
			$ThisMsgCount = $dbCmd->GetValue();
	
			if(!$ThisMsgCount && !$CategoryObj->hasChildren($ChildCatID))
				$Icon = "{ 'i0' : 'images/tree/folder.gif', 'i4' : 'images/tree/folderopen_selected.gif', 'i8' : 'images/tree/folderopen.gif', 'i12' : 'images/tree/folderopen_selected.gif', 'i64' : 'images/tree/folderopen.gif', 'i68' : 'images/tree/folderopen_selected.gif' }";
			else
				$Icon = "0";
		
		
			//Create the folder for the current node
			$js .= "\n ['$ChildCatName', 'javascript:ShowCat($ChildCatID)', $Icon, ";
		
			//If there are more subfolders then go into all of them.
			CannedMessages::GenerateTree_JSON_Recursive($dbCmd, $CategoryObj, $ChildCatID, $js);
				
	
			$js .= "\n ], ";
		}
	
		//Get any messages that exist within this category
		$dbCmd->Query("SELECT * FROM cannedmsgdata WHERE CategoryID=$CategoryID ORDER By MessageTitle ASC");
		$MsgCount = $dbCmd->GetNumRows();
	
		while($MsgRow = $dbCmd->GetRow()){
			$MsgID = $MsgRow["ID"];
			$MsgTitle = addslashes(WebUtil::htmlOutput($MsgRow["MessageTitle"]));
	
			$js .= "\n [ '$MsgTitle', 'javascript:ShowMessage($MsgID)' ], ";
		}
		
		
		//If this is an empty folder then we have to close off the last element in the array
		if(!$MsgCount && !$CategoryObj->hasChildren($CategoryID))
			$js .= "0";
		else
			$js = substr($js, 0, -2); //Get rid of the last comma
		
	}
	
}


