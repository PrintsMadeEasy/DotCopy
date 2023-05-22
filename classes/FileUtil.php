<?

class FileUtil {
	
	// I had a difficulty getting the PECL file extention linked, ad well as the mime_content_type function included
	// We don't have that many file extentions to proxy... plus this more rapid than running through a heurisitcs scan.
	// Pass in a filename on disk and will return something like "image/gif"
	static function getMimeTypeByExtentionOffDisk($fileName){

		if(!file_exists($fileName))
			throw new Exception("Error in method getMimeTypeByExtentionOffDisk.  The file does not exist.");

		$matches = array();
		if(!preg_match("/\.((\w|\d){1,7})$/", $fileName, $matches))
			throw new Exception("Error in method getMimeTypeByExtentionOffDisk.  An extention was not found within the filename");
		
		$ext = strtoupper($matches[1]);
		
		return self::getMimeTypeFromExtentionOnly($ext);
	}
	static function getMimeTypeByFileNameStr($fileName){

		$fileName = self::CleanFileName($fileName);

		$matches = array();
		if(!preg_match("/\.((\w|\d){1,7})$/", $fileName, $matches))
			throw new Exception("Error in method getMimeTypeByFileNameStr.  An extention was not found within the filename");
		
		$ext = strtoupper($matches[1]);
		
		return self::getMimeTypeFromExtentionOnly($ext);
	}
	
	
	
	// Pass in a string like "JPEG"
	private static function getMimeTypeFromExtentionOnly($ext){
		
		$ext = strtoupper($ext);
		
		if($ext == "GIF")
			return "image/gif";
		else if($ext == "JPG" || $ext == "JPEG" )
			return "image/jpeg";
		else if($ext == "HTM" || $ext == "HTML" || $ext == "STAGING" )
			return "text/html";
		else if($ext == "SWF" )
			return "application/x-shockwave-flash";
		else if($ext == "FLV" )
			return "video/x-flv";
		else if($ext == "PNG" )
			return "image/png";
		else if($ext == "CSS" )
			return "text/css";
		else if($ext == "ICO" )
			return "image/x-icon";
		else if($ext == "JS" )
			return "application/x-javascript";
		else if($ext == "MPEG" || $ext == "MPG" )
			return "video/mpeg";
		else if($ext == "ZIP" )
			return "application/zip";
		else if($ext == "XML" )
			return "text/xml";
		else if($ext == "TXT" )
			return "text/plain";
		else if($ext == "GZ" )
			return "application/x-compressed";
		else if($ext == "TAR" )
			return "application/x-tar";
		else if($ext == "GG" )
			return "application/octet-stream";
		else if($ext == "PDF" )
			return "application/pdf";
		else if($ext == "TXT" )
			return "text/plain";
		else if($ext == "CSV" )
			return "text/csv";
		else if($ext == "AI" )
			return "application/postscript";
		else if($ext == "PSD" )
			return "application/octet-stream";
		else if($ext == "XLS" )
			return "application/excel";
		else if($ext == "DOCX" )
			return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
		else if($ext == "XLSX" )
			return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
		else
			throw new Exception("Error in method getMimeTypeByExtentionOffDisk.  the extention has not been defined yet.");
	}


	
	// Specify a directory and file name...  will create the filename and write the data to disk if the file does not exist
	// If it does exist... then it won't hurt the file or change it.
	// Does not return anything
	static function WriteDataToDiskIfNotExists($dir, $filename, &$TheData){

		if ($dir[strlen($dir) - 1] == '/')
			$trailing_slash = "";
		else
			$trailing_slash = "/";

		/*The PHP function is_dir returns true on files that have no extension.
		The filetype function will tell you correctly what the file is */
		if (!is_dir(realpath($dir)) || filetype(realpath($dir)) != "dir") {
			print "There is an error with the directory path... $dir";
			exit;
		}
		if (!is_writable($dir)){
			print "The directory is not writable... $dir";
			exit;
		}

		$fullPath = $dir . $trailing_slash . $filename;

		if(!file_exists($fullPath)){
			$fp = fopen($fullPath, "w");
			fwrite($fp, $TheData);
			fclose($fp);
		}

	}
	
	
	// Pass in an array of elements, this function will convert the array into a comma delimited string suitable for CSV files. 
	static function csvEncodeLineFromArr($arr){
	
		$retStr = "";
	
		foreach($arr as $thisElement){
	
			// Fields with an embedded double quote must have the field surounded by double quotes and have all of the double quotes insided replaced by a pair of doubles
			// Fields with an embedded comma must be surrounded in double quotes.
			if(preg_match('/"/', $thisElement))
				$thisElement = '"' . preg_replace('/"/', '""', $thisElement) . '"';
			else if(preg_match("/,/", $thisElement))
				$thisElement = '"' . $thisElement . '"';
	
			$retStr .= $thisElement . ",";
		}
	
		// strip off the last comma
		if(!empty($retStr))
			$retStr = substr($retStr, 0, -1);
		
		return $retStr;

	}
	
