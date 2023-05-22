<?php


// Automatically load Classes if the FileName matches the class Name.

function __autoload($class_name) {
	
	//class directories
	$directories = array (
		'../classes/', 
		'../classes/AdWords/', 
		'../classes/Checkout/', 
		'../classes/Paypal/', 
		'../classes_3rdparty/', 
		'../classes_3rdparty/ExcelParser/', 
		'../classes_3rdparty/Pear/',
		'../classes_3rdparty/Pear/Mail/',
		'../classes_3rdparty/Pear/calendar/',
		'classes/', 
		'classes/AdWords/', 
		'classes/Checkout/', 
		'classes/Paypal/', 
		'classes_3rdparty/', 
		'classes_3rdparty/ExcelParser/', 
		'classes_3rdparty/Pear/Mail/',
		'classes_3rdparty/Pear/',
		'classes_3rdparty/Pear/calendar/'
	);
	

	// Special Cases: We may have more than one class in a file.
	// The Key is the actual filename, the values are all of the class names inside
	$mutliClassNames["Shapes.php"] = array("ShapeContainer", "ArtworkShape", "ArtworkCircle", "ArtworkRectangle");
	$mutliClassNames["CmykBlocks.php"] = array("CMYKblocksContainer", "CMYKblocks");
	$mutliClassNames["ups_avs.php"] = array("UPS_AV", "UPS_AV_request");
	$mutliClassNames["ups_TimeInTransit.php"] = array("UPS_TimeInTransit", "UPS_TimeInTransit_request", "UPS_TimeInTransit_response");
	$mutliClassNames["ArtworkInformation.php"] = array("LayerItem", "TextItem", "TextPermissions", "GraphicItem", "GraphicPermissions", "MarkerImage", "MaskImage", "SideItem", "ColorItem");
	$mutliClassNames["dataprovider.php"] = array("ExcelParserUtil", "DataProvider");
	$mutliClassNames["mime.php"] = array("Mail_mime");
	$mutliClassNames["mimeDecode.php"] = array("Mail_mimeDecode");
	$mutliClassNames["mimePart.php"] = array("Mail_mimePart");
	$mutliClassNames["RFC822.php"] = array("Mail_RFC822");
	$mutliClassNames["Calendar.php"] = array("Calendar_Engine_Factory");

	
	
	
	// For each directory... try and include the file.
	foreach ( $directories as $directory ) {

		if (file_exists ( $directory . $class_name . '.php' )) {
			require_once ($directory . $class_name . '.php');
			return;
		}
		else if (file_exists ( $directory . $class_name . '.inc' )) {
			require_once ($directory . $class_name . '.inc');
			return;
		}
		
		foreach($mutliClassNames as $classFileName => $allClassNamesArr){
			if(in_array($class_name, $allClassNamesArr)){
				if (file_exists ( $directory . $classFileName)) {
					require_once ($directory . $classFileName);
					return;
				}
			}
		}
	}
	

	
}
