<?

require_once("library/Boot_Session.php");


#-- Make this script be able to run for at least 30 mintues  --#
set_time_limit(2000);






$dbCmd = new DbCmd();


$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



$PDFprofile = WebUtil::GetInput("pdfprofile", FILTER_SANITIZE_STRING_ONE_LINE);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$DownloadFileName = WebUtil::GetInput("filename", FILTER_SANITIZE_URL);



$t = new Templatex(".");

$t->set_file("origPage", "ad_multibatchpdf-template.html");



// There are a number of name/value pairs that come in the URL containing Project Lists and FileNames
// Many project artworks may be combined into 1 file, there may be up to 10 files for different print queues
$ProjectIDlistArr = array();
$FileNameArr = array();
$PDFcollectionFileNames = array(); 



// Is a multi-dim array with 2 levels that holds all of the product ID's with the 
$TotalProjectIDarr = array();

for($i=1; $i<=10; $i++){
	$ProjectIDlistArr[$i] = WebUtil::GetInput("projectlist" . $i, FILTER_SANITIZE_STRING_ONE_LINE);
	$FileNameArr[$i] = WebUtil::GetInput("filename" . $i, FILTER_SANITIZE_STRING_ONE_LINE);
	
	$TotalProjectIDarr[$i] = array();


	// The Project list has each ID separated by a pipe symbol
	$MasterList = split("\|", $ProjectIDlistArr[$i]);

	// Besides building an SQL query, this is useful to strip off any extra pipe symbols and check for integrity
	foreach($MasterList as $ProjectID){
		if(preg_match("/^\d+$/", $ProjectID)){
			
			ProjectBase::EnsurePrivilagesForProject($dbCmd, $ProjectID);
		
			// Keep adding to our global array of Project ID's
			$TotalProjectIDarr[$i][] = $ProjectID;

			// If we are marking as queued.. then update the DB and keep a record of the status change
			// Make sure we are only changing to "Queued" if the status is currently set to "Proofed".  This shouldn't ever happen, but better safe than sorry.
			if($view == "markqueued"){
				if(ProjectOrdered::GetProjectStatus($dbCmd, $ProjectID) == "P"){
					ProjectOrdered::ChangeProjectStatus($dbCmd, "Q", $ProjectID);
					ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, "Q", $UserID);
				}
			}
		}
		else{
			if(!empty($ProjectID))
				throw new Exception("Error, there is an invalid Project ID ... $ProjectID");
		}
	}
}




// Set all of the ProjectID within hidden inputs on the form
for($i=1; $i<=10; $i++){
	$t->set_var("PROJ" . $i, $ProjectIDlistArr[$i]);
	$t->set_var("FILENAME" . $i, $FileNameArr[$i]);

}

$t->set_var("PDFPROFILE", $PDFprofile);
$t->set_var("VIEW", $view);




if($view == "new"){

	// Spit out the template first so the user has some eye candy to to see... and a progress meter while the system generates the files
	// We are going Write information at the bottom of the HTML to show our progress with generating the files.
	$t->set_var("JS_ONLOAD", "");
	
	$t->discard_block("origPage", "MarkQueuedBL");
	$t->discard_block("origPage", "CloseWindowBL");
	
	// Print out the tempalte
	$t->pparse("OUT","origPage");
	Constants::FlushBufferOutput();
	
}


// If we are generating the PDF's, then generate each project list and keep adding to the collection of files that we will be 'tar'ing.
if($view == "new"){

	for($i=1; $i<=10; $i++){
		if(sizeof($TotalProjectIDarr[$i]) <> 0){

			$FileNamePrefix = $FileNameArr[$i];

			// Generate the large PDF document and append the file name to our list... that we will later Tar.
			// It will also output its progress to the Javascript
			$batchID = date("Y:m:d:H:i:s");
			$PDFcollectionFileNames[] = PdfDoc::GenerateSingleFilePDF($dbCmd, $TotalProjectIDarr[$i], $PDFprofile, $FileNamePrefix, $batchID, "javascript_percent");
		}
	}
}




if($view == "new"){

	print "\n<script>\n";
	print "ShowProgress('Compressing Files');";
	print "\n</script>\n";

	// We can't run the TAR command on the windows machine
	if(!Constants::GetDevelopmentServer()){

		// We want to Tar up all of the PDF files.
		// So loop through the collection of file names and build a tar command
		$PDFfilenamesArr = array();
		foreach($PDFcollectionFileNames as $ThisPDFname)
			$PDFfilenamesArr[] = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $ThisPDFname;

		$TarFilePrefix = time();
		$TarFileName = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $TarFilePrefix . ".tar";
		$URLofDownload = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $TarFilePrefix . ".tar";

		$UnixTarCommand = Constants::GetTarFileListCommand($TarFileName , $PDFfilenamesArr);
		system($UnixTarCommand);

		##-- Make sure that we have permission to modify it with system comands --##
		chmod ($TarFileName, 0666);

	}
	else{
		$URLofDownload = "nothing to download";
	}
	
	print "\n<script>\n";
	print "DownloadFile('"  . $URLofDownload . "');";
	print "\n</script>\n";

}
else if($view == "markqueued"){

	// When this page comes up ... make sure to reload the parent page.
	$JS_Onload = "window.opener.location = window.opener.location;";
	$t->set_var("JS_ONLOAD", $JS_Onload);
	
	// Erase the spinning Wheel
	$t->discard_block("origPage", "SpinningWheelBL");
	$t->discard_block("origPage", "MarkQueuedBL");
	
	$t->pparse("OUT","origPage");

}
else if($view == "download"){

	// When this page comes up ... make sure to reload the parent page.
	$JS_Onload = "document.location = \"".addslashes($DownloadFileName)."\";";
	$t->set_var("JS_ONLOAD", $JS_Onload);
	
	// Erase the spinning Wheel
	$t->discard_block("origPage", "SpinningWheelBL");
	$t->discard_block("origPage", "CloseWindowBL");
	
	$t->pparse("OUT","origPage");

}
else{
	throw new Exception("Illegal View type");
}



?>