	// I didn't have PHP 5.3 installed at the time... so this is a replacement for the function "str_getcsv" 
	// This function will parse a CSV string and return the array.
    function csvExplode($input, $delimiter = ",", $enclosure = '"', $escape = "\\") { 
        $maxMemory = round(0.5 * 1024 * 1024); 
        $fp = fopen("php://temp/maxmemory:$maxMemory", 'r+'); 
        fputs($fp, $input); 
        rewind($fp); 

        $data = fgetcsv($fp, 1000, $delimiter, $enclosure); //  $escape only got added in 5.3.0 
        
        // Prevent a compiler warning
        if(empty($escape))
        	$escape = null;

        fclose($fp); 
        return $data; 
    } 
	
	
	// The tempnam() function will not let you specify a postfix to the filename created.
	// Here is a function that will create a new filename with pre and post fix'es.
	// Returns false if it can't create in the dir specified where tempnam() creates in the systems temp dir.
	//  Optionaly pass in a seed value if you want to help distinguish files names on the development server
	static function newtempnam($dir, $prefix, $postfix, $seed="giberish"){
	    // Creates a new non-existant file with the specified post and pre fixes //

		if ($dir[strlen($dir) - 1] == '/')
			$trailing_slash = "";
		else
			$trailing_slash = "/";

		//The PHP function is_dir returns true on files that have no extension.
		//The filetype function will tell you correctly what the file is //
		if (!is_dir(realpath($dir)) || filetype(realpath($dir)) != "dir") {
			// The specified dir is not actualy a dir
			return false;
		}
		if (!is_writable($dir)){
			// The directory will not let us create a file there
			return false;
		}

		// Some of the function below dont work on the windows server
		if(Constants::GetDevelopmentServer()){
			$filename = $dir . $trailing_slash . $prefix . substr(md5(microtime()), 0, 12) . $postfix;
			usleep(100);
		}
		else{
			do{
				$seed = substr(md5(microtime().posix_getpid()), 0, 8);
				$filename = $dir . $trailing_slash . $prefix . $seed . $postfix;
			}
			while (file_exists($filename));
		}

		$fp = fopen($filename, "w");
		fclose($fp);
		return $filename;
	}

	
	
	
	// Makes sure that the file name is OK to put on our disk... in case being uploaded by a customer... etc.
	static function CleanFileName($fileName){
	
		$fileName = trim($fileName);
		
		$fileName = WebUtil::FilterData($fileName, FILTER_SANITIZE_STRING_ONE_LINE);
		
		// Stripping the last 50 will keep the extention (if the file name is too long)
		if(strlen($fileName) > 50)
			$fileName = substr($fileName, -50);
	
		// A clever trick would be if you attached a file to the server with the follwing filename  "oops.php?ext=something.gif"  Then the contents of the file could be malicious php code.
		// There are a few other tricks I can think of.  Getting rid of all of these characters should do it.. I hope!
		$fileName = preg_replace("/\?/i", "", $fileName);
		$fileName = preg_replace("/&/i", "", $fileName);
		$fileName = preg_replace("/\^/i", "", $fileName);
		$fileName = preg_replace("/=/i", "", $fileName);
		$fileName = preg_replace("/\s/i", "_", $fileName);
		$fileName = preg_replace("/%/i", "", $fileName);
		$fileName = preg_replace("/\//i", "", $fileName);
		$fileName = preg_replace("/\\\\/i", "", $fileName);
		$fileName = preg_replace("/(\n|\r|\t)/i", "", $fileName);
		$fileName = preg_replace("/php/i", "ph_p", $fileName);
		$fileName = preg_replace("/cgi/i", "cg_i", $fileName);
		$fileName = preg_replace("/\.ph/i", "p_h", $fileName);
		$fileName = preg_replace("/\.sh/i", "s_h", $fileName);
		$fileName = preg_replace("/\.pl/i", "p_l", $fileName);
		$fileName = preg_replace("/\.asp/i", "as_p", $fileName);
		$fileName = preg_replace("/\.exe/i", "ex_e", $fileName);
		$fileName = preg_replace("/\.bat/i", "ba_t", $fileName);
		$fileName = preg_replace("/\.msi/i", "ms_i", $fileName);
		$fileName = preg_replace("/\.com/i", "co_m", $fileName);
		
		// Get rid of non-ascii characters
		$fileName = preg_replace('/[^(\x20-\x7F)]*/','', $fileName);
		
		
		// That should take care of the most serious threats.
		// Now we want to get Rid of any "period" in the file name to keep CSR's from clicking on a bad windows attachment.
		// Substitute our known good extentions with a ^ symbol in front temporarily while we wipe out the periods.
		$legalFileExtensionsArr = FileUtil::GetLegalAttachmentExtensions();
		
		foreach ($legalFileExtensionsArr as $thisExtension){
			$fileName = preg_replace("/\.$thisExtension$/i", ("^" . $thisExtension), $fileName);
		}
		
		// Get rid of any left over periods
		// Then put back our persiod for the valid extension
		$fileName = preg_replace("/\./", "_", $fileName);
		$fileName = preg_replace("/\^/", ".", $fileName);

		if(empty($fileName))
			throw new Exception("The file name can not be left blank.");
		
		return $fileName;
	}


	// Don't write the attachment to disk if it has an illegal attachment
	static function CheckIfFileNameIsLegal($fileName){

		$fileName = FileUtil::CleanFileName($fileName);

		//Get a list of known good extensions
		$LegalAttachmentsArr = FileUtil::GetLegalAttachmentExtensions();

		foreach($LegalAttachmentsArr as $thisLegalAtt){
			if(preg_match('/\.' . $thisLegalAtt  . '$/i', $fileName ))
				return true;
		}
		return false;
	}


	// Returns an array with the list of extensions that we may accept
	static function GetLegalAttachmentExtensions(){

		return array(
			"gif",
			"jpg",
			"jpeg",
			"png",
			"bmp",
			"doc",
			"docx",
			"xlsx",
			"pub",
			"psd",
			"eps",
			"ps",
			"ai",
			"tif",
			"tiff",
			"pdf",
			"html",
			"htm",
			"txt",
			"wav",
			"pdf",
			"ttf",
			"xls",
			"ppt",
			"csv"
		);
	}
	
	
	
}

?>