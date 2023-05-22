<?php
class ImageLib {
	
	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	static function GetImagesSavedTableName(DbCmd $dbCmd){
		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='imagessaved'");
		$tableName = $dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function ImageLib::GetImagesSavedTableName");
		
		return $tableName ;
	}
	
	
	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	static function GetImagesTemplateTableName(DbCmd $dbCmd){
		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='imagestemplate'");
		$tableName = $dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function ImageLib::GetImagesTemplateTableName");
		
		return $tableName ;
	}
	
	
	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	static function GetVectorImagesSavedTableName(DbCmd $dbCmd){
		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='vectorimagessaved'");
		$tableName = $dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function ImageLib::GetVectorImagesSavedTableName");
		
		return $tableName ;
	}
	
	
	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	static function GetVectorImagesTemplateTableName(DbCmd $dbCmd){
		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='vectorimagestemplate'");
		$tableName = $dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function ImageLib::GetVectorImagesTemplateTableName");
		
		return $tableName ;
	}
	
	
	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	static function GetVariableImagesTableName(DbCmd $dbCmd){
		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='variableimages'");
		$tableName = $dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function ImageLib::GetVariableImagesTableName");
		
		return $tableName ;
	}
	
	
	
	
	// Flash measures units at 96 PPI... Picas are 72PPI
	static function ConvertPicasToFlashUnits($picas){
		return  round($picas / 72 * 96, 1);
	}
	
	
	static function GetImageByID(DbCmd $dbCmd, $imageid){
	
		if(!preg_match("/^\d+$/", $imageid))
			throw new Exception("Illegal Image ID in function ImageLib::GetImageByID.");
	
		// We look into the imagespointer table to find out where the image file is actually stored.
		// Images can get moved around between saved and temporary tables.  The Image Pointer is a way to keep track of where it is.
		$dbCmd->Query("SELECT TableName, Record from imagepointer where ID=$imageid");
	
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading Image:t: $imageid");
			print "Error loading Image:t: $imageid";
			return array("ContentType"=>"ERROR", "ImageData"=>"");
		}
		$row = $dbCmd->GetRow();
	
		$table_name = $row["TableName"];
		$recordID = $row["Record"];
	
	
		// Looks for the image that is pointed to from the imagepointer table.
		$dbCmd->Query("SELECT BinaryData, FileType from $table_name where ID=$recordID");
	
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading Image:s: The Image pointer is pointing at a record which may not exist. $imageid");
			print "There was an Error loading Image:s:";
			return array("ContentType"=>"ERROR", "ImageData"=>"");
		}
		$row = $dbCmd->GetRow();
	
		// Send the image back in a hash.
		return array("ContentType"=>$row["FileType"], "ImageData"=>$row["BinaryData"]);
	}
	
	// This function will create a temporary blank White JPEG on disk and return its filename. 
	// Use this to start pasting layers on top of 
	static function CreateWhiteJPEGimage($width, $height, $useThisFileName = null) {
	
		//Open up a white background JPEG file from our library directory
		$WhiteBackFname = Constants::GetWebserverBase() . "/library/whitebackground.jpg"; 
		$fd = fopen ($WhiteBackFname, "r");
		$ImageBinaryData = fread ($fd, filesize ($WhiteBackFname));
		fclose ($fd);
	
	
		// Either use the name that was provided or create a new temp file.
		if(!empty($useThisFileName))
			$tmpfname = $useThisFileName;
		else
			$tmpfname = FileUtil::newtempnam(Constants::GetTempDirectory(), "BCK", ".jpg", time());
	
		
		// Put image data into the temp file 
		$fp = fopen($tmpfname, "w");
		fwrite($fp, $ImageBinaryData);
		fclose($fp);
	
		// Make sure that we have permission to modify it with system comands 
		chmod ($tmpfname, 0666);
	
		// Our GD library creates the Image with a Bilevel image type... we need to change to TrueColor
		$ResizeCommand = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -type TrueColor -geometry " . $width . "x" . $height . "! " . $tmpfname;
		system($ResizeCommand);
	
		return $tmpfname;
	}
	
	
	// Pass in a Vector Image ID.  It will return a the blob data by reference.
	static function &getPDFfromVectorImageID(DbCmd $dbCmd, $imgID, $filteredVersion = true){
	
		if(!preg_match("/^\d+$/", $imgID))
			throw new Exception("Illegal Image ID in function ImageLib::getPDFfromVectorImageID.");
			
		$dbCmd->Query("SELECT TableName, Record from vectorimagepointer where ID=$imgID");
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading VectorImage:a: $imgID");
			throw new Exception("exiting, invalid Vector Image ID.");
		}
		
		$row = $dbCmd->GetRow();
	
		$table_name = $row["TableName"];
		$recordID = $row["Record"];
		
	
		if($table_name == "vectorimagesdeleted")
			WebUtil::WebmasterError("Error trying to load a deleted Vector image: $imgID");
		
		if($filteredVersion)
			$pdfColumnName = "PDFbinaryData";
		else
			$pdfColumnName = "OrigFormatBinaryData";
		
		// This Query looks for the image that is pointed to from the imagepointer table.
		$dbCmd->Query("SELECT $pdfColumnName FROM $table_name where ID=$recordID");
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading Vector Image:c: $imgID");
			throw new Exception("exiting, invalid Vector Image ID.");
		}
	
		$retPDF = $dbCmd->GetValue();

		// To Avoid a compiler warning of an unused variable.
		if(!empty($retPDF))
			return $retPDF;
		else
			return $retPDF;
	}
	
	// Returns a hash with 2 elements for the "width" and "height" of the given Vector Image ID.
	static function getDimensionsPicasFromVectorImageID(DbCmd $dbCmd, $imgID){
	
		if(!preg_match("/^\d+$/", $imgID))
			throw new Exception("Illegal Image ID in function ImageLib::getDimensionsPicasFromVectorImageID.");
			
		$dbCmd->Query("SELECT TableName, Record from vectorimagepointer where ID=$imgID");
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading VectorImage Dimensions:a: $imgID");
			throw new Exception("exiting, invalid Vector Image ID in function ImageLib::getDimensionsPicasFromVectorImageID.");
		}
		
		$row = $dbCmd->GetRow();
	
		$table_name = $row["TableName"];
		$recordID = $row["Record"];
		
		// This Query looks for the image that is pointed to from the imagepointer table.
		$dbCmd->Query("SELECT PicasWidth, PicasHeight FROM $table_name where ID=$recordID");
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading Vector Image Dimensions:c: $imgID");
			throw new Exception("exiting, invalid Vector Image ID in fucntion ImageLib::getDimensionsPicasFromVectorImageID.");
		}
		
		$dimRow = $dbCmd->GetRow();
		
		return array("Width"=>$dimRow["PicasWidth"], "Height"=>$dimRow["PicasHeight"]);
	
	}
	
	
	// This function will Load an Image from the database, specified by $imgID and return the file name.  We also need some additions parameter to tell us if it needs to be stretched or rotated. 
	// It returns the File name of the Image on Disk... do not delete the image because it will be handeled by Cron
	// Don't modify the image because it will be cached and the same file will get returned on similar requests.
	static function LoadImageByID (DbCmd $dbCmd, $imgID, $newWidth, $newHeight, $rotation) {
	
		if(!preg_match("/^\d+$/", $imgID))
			throw new Exception("Illegal Image ID.");
			
		$newWidth = round(floatval($newWidth));
		$newHeight = round(floatval($newHeight));
		
		// To Keep the Inodes from filling up with files too quickly, we want to organize as many subdirectories as possible.
		// We don't ever want more than 10,000 files in a directory.
		$imageDirName = (ceil($imgID / 10000) * 10) . "K";
			
		$imageCacheSubDir = Constants::GetImageCacheDirectory() . "/ImageIDs"; 
		if(!file_exists($imageCacheSubDir)){
			mkdir($imageCacheSubDir);
			chmod($imageCacheSubDir, 0777);
		}
		
		$imageCacheSubDir = Constants::GetImageCacheDirectory() . "/ImageIDs/Range_" . $imageDirName; 
		if(!file_exists($imageCacheSubDir)){
			mkdir($imageCacheSubDir);
			chmod($imageCacheSubDir, 0777);
		}
		
		
		$imageIDfileName = $imageCacheSubDir . "/ID" . $imgID . "_W" . $newWidth . "_H" . $newHeight . "_R" . round($rotation);
		
		
		// A Sanity check to make sure that the image wasn't cached with an error of some kind.
		if(file_exists($imageIDfileName) && filesize($imageIDfileName) == 0)
			@unlink($imageIDfileName);
		
		
		if(file_exists($imageIDfileName) && filesize($imageIDfileName) > 0)			
			return $imageIDfileName;
	
	
		// We look into the imagespointer table to find out where the image file is actually stored.
		// Images can get moved around between saved and temporary tables.  The Image Pointer is a way to keep track of where it is.
		$dbCmd->Query("Select TableName, Record from imagepointer where ID=$imgID");
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading:a: $imgID");
			throw new Exception("exiting, invalid Image ID.");
		}
		$row = $dbCmd->GetRow();
	
		$table_name = $row["TableName"];
		$recordID = $row["Record"];
	
		if($table_name == "imagesdeleted")
			WebUtil::WebmasterError("Error trying to load a deleted image: $imgID");
	
		// This Query looks for the image that is pointed to from the imagepointer table.
		$dbCmd->Query("SELECT BinaryData,FileType from $table_name where ID=$recordID");
		if($dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("Error loading:c: $imgID");
			throw new Exception("exiting, invalid Image ID.");
		}
		$row = $dbCmd->GetRow();
	
	
		$isJPEG = false;
		
		// Determine what compression level we are going to use for the resize.
		// JPEG compression is pretty straight forward... 0 - 100 for lossiness.
		// PNG images have a special compression.  The first digit is the zlib compression level 1-9... the second 1-5 is the Filter Type  (5 Adaptive is the most flexible)
		if(preg_match("/png/i", $row["FileType"])){
			// I found that using a compression level of 6 with adpative filtering yielded decent file sizes and it was pretty fast on large images.
			$qualityValue = "65";
		}
		else if(preg_match("/jpeg/i", $row["FileType"])){
			$qualityValue = "95";
			$isJPEG = true;
		}
		else{
			throw new Exception("Error with Image File type:" . $imgID);
		}
	
		// Put image data into file
		$fp = fopen($imageIDfileName, "w");
		fwrite($fp, $row["BinaryData"]);
		fclose($fp);
	
		// Make sure that we have permission to modify it with system comands 
		chmod ($imageIDfileName, 0777);
	
	
		// Get Width and Height of Temp Image
		$imageDimHash = ImageLib::GetDimensionsFromImageFile($imageIDfileName);
		$ImageWidth_Actual = $imageDimHash["Width"];
		$ImageHeight_Actual = $imageDimHash["Height"];
	
		
		// To speed up the process on Large JPEG images... if we are going to be scaling up, then we don't need as high up a compression level because the detailed pixels aren't really there.
		// However, if a user is resizing their jpeg to be of a smaller size, we want good clarity (like realtor logos, etc).
		if($isJPEG && ($ImageWidth_Actual < $newWidth))
			$qualityValue = "85";
	
		// Find out if the user resized the graphic.  If they did we need to resize as well. 
		// Because some precision is lost when converting graphic sizes between flash for DPI etc.
		// we only want to run ImageMagick resize if the difference is greater than 1/2%.  Otherwise we might be resizing unessasarily and losing image quality.
		$width_percentage_change = (abs($ImageWidth_Actual - $newWidth)) / $ImageWidth_Actual;
		$height_percentage_change = (abs($ImageHeight_Actual - $newHeight)) / $ImageHeight_Actual;
		if($width_percentage_change > 0.005 || $height_percentage_change > 0.005 ){
	
			// This is a system command that resizes images.  It will overwrite the temporary image on disk. 
			// I got the software for image transformation from www.imagemagick.org
			$resizeCommand = Constants::GetUpperLimitsShellCommand(200, 40) . Constants::GetPathToImageMagick() . "mogrify -quality $qualityValue -geometry " . $newWidth . "x" . $newHeight . "! " . $imageIDfileName;
			system($resizeCommand);
	
		}
	
	
		if($rotation <> 0){
	
			// This is a system command that rotates images.  It will overwrite the temporary image on disk. 
			// I got the software for image transformation from www.imagemagick.org
			$rotateCommand = Constants::GetUpperLimitsShellCommand(200, 40) . Constants::GetPathToImageMagick() . "mogrify -quality $qualityValue -rotate " . $rotation . " " . $imageIDfileName;
	
			// for debuging if you want to capture the system output 
			system($rotateCommand);
		}
	
	 
		return $imageIDfileName;
	}
	
	
	
	// Convert the coordinates from our Flash program to a registration point starting in the top left (in pixels).  Based upon DPI ratio
	static function TranslateCoordinates($CanvasWidth, $CanvasHeight, $OldX, $OldY, $dpi_Ratio, $BleedPixels){
	
		// This will translate the coordinates.
		// Our Flash program has a coordiante starting point in exactly the center of the content window.  We want to make the coords start at the upper-left corner.
		$X_coordinate = $OldX + $CanvasWidth/2;
		$Y_coordinate = $OldY + $CanvasHeight/2;
	
	
		// Expand the coordinates from the top-left edge based on the DPI ratio
		$X_coordinate = $X_coordinate * $dpi_Ratio;
		$Y_coordinate = $Y_coordinate * $dpi_Ratio;
	
		// Compensate for the bleed pixels which may expand the canvas area
		$X_coordinate += $BleedPixels;
		$Y_coordinate += $BleedPixels;
		
		return array("Xcoord"=>$X_coordinate, "Ycoord"=>$Y_coordinate);
	}
	
	
	
	
	
	// After a file has been uploaded.... It is written to a temporary file... then the file name is passed into this function.
	// It will convert the image into either a JPEG or a PNG 
	// Returns a blank string upon sucess.  Otherwise returns an error message 
	// Pass in the DPI of the artwork that the image will go on.  The DPI setting is ONLY used when converting Vector Images such as PDF, or EPS files.
	// By default we are limiting the upload size to 2.5 MB... but you can override that if you want to... 
	// .... just make sure it will fit in a DB table if that is where it will be stored.  LongBlob = 4096MB,  MediumBlob = 16MB, Blob = 0.0625 MB
	static function ConvertImageAfterUpload($ImageFileName, $dpi, $sizeLimit = 2.5){
	
		if(!preg_match("/^\d+$/", $dpi) || $dpi < 10 || $dpi > 600){
			$err = "Error in function ImageLib::ConvertImageAfterUpload.   The DPI setting is incorrect.";
			WebUtil::WebmasterError($err);
			exit($err);
		}
			
	
		$ImageFormat = ImageLib::GetImageFormatFromFile($ImageFileName);
	
		$LegalImageFormats = array( "JPEG", "TIFF", "BMP", "GIF", "PNG", "PDF", "EPS", "EPT" );
		$vectorFormats = array("PDF", "EPS", "EPT");
	
		if(!in_array($ImageFormat, $LegalImageFormats))
			return "The only types of image files you may upload are JPEG, PNG, GIF, BMP, TIFF, PDF, and EPS.  If your image is in any other format, use your favorite image editing software to convert.";
	
	
		// Sometimes the verbose output will detect errors with the image.  We don't want those to get into our system... Possible cause of memory leaks causing a big problem on the server lately???
		$DetailedImageInfo = ImageLib::GetImageInformation($ImageFileName, true);
		if(preg_match("/Corrupt/i", $DetailedImageInfo))
			return "Your image is in the proper format but it appears to have some corruption.  Please try saving your image again and re-uploading.  If the problem persists email your file to Customer Service.";
		else if(in_array($ImageFormat, $vectorFormats) && preg_match("/The following fonts were not embedded/i", $DetailedImageInfo))
			return "You tried to upload a PDF or EPS file and your font(s) were not embedded.  Try re-saving your file with 'Embedded Fonts' or 'Convert Fonts to Outlines' so that we can print your file as expected.";
		else if(preg_match("/error/i", $DetailedImageInfo))
			return "There was an error reading your file.  Please try re-saving your image from your graphic software in another format (PDF, EPS, or JPEG) and re-uploading.  If the problem persists then email your file to Customer Service.";
	
	
	
		$DimHash = ImageLib::GetDimensionsFromImageFile($ImageFileName);
	
		if(empty($DimHash["Width"]) || empty($DimHash["Height"]))
			return "The image appears to be empty.  If you continue to have problems, please email your file to Customer Service";
	
		$ImgWidth = $DimHash["Width"];
		$ImgHeight = $DimHash["Height"];
		
		// 4000 pixels is more than enough to cover 13 inches at 300 DPI.
		if($ImgWidth > 4000 || $ImgHeight > 4000)
			return "Your image is too large.  The height and width must be less that 4000 pixels.  Try resizing your image and making it smaller before uploading. If you continue to have problems uploading, email your file to Customer Service";
	
		// If a PDF, TIFF, or BMP is stored in CMYK format... we want to keep it in CMYK while converting to JPEG.  A separate process will	convert a CMYK jpeg to RGB.
		if(preg_match("/Colorspace:\s+CMYK/", $DetailedImageInfo))
			$destinationSpace = "CMYK";
		else
			$destinationSpace = "RGB";
	
		// The first thing to do is to convert all incompatible Image Formats 
		if($ImageFormat == "TIFF" || $ImageFormat == "BMP"){
			$ConverImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -format jpeg -colorspace $destinationSpace -quality 90 " . $ImageFileName;
			system($ConverImageCmd);
			
			ImageLib::OverwriteOriginalFileAfterImageMagickConversion($ImageFileName, "jpeg");
		}
		else if(in_array($ImageFormat, $vectorFormats)){
		
			// Put brackets after the file name to specify the page number.  We are always interested in just the first page.
			$ConverImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -format jpeg -colorspace $destinationSpace -quality 80 -density " . $dpi . "x" . $dpi . " " . $ImageFileName . "[0]";
			system($ConverImageCmd);
			
			ImageLib::OverwriteOriginalFileAfterImageMagickConversion($ImageFileName, "jpeg");
		}
		else if($ImageFormat == "GIF"){
			$ConverImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -format png24 " . $ImageFileName;
			system($ConverImageCmd);
			
			ImageLib::OverwriteOriginalFileAfterImageMagickConversion($ImageFileName, "png24");
		}
	
		
		$unknownErrorMsg = "An error occured converting your image.  Please report this problem to Customer Service and attach the image that you are trying to upload.";
	
		if(!file_exists($ImageFileName))
			return $unknownErrorMsg . " msg: 384042";
	
		//Make sure that we have a JPEG or PNG graphic after any conversions
		//This is a way to double check that everything is OK
		$ImageFormat = ImageLib::GetImageFormatFromFile($ImageFileName);
		if( $ImageFormat <> "JPEG" && $ImageFormat <> "PNG" )
			return $unknownErrorMsg;
	
	
	
		// Just because our Image is in PNG or JPEG format does not mean that we can use it.  There are incompatible types to watch out for
	
	
		// MING can't handle progressive Scan or Greyscale JPEG's   
		if($ImageFormat == "JPEG"){
			
			//This will contain about a page's worth of data about the image.
			$ImageInfo = ImageLib::GetImageInformation($ImageFileName);
	
			//Look for the words "Interlace: Plane" within the output.  This indicates it is an interlaced JPEG and we need to convert it
			if(preg_match("/Interlace:\s+Plane/i", $ImageInfo) || preg_match("/Interlace:\s+JPEG/i", $ImageInfo)){
				$ConverImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -interlace none " . $ImageFileName;
				system($ConverImageCmd);	
			}
			
			//If the JPEG is In CMYK mode then we need to convert it back to RGB... let the RIP station convert it back to CMYK
			//CMYK images do not work well with MING  or when making composite images with RGB
			//Plus let the printers and RIP stations make their own improvements if it ever gets closer to simulate RGB... Unix software is always behind
			if(preg_match("/Colorspace:\s+CMYK/", $ImageInfo)){
				$inputProfile = Constants::GetWebserverBase() . "/library/icc_profiles/USWebCoatedSWOP.icc";
				$outputProfile = Constants::GetWebserverBase() . "/library/icc_profiles/AdobeRGB1998.icc";
				$ConverImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "convert -profile \"$inputProfile\" -profile \"$outputProfile\" " . $ImageFileName . " " . $ImageFileName;
				system($ConverImageCmd);
			}
		}
		else if($ImageFormat == "PNG"){
	
			//This will contain a minimum amount of data about the image... We just want to make sure it is a PNG 24 and not a PNG 8
			$ImageInfo = ImageLib::GetImageInformation($ImageFileName, false);
			
			if(!preg_match("/DirectClass/", $ImageInfo)){
				$ConverImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -format png24 " . $ImageFileName;
				system($ConverImageCmd);
				
				ImageLib::OverwriteOriginalFileAfterImageMagickConversion($ImageFileName, "png24");
			}
		}
	
	
		// Currently the PHP limit is set to 3MB, let's set our upper a limit a little bit lower.  There is no reason for us to store images any larger than this.
		$NewSize = filesize($ImageFileName);
		if($NewSize > ($sizeLimit * 1024 * 1024))
			return "The file that you are trying to upload is too large.  Please ensure that the file size is less that $sizeLimit MB.";
		
	
		return "";
	}
	
	//After we change the format of an image... Image magick makes a copy and the copy always has the extension changed
	//Ideally ImageMagick should convert the existing file name and not make a copy
	//This function will copy the data from the copy... then delete the copy... then overrwrite the data with the original filename
	static function OverwriteOriginalFileAfterImageMagickConversion($OriginalFileName, $ExtraExtension){
	
		//If it is the development server then strip off the .tmp extension.. because image magick changes it for us.
		//The unix server just ads the extenion on to the end
		if(Constants::GetDevelopmentServer()){
			$OriginalFileNameWithoutExtension = preg_replace("/\.tmp$/", "", $OriginalFileName);
			$destinationFileName = $OriginalFileNameWithoutExtension . ".tmp";
		}
		else{
			$OriginalFileNameWithoutExtension = $OriginalFileName;
			$destinationFileName = $OriginalFileNameWithoutExtension;
		}
			
		
		$NewTempName = $OriginalFileNameWithoutExtension . "." . $ExtraExtension;
	
	
		if(!file_exists($NewTempName))
			throw new Exception("An unknown error occured while converting the image.");
	
		
		if(Constants::GetDevelopmentServer())
			$systemMoveCommand = "move";
		else
			$systemMoveCommand = "mv";

		system($systemMoveCommand . " " . escapeshellarg($NewTempName) . " " . escapeshellarg($destinationFileName));
	}
	
	
	// This will return a lot of information about the image... Use pattern matching to hunt for things within this 60 line text blook
	// It may contain information about CMYK Mode/vs. RGB... interlace, colors, etc.
	// Pass If the flag detailed is set to false... then it will only return 1 line of text like.....     JPEG 338x323 DirectClass 147kb 0.000u 0:01
	static function GetImageInformation ($ImageFileName, $Detailed = true){
	
		if($Detailed)
			$IdentifyExtra = "-verbose ";
		else
			$IdentifyExtra = "";
	
		$identify_command = Constants::GetUpperLimitsShellCommand(150, 40) . Constants::GetPathToImageMagick() . "identify $IdentifyExtra" . $ImageFileName;
		return  WebUtil::mysystem($identify_command);
	
	}
	
	//  Pass in a location of a file on disk.  It will return a hash with 2 elements.  "height" & "width".
	static function GetDimensionsFromImageFile($ImageFileName){
	
		$RetHash = array("Width"=>null, "Height"=>null);
	
		$ImageInfo = ImageLib::GetImageInformation ($ImageFileName, false);
		$matches = array();
		if(preg_match_all("/(\d+)x(\d+)/", $ImageInfo, $matches)){
	
			$RetHash["Width"] = $matches[1][0];
			$RetHash["Height"] = $matches[2][0];
	
		}
		return $RetHash;
	
	}
	
	// Same thing as ImageLib::GetDimensionsFromImageFile()... but pass in the binary data instead of a File Path
	static function GetDimensionsFromImageBinaryData(&$imageBinData){
	
		// Create temporary file on disk.
		$tempImageName = tempnam (Constants::GetTempDirectory(), "IMGSIZE");
		chmod ($tempImageName, 0666);
			
		$fp = fopen($tempImageName, "w");
		fwrite($fp, $imageBinData);
		fclose($fp);
		
		$retHash = ImageLib::GetDimensionsFromImageFile($tempImageName);
		
		@unlink($tempImageName);
		
		return $retHash;
	}
	
	
	static function GetDimensionsFromRasterImageID(DbCmd $dbCmd, $imageid){
	
		$ImageInfo = ImageLib::GetImageByID($dbCmd, $imageid);
	
		// Create temporary file on disk.
		$tempImageName = tempnam (Constants::GetTempDirectory(), "IMGSIZE");
		
		// Make sure that we have permission to modify it with system comands as "nobody" webserver user 
		chmod ($tempImageName, 0666);
			
		// Put image data into the temp file 
		$fp = fopen($tempImageName, "w");
		fwrite($fp, $ImageInfo["ImageData"]);
		fclose($fp);
		
		$retHash = ImageLib::GetDimensionsFromImageFile($tempImageName);
		
		unlink($tempImageName);
		
		return $retHash;
	}
	
	// Vector images store their dimensions in Picas. 
	// If you want to figure out how many pixels the vector image occupies, you have to specify the DPI.
	static function GetPixelDimensionsFromVectorImageID($vectorImageid, $dpi){
	
		$vectorImageid = intval($vectorImageid);
		$dpi = intval($dpi);
		
		if($dpi < 5 || $dpi > 1000)
			throw new Exception("Error in method GetPixelDimensionsFromVectorImageID. The DPI value is out of range.");
		
		$dbCmd = new DbCmd();
		$picasDimensionHash = self::getDimensionsPicasFromVectorImageID($dbCmd, $vectorImageid);

		$retHash = array();
		$retHash["Width"] = round($picasDimensionHash["Width"] / 72 * $dpi);
		$retHash["Height"] = round($picasDimensionHash["Height"] / 72 * $dpi);
		
		return $retHash;
	}
	
	// works the same as GetImageFormat but it takes in binary data instead.
	// return false if the data does not appear to be an image.
	static function GetImageFormatFromBinData(&$imageBinData){
	
		// Create temporary file on disk.
		$tempImageName = tempnam (Constants::GetTempDirectory(), "IMGTEST");
		chmod ($tempImageName, 0666);
			
		$fp = fopen($tempImageName, "w");
		fwrite($fp, $imageBinData);
		fclose($fp);
		
		$imageFormat = ImageLib::GetImageFormatFromFile($tempImageName);
		
		@unlink($tempImageName);
		
		return $imageFormat;
	}
	
	
	//May return JPEG, or GIF, or PNG, TIFF, etc.
	static function GetImageFormatFromFile($ImageFileName){
		$ImageInfo = ImageLib::GetImageInformation ($ImageFileName, false);
		
		$matches = array();
		if(preg_match_all("/(\w+)\s\d+x\d+/", $ImageInfo, $matches))
			return $matches[1][0];
		else
			return false;
	
	}
	
	
	// In case you already need detailed info on an Image... this will prevent you from having to run an system command twice... just share the Verbose info.
	static function GetImageFormatFromDetailedInfo($detailedInfo){
	
		$matches = array();
		if(preg_match_all("/Format:\s(\w+)\s/", $detailedInfo, $matches))
			return $matches[1][0];
		else
			return false;
	
	}
	
	//The Image should only be a JPEG or a PNG.  Will return "image/jpeg" etc.
	static function GetMimeCodeForImage($ImageFileName){
	
		$ImageFormat = ImageLib::GetImageFormatFromFile($ImageFileName);
		
	
		if($ImageFormat == "JPEG")
			return "image/pjpeg";
		else if($ImageFormat == "PNG")
			return "image/x-png";
		else if($ImageFormat == "GIF")
			return "image/gif";
		else{
			print "An unknown error occured with the mime format";
			exit;
		}
	}
	
	// This will insert the image into the proper table and also create a record in the image pointer table
	// It will return the ID from the image pointer table
	static function InsertImageRasterIntoDB($dbCmd, $viewType, &$ImageData, $imagefile_type, $UploadIdentify){
	
		// Create Hash for inserting into DB -
		$ImageInsertArr = array();
		$ImageInsertArr[ "FileSize"] = strlen($ImageData);
		$ImageInsertArr[ "FileType"] = $imagefile_type;
		$ImageInsertArr[ "DateUploaded"] = time();
		$ImageInsertArr[ "BinaryData"] = $ImageData;
	
	
		if($viewType == "template_category" || $viewType == "template_searchengine"){
			$TableName = ImageLib::GetImagesTemplateTableName($dbCmd);
		}
		else if($viewType == "customerservice" || $viewType == "saved" || $viewType == "proof"){
			$TableName = ImageLib::GetImagesSavedTableName($dbCmd);
		}
		else if($viewType == "projectssession" || $viewType == "session"){
	
			$TableName = "imagessession";
	
			//Images in the session have a couple more fields
			$ImageInsertArr[ "SID"] = WebUtil::GetSessionID();
			$ImageInsertArr[ "UploadIdentify"] = $UploadIdentify;
	
		}
		else{
			return false;
		}
	
	
		// Insert the binary image data
		$uploadid = $dbCmd->InsertQuery($TableName, $ImageInsertArr);
		
	
		// Insert the new Image Record into the ImagePointer table. -
		$ImgPointerArr = array();
	
		$ImgPointerArr[ "Record"] = $uploadid;
		$ImgPointerArr[ "TableName"] = $TableName;
		$imageID = $dbCmd->InsertQuery("imagepointer", $ImgPointerArr);
	
	
		return $imageID;
	}
	
	// Pass in a mime or file type into this function.
	// It will return the extention that goes with it.
	// Like EPT files actually have an extention of EPS.
	static function getImageExtentsionByFileType($fileDesc){
	
		if(preg_match("/ept/i", $fileDesc))
			return "eps";
		else if(preg_match("/eps/i", $fileDesc))
			return "eps";
		else if(preg_match("/pdf/i", $fileDesc))
			return "pdf";
		else if(preg_match("/jpeg/i", $fileDesc))
			return "jpg";
		else if(preg_match("/jpg/i", $fileDesc))
			return "jpg";
		else if(preg_match("/gif/i", $fileDesc))
			return "gif";
		else if(preg_match("/bmp/i", $fileDesc))
			return "bmp";
		else if(preg_match("/png/i", $fileDesc))
			return "png";
		else if(preg_match("/tif/i", $fileDesc))
			return "tif";
		else
			throw new Exception("Illegal file description in function ImageLib::getImageExtentsionByFileType");
	}
	
	
	
	// This will insert the Vector Image into the proper table and also create a record in the Vector Image pointer table
	// It will return the ID from the Vector Image pointer table
	// The Image Type must only be a supported vector type, like PDF or EPS
	// This function will allways convert the image into a PDF (if it is not already).
	// If the original file is not a PDF, then it will save the original copy in another blob field.
	// Returns false if there is an error.
	static function InsertImageVectorIntoDB($dbCmd, $viewType, &$ImageData, $vectorFileType, $originalVectorFileName, $UploadIdentify){
	
		if(!in_array($vectorFileType, array("PDF", "EPS", "EPT")))
			throw new Exception("Error in function ImageLib::InsertImageVectorIntoDB.  The file format is not supported.");
	
		if(strlen($originalVectorFileName) > 100)
			throw new Exception("The file name is too long in function ImageLib::InsertImageVectorIntoDB");
	
		$tmpVectorName = tempnam (Constants::GetTempDirectory(), "VECT");
		$fp = fopen($tmpVectorName, "w");
		fwrite($fp, $ImageData);
		fclose($fp);
		
		chmod ($tmpVectorName, 0666);
	
		$originalFileSize = strlen($ImageData);
		$originalFileType = $vectorFileType;
		
	
		// If the file format is not a PDF... then we need to convert it to a PDF.
		// Otherwise rename the file to have a PDF extension
		if($vectorFileType == "PDF"){
		
			if(Constants::GetDevelopmentServer())
				system("move  " . $tmpVectorName . " " . $tmpVectorName . ".pdf");
			else
				system("mv  " . $tmpVectorName . " " . $tmpVectorName . ".pdf");
			
			$tmpVectorName = $tmpVectorName . ".pdf";
		}
		else{
			
			// convert the vector image into 
			if(Constants::GetDevelopmentServer())
				$ghostScriptCommand = "gswin32c";
			else
				$ghostScriptCommand = "gs";
			
			$ConverToPDFcmd = $ghostScriptCommand . " -dSAFER -dBATCH -dNOPAUSE -dEPSCrop -sDEVICE=pdfwrite -sOutputFile=" . $tmpVectorName . ".pdf " . $tmpVectorName;
			
			// Write the command that we are about to execute to a log file.  I have found some "gs" processes going into an infinite loop.
			$logHandle = fopen(Constants::GetTempDirectory() . "/Ghostscript.log", "a");
			fwrite($logHandle, "Converting to PDF " . date("l dS \of F Y h:i:s A") . ": " . $ConverToPDFcmd . "\n");
			fclose($logHandle);
			
			WebUtil::mysystem($ConverToPDFcmd);
			
			// Get rid of the old file... and change the file name to point to our new temporary file (with PDF extention).
			//unlink($tmpVectorName);
			$tmpVectorName = $tmpVectorName . ".pdf";
		}
		
	
		$pdfObj = pdf_new();
		
		if(!Constants::GetDevelopmentServer())
			pdf_set_parameter($pdfObj, "license", Constants::GetPDFlibLicenseKey());
		
		PDF_begin_document($pdfObj, "", "");	
	

		pdf_set_info($pdfObj, "Title", "Artwork Proof");
		pdf_set_info($pdfObj, "Subject", "Filtered Vector Image");
	
		PDF_set_parameter($pdfObj, "SearchPath", Constants::GetFontBase());
		PDF_set_parameter($pdfObj, "SearchPath", Constants::GetTempDirectory());
	
	
		// Put the Virtual Lib file into a PDI object so that we can extract page(s) from it.
		$PDI_obj = PDF_open_pdi($pdfObj, $tmpVectorName, "", 0);
		
		if($PDI_obj <= 0)
			return false;
	
		chmod ($tmpVectorName, 0666);
		
		
		$totalPagesInPDFFile = PDF_get_pdi_value($pdfObj, "/Root/Pages/Count", $PDI_obj, 0, 0);
	
		if(empty($totalPagesInPDFFile))
			return false;
	
	
		
		// Get a PDF Page Object for page #1.  If there are more than one pages, we will discard it.
		$pageNumber = 1;
		$PDI_PageObj = PDF_open_pdi_page($pdfObj, $PDI_obj, $pageNumber, "");
	
	
		// In case we come across an error trying to extract the page.
		if($PDI_PageObj <= 0)
			return false;
	
		
		@unlink($tmpVectorName);	
	
	
		$picasWidth = PDF_get_pdi_value($pdfObj, "width", $PDI_obj, $PDI_PageObj, 0);
		$picasHeight = PDF_get_pdi_value($pdfObj, "height", $PDI_obj, $PDI_PageObj, 0);
	
		// To make sure that it fits in our DB.
		if(strlen($picasWidth) > 12)
			$picasWidth = round($picasWidth, 6);
		if(strlen($picasHeight) > 12)
			$picasHeight = round($picasHeight, 6);
			
		
		if(!preg_match("/^\d+(\.\d+)?$/", $picasWidth) || !preg_match("/^\d+(\.\d+)?$/", $picasHeight)){
			$errorMessage = "Error in Method ImageLib::InsertImageVectorIntoDB.  Can not determine the PDF Width or Height.";
			WebUtil::WebmasterError($errorMessage);
			exit($errorMessage);
		}
	
	
		// Insert the uploaded PDF into our PDF lib page... we will not be using the PDF lib page here... 
		// ... we just want to see if we can  merge the document without any errors.
		PDF_begin_page($pdfObj, $picasWidth, $picasHeight);
		PDF_fit_pdi_page($pdfObj, $PDI_PageObj, 0, 0, "adjustpage");
		PDF_end_page($pdfObj);
	
		PDF_close_pdi_page($pdfObj, $PDI_PageObj);
		
		// Close the PDI object.
		PDF_close_pdi($pdfObj, $PDI_obj);
		
		PDF_end_document($pdfObj, "");
		
		$pdfBinaryData = pdf_get_buffer($pdfObj);
	
	
	
		$embeddedText = null;
		
	
		// Create Hash for inserting into DB
		$ImageVectInsertArr = array();
		$ImageVectInsertArr[ "PDFbinaryData"] = $pdfBinaryData;
		$ImageVectInsertArr[ "EmbeddedText"] = $embeddedText;
		$ImageVectInsertArr[ "DateUploaded"] = time();
		$ImageVectInsertArr[ "OrigFileSize"] = $originalFileSize;
		$ImageVectInsertArr[ "OrigFileType"] = $originalFileType;
		$ImageVectInsertArr[ "OrigFileName"] = $originalVectorFileName;
		$ImageVectInsertArr[ "PDFfileSize"] = strlen($pdfBinaryData);
		$ImageVectInsertArr[ "PicasWidth"] = $picasWidth;
		$ImageVectInsertArr[ "PicasHeight"] = $picasHeight;
	
	
		if($viewType == "template_category" || $viewType == "template_searchengine"){
			$TableName = ImageLib::GetVectorImagesTemplateTableName($dbCmd);
		}
		else if($viewType == "customerservice" || $viewType == "saved" || $viewType == "proof"){
			$TableName = ImageLib::GetVectorImagesSavedTableName($dbCmd);
		}
		else if($viewType == "projectssession" || $viewType == "session"){
	
			$TableName = "vectorimagessession";
	
			//Images in the session have a couple more fields
			$ImageVectInsertArr[ "SID"] = session_id();
			$ImageVectInsertArr[ "UploadIdentify"] = $UploadIdentify;
	
		}
		else{
			return false;
		}
	
	
		// Insert the PDF binary file and all other information
		$uploadid = $dbCmd->InsertQuery($TableName, $ImageVectInsertArr);
	
		if(empty($uploadid))
			return false;
	
		// Do not try to put both blob fields in the Table with a single Insert Query or it could exceed our Max Packet allowed with MySQL.	
		$dbCmd->UpdateQuery($TableName, array("OrigFormatBinaryData"=>$ImageData), "ID=$uploadid");
	
	
		// Insert the new Image Record into the ImagePointer table.
		$ImgPointerArr = array();
		$ImgPointerArr[ "Record"] = $uploadid;
		$ImgPointerArr[ "TableName"] = $TableName;
		$imageID = $dbCmd->InsertQuery("vectorimagepointer", $ImgPointerArr);
	
	
		return $imageID;
	}
	
	
	
	// Pass in binary Image data.
	// this function will analyze if it is a vector image and then convert it to a JPEG and insert 2 images into the DB
	// Or it will insert just a JPEG.
	// This function will return an array with 3 elements.  The Rasterized Image ID, The Vector Image ID (if any), and an error message.
	// If you get an error message then the other 2 image ID's will be empty.
	// Identify is a unique ID used by Mac users who don't have 2 way flash communication with the browser.  It will POLL the server looking for that ID.
	static function InsertRasterAndVectorImageIntoDB(DbCmd $dbCmd, $viewTpe, &$BinaryData, $originalFileName, $dpi, $identify){
	
		$retArr = array("RasterImageID"=>null, "VectorImageID"=>null, "ErrorMsg"=>null);
	
		// Create a temporary file on disk 
		$tmpfname = tempnam (Constants::GetTempDirectory(), "UPLD");
		$fp = fopen($tmpfname, "w");
		fwrite($fp, $BinaryData);
		fclose($fp);
		
		chmod ($tmpfname, 0666);
		
		$DetailedImageInfo = ImageLib::GetImageInformation($tmpfname, true);
	
	
		// Find out if this is a Vector file before possibly converting it into a JPEG.
		$ImageFormat = ImageLib::GetImageFormatFromDetailedInfo($DetailedImageInfo);
		
		if(empty($ImageFormat)){
			unlink($tmpfname);
			$retArr["ErrorMsg"] = "We were not able to determine your image format.  Make sure that you can open the image in your browser (or common graphic software), then try uploading again.  Cutting-edge PDF formats may need to be converted to an older version or run through a PDF Optimizer before uploading.  If the problem persists, please email your artwork to Customer Service so they can help.";
			return $retArr;
		}
	
		$vectorFormats = array( "PDF", "EPS", "EPT" );
		if(in_array($ImageFormat, $vectorFormats))
			$isVectorFile = true;
		else
			$isVectorFile = false;
			
			
		// If it is a PDF file... make sure that it is less than version 1.6
		if($ImageFormat == "PDF"){
			
			$matches = array();
			if(preg_match("/Version:\s+PDF\-(\d+\.\d+)/", $DetailedImageInfo, $matches)){
				$pdfVersion = $matches[1];
				
				if($pdfVersion >= 1.6){
					$retArr["ErrorMsg"] = "In order to ensure reliable production we can only accept PDF files with versions 1.5 and below. You attempted to upload a PDF file with version $pdfVersion. Newer PDF versions have advanced features that are not compatible with today's printing presses, such as transparency, form elements, remotely stored images, etc. Try re-saving to an older format or ask Customer Service for assistance.";
					WebUtil::WebmasterError($retArr["ErrorMsg"]);
					return $retArr;
				}
			}
	
		}
	
	
		// Will convert the image file if needed... and do other touchups on the files
		// Will make sure that the Image is in JPEG or PNG format
		// Should return a blank string if there are no errors
		$imageUploadErrorMessage = ImageLib::ConvertImageAfterUpload($tmpfname, $dpi, 6);
		if(!empty($imageUploadErrorMessage)){
			unlink($tmpfname);
			
			$retArr["ErrorMsg"] = $imageUploadErrorMessage;
			return $retArr;
		}		
	
	
	
		$MimeCode = ImageLib::GetMimeCodeForImage($tmpfname);
	
		if(empty($MimeCode)){
			unlink($tmpfname);
			$retArr["ErrorMsg"] = "We were not able to determine your image format (after conversion).  Make sure that you can open the image in your browser (or common graphic software), then try uploading again.  If the problem persists, please email your artwork to Customer Service.";
			return $retArr;
		}
		
	
		// Read the binary data from the temporary file (which should be a raster JPEG or PNG at this point) and then delete temp file.
		$fd = fopen ($tmpfname, "r");
		$BinaryRasterData = fread ($fd, filesize ($tmpfname));
		fclose ($fd);
	
	
		unlink($tmpfname);
	
	
		$imageID = ImageLib::InsertImageRasterIntoDB($dbCmd, $viewTpe, $BinaryRasterData, $MimeCode, $identify);
	
	
		if($isVectorFile){
			$vectorImageID = ImageLib::InsertImageVectorIntoDB($dbCmd, "projectssession", $BinaryData, $ImageFormat, $originalFileName, $identify);
	
			if(empty($vectorImageID)){
			
				$retArr["ErrorMsg"] = "An error occurred trying to convert your (PDF, EPS, or Postscript) file.  If the problem persists, please email your artwork to Customer Service or convert your artwork to another format.";
				return $retArr;
			}
		}
		else{
			$vectorImageID = null;
		}
		
		
		$retArr["RasterImageID"] = $imageID;
		$retArr["VectorImageID"] = $vectorImageID;
		
		
		if(!preg_match("/^\d+$/", $retArr["RasterImageID"]) || $retArr["RasterImageID"] == 0 )
			$retArr["ErrorMsg"] = "An error occurred trying to save your image.  The Image ID could not be located. If the problem persists, please email your artwork to Customer Service.";
		
		return $retArr;
	
	
	}
	
	
	
	
	// Takes in the ImageID and returns what the name of the flash file is supposed to be
	static function GetMingFileName($TheImageID){
	
		return $TheImageID . ".swf";
	
		// I was going to encrypt this.. but then it turns into a hasle trying to pass this name to the SWF instead of an ImageID
		// If you try to recalculate in Flash Actionscript, then a hacker can use a decompiler to break it.
		/*
		$Crypt = md5($TheImageID);
		$Crypt = substr($Crypt, 0, 10);
		$Crypt = strrev($Crypt);
		$Crypt = substr($Crypt, 0, 4) . substr($Crypt, 5);
		return $Crypt . $TheImageID . "9.swf";
		*/
	
	}
	
	
	
	static function GetVectorImageIDsFromArtwork(&$ArtworkFile){
			
	
		$regex = "/<VectorImageId>(\d+)<\/VectorImageId>/i";
	
		$retArray = array();
		$m = array();
		
		if(preg_match_all($regex, $ArtworkFile, $m))
			$retArray = array_merge($retArray, $m[1]);
			
		return array_unique($retArray);
	}
	
	
	// Images may be stored in a Saved project Table, or the Session, or Templates
	// They all share a common image pointer.
	// This function will return true if the Image ID is linked to an image stored in one of the Template Tables.
	static function CheckIfImageBelongsToTemplateCollection(DbCmd $dbCmd, $imageID){
		
		$imageID = intval($imageID);
		
		$dbCmd->Query("SELECT TableName FROM imagepointer WHERE ID=$imageID");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call ImageLib::CheckIfImageBelongsToTemplateCollection, the Image ID does not exist: $imageID");
		
		if(preg_match("/template/", $dbCmd->GetValue()))
			return true;
		else
			return false;
	}
	
	
	
	
	
	// This will write the SWF file to disk for the given Image ID.  It will not duplicate work if the file is already there.
	// Set force = true if you want to write to disk no matter what.
	// Set a max pixel limit if you want to restrict the size of the MING preview to within that constraint (height or width).
	static function WriteMINGswfToDisk(DbCmd $dbCmd, $ImageID, $force = false, $maxPixelLimit = 2800){
	
		if(!preg_match("/^\d+$/", $ImageID))
			throw new Exception("Error with Image ID");
			
		if(!preg_match("/^\d+$/", $maxPixelLimit))
			throw new Exception("Error with Pixel limit");
			
	//	if($maxPixelLimit > 3000)
	//		throw new Exception("You can not have a pixel limit for SWF files above 3000 pixels or you could hit a 32 bit limitation.");
	
		$MingFileName = Constants::GetMingBase() . "/" . ImageLib::GetMingFileName($ImageID);
	
	
		//This will erase a preview SWF file if it exists... in case we want to refresh the file.
		if($force){
			if(file_exists($MingFileName))
				unlink($MingFileName);
		}
		
		//We don't need to write the flash file to disk because there is already a file there.
		if(file_exists($MingFileName))
			return;
	
		$ImageDataForMing = null;	
	
		// Fetch the image out of the database using a custom function in image_lib.php 
		$ImageInfo = ImageLib::GetImageByID($dbCmd, $ImageID);
	
		// Create temporary file on disk.
		$tempImageName = Constants::GetTempDirectory() . "/" . "MNG_" . $ImageID;
			
		// In case 2 processes start at the same time... let the first one finish first.
		if(file_exists($tempImageName))
			return;
			
		// Put image data into the temp file 
		$fp = fopen($tempImageName, "w");
		fwrite($fp, $ImageInfo["ImageData"]);
		fclose($fp);
	
		// We should only be storing JPEGs and PNG graphics in our image library.
	
		$imageFormat = ImageLib::GetImageFormatFromFile($tempImageName);
	
		// Get Width and Height of Temp Image
		$imageDimHash = ImageLib::GetDimensionsFromImageFile($tempImageName);
		
	
		$mingImageWidth = $imageDimHash["Width"];
		$mingImageHeight = $imageDimHash["Height"];
		
	//	$originalImageWidth = $mingImageWidth;
	//	$originalImageHeight = $mingImageHeight;
		
		// Coordinates in SWF are in twips (20 twips = 1 pixel) and 2^16 = 65536 = 3276.8 * 20
		// Because the operating system is 32 bits... we can not let one of the legs of the MING image exceed 3276 pixels or it will crash.
		// If one of the legs goes over this... then we need to resize the image before creating a SWF out of it.
		// ----------  Actually... instead of using 3276 pixels as the max....
		// We are going to make a Max pixel limit for the edting tool since resolution doesn't have to be that great in the design process
		// We are going to resize anything that goes over that limit, unless it width/height is too narrow.  If someone uploaded a large vertical bar it could go down to Zero pixels width/height.
		$minimumPixelLimit = 100;
		
		if(($mingImageWidth > $maxPixelLimit || $mingImageHeight > $maxPixelLimit) && ($mingImageWidth > $minimumPixelLimit && $mingImageHeight > $minimumPixelLimit)){
		
			if($mingImageWidth > $mingImageHeight){
				$resizeRatio = $mingImageHeight / $mingImageWidth;
				$newWidth = $maxPixelLimit;
				$newHeight = round($maxPixelLimit * $resizeRatio);
			}
			else{
				$resizeRatio = $mingImageWidth / $mingImageHeight;
				$newWidth = round($maxPixelLimit * $resizeRatio);
				$newHeight = $maxPixelLimit;
			}
			
			if($newHeight == 0)
				$newHeight = 1;
			if($newWidth == 0)
				$newWidth = 1;
	
			$resizeCommand = Constants::GetUpperLimitsShellCommand(180, 30) . Constants::GetPathToImageMagick() . "mogrify -quality 80 -geometry " . $newWidth . "x" . $newHeight . "! " . $tempImageName;
			system($resizeCommand);
			
			$mingImageWidth = $newWidth;
			$mingImageHeight = $newHeight;
		}
	
	
	
		// If this is a PNG graphic then we need to convert it into a dbl file (so MING can understand it) 
		if($imageFormat == "PNG"){
	
			// The file name has to end in ".png" for png2dbl to work 
			if(Constants::GetDevelopmentServer()){
				$systemMoveComand = "move  " . $tempImageName . " " . $tempImageName . ".png";
				$systemMoveComand = preg_replace("^/^", "\\", $systemMoveComand); // Convert forward slashes to backward slashes on windows OS.
				system($systemMoveComand);
			}
			else{
				system("mv  " . $tempImageName . " " . $tempImageName . ".png");
			}
	
			// We have to create an empty output file.. So the user "nobody" can write into the file 
			$fp = fopen($tempImageName . ".dbl", "w");
			fclose($fp);
	
			// Make sure that we have permission to modify it with system comands as "nobody" webserver user 
			chmod ($tempImageName . ".dbl", 0666);
	
	
			// Run png2dbl system command.... converts into an Image Object with transparency that Ming can understand. 
			system("png2dbl " . $tempImageName . ".png");
	
	
	
			// get dbl off of disk 
			$filename = $tempImageName . ".dbl";
			$fd = fopen ($filename, "r");
			$dblfile = fread ($fd, filesize ($filename));
			fclose ($fd);
	
			// Get rid of temporary files
			unlink($tempImageName . ".png");
			unlink($tempImageName . ".dbl");
	
			$ImageDataForMing = $dblfile;
	
		}
		else if($imageFormat == "JPEG"){
			
			// We can use JPG data immediately for import into Ming without conversion software 
			$fd = fopen ($tempImageName, "r");
			$ImageDataForMing = fread ($fd, filesize ($tempImageName));
			fclose ($fd);
			
			// Get rid of temporary file
			unlink($tempImageName);
		}
		else{
			unlink($tempImageName);
			WebUtil::WebmasterError("Error in function ImageLib::WriteMINGswfToDisk... The image format is unknown: $imageFormat - ImageID: $ImageID");
			throw new Exception("Error generating SWF file for Image in function ImageLib::WriteMINGswfToDisk");
		}
		
		// If an image fails to load, that the image data will be null.  We can't create a MING file out of that.
		if(empty($ImageDataForMing)){
			WebUtil::WebmasterError("Error in function ImageLib::WriteMINGswfToDisk... Image data failed to load: $imageFormat - ImageID: $ImageID");
			return;
		}
	
	
	
		//Launch Flash
		$movie = new swfMovie();
		//$movie->setBackground(255, 255, 255);
	
		//Import a bitmap
		$b = new SWFBitmap($ImageDataForMing);
	
		// We were making the Movie Width/Height match the original upload dimensions... but that turned out to have a bug... especially if over the max Twip width
		// It was also causing a problem with DPI warnings.
		// Really the correct place to set the Original Width is in the XML document... not try to fake the image width using SWF scaling.
		$w = $mingImageWidth;
		$h = $mingImageHeight;

		
		// If the GetWidth or Height fails... we can't draw a shape with no dimensions
		if(empty($w) || empty($h))
			return;
	
		//Make stage as big as the width and height of our bitmap
		$movie->setDimension($w, $h);
	
		//Convert Bitmap to a shape for Flash.
		$s = new SWFShape();
	
		//$s->setLine(1, 0x3f, 0x3f, 0x3f);
		$f = $s->addFill($b);
		$s->setLeftFill($f);
	
	
		//draw corners for the bitmap shape
		$s->drawLine($w, 0);
		$s->drawLine(0, $h);
		$s->drawLine(-$w, 0);
		$s->drawLine(0, -$h);
	
	
		//add our bitmap shape to this movieclip
		//Much like dragging a symbol from the library in Flash.
		//The first frame of the clip inside our editor is stuck on Frame 1.  After this movie Clip downloads it will send it to frame 3 with will initialize other stuff
		$movie->add($s);
	
		$movie->nextFrame();
		$movie->nextFrame();
		$movie->add(new SWFAction("ExistenceChecker = true; _parent.gotoAndPlay(3); stop();"));
		$movie->nextFrame();
	
		$movie->save($MingFileName);
	}
	
	
	// Writes the image to disk... having the proper extension for file type. 
	// File name in encrypted... based off of the image ID
	// The file name is returned
	static function WriteImageIDtoDisk(DbCmd $dbCmd, $ImageID){
			
		// Fetch the image out of the database
		$ImageInfoIndividual = ImageLib::GetImageByID($dbCmd, $ImageID);
	
		// Find out what the extension of the file name should be
		if(preg_match("/jpeg/i", $ImageInfoIndividual["ContentType"]))
			$imgExtension = "jpg";
		else if(preg_match("/png/i", $ImageInfoIndividual["ContentType"]))
			$imgExtension = "png";
		else if(preg_match("/gif/i", $ImageInfoIndividual["ContentType"]))
			$imgExtension = "gif";
	
		// The file name for image IDs... is    IMAGE_ID + "images"   .... then put into an MD5 hash 
		$filename = "temp_" . substr(md5($ImageID . "images"), 0, 18) . "." . $imgExtension;
	
		FileUtil::WriteDataToDiskIfNotExists(Constants::GetTempImageDirectory(), $filename, $ImageInfoIndividual["ImageData"]);
		
		return $filename;
	}
	
	static function outputTransparentGif(){
		
		// Print out a 1x1 transparent pixel.
		header("Content-Type: image/gif");
		header("Content-Length: 49");
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past so that the browser always accesses the tracking script.
		session_write_close();
		
		print pack('H*', '47494638396101000100910000000000ffffffffffff00000021f90405140002002c00000000010001000002025401003b');
		
	}
	
}


