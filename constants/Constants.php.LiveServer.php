<?php

// Set the Time zone for PHP.
putenv ('TZ=America/Los_Angeles'); 
putenv ('SHELL=/bin/bash'); 
putenv ('PATH=/usr/local/bin:/usr/local/jdk/bin:/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin:/usr/X11R6/bin:/usr/X11R6/bin:/root/bin'); 
define("TWENTY_FOUR_HOURS", 86400);

class Constants {
	
	static function GetDatabaseName() {
		return "dot_db";
	}
	static function GetDatabaseHost() {
		return "localhost";
	}
	static function GetDatabaseUserID() {
		return "dotuser";
	}
	
	// For extra security, don't store the password in CVS or on a local computer. 
	// The password should manually be typed in on the server.
	static function GetDatabasePassword() {
		
		$passwordFile = "/home/printsma/constants/dbPasswordContainer";
		if(!file_exists ($passwordFile))
			exit ( "Error in function for retrieving the password." );
		
		$fd = fopen ($passwordFile, "r");
		$pass = fread ($fd, filesize ($passwordFile));
		fclose ($fd);
		
		return trim($pass);
	}
	
	static function GetGoogleAdwordsPassword() {
		
		$passwordFile = "/home/printsma/constants/googleAdwordsPasswordContainer";
		if(!file_exists ($passwordFile))
			exit ( "Error in static function GetGoogleAdwordsPassword" );
		
		$fd = fopen ($passwordFile, "r");
		$pass = fread ($fd, filesize ($passwordFile));
		fclose ($fd);
		
		return trim($pass);
	}
	
	static function GetSalesTaxConstant($State){
		if(strtoupper($State) == Constants::GetSalesTaxState()){
			return 0.0825;
		}
		else{
			return 0;
		}
	}
	
	static function GetSalesTaxState(){
		return "CA";
	}
	
	
	//Setting this to TRUE will look for an encrypted cookie on the users machine before letting them access a secure area of the site
	//Be aware that cookies must be able to work... so an IP address will not do
	static function AuthenticateMemberSecurity(){
		return true;
	}
	
	
	static function GetServerSSL(){
		//if(Domain::getDomainKeyFromURL() != "Postcards.com"){
			return "https";
		//}
		//else{
		//	return "http";
		//}
	}

	
	static function GetDatabaseSource(){
		return "LIVE";
	}
	
	static function GetPathToImageMagick(){
		return "/usr/local/bin/";
	}
	
	// Get a shell command for limiting the maximum file size a shell may execute and the max amount of seconds that it may run for.
	// PHP will lose these settings on subsequent "exec" calls.  In Linux you need to execute the whole call in one excec command. 
	// The semicolon let's us keep the ulimit settings for the rest of the "exec" call.
	static function GetUpperLimitsShellCommand($maxTempFileSize_Megabytes, $maxExecutionTime_Seconds) {
		
		$maxTempFileSize_Megabytes = abs(intval($maxTempFileSize_Megabytes));
		$maxExecutionTime_Seconds = abs(intval($maxExecutionTime_Seconds));
		
		// Linux uses "block" size for the "ulimit" command.  A block is typically 1024 bytes.
		$maxBlockSize = $maxTempFileSize_Megabytes * 1024;
		
		return "ulimit -f $maxBlockSize -t $maxExecutionTime_Seconds ; ";
	}
	
	static function GetCurlCommand(){
		return "/usr/bin/curl";
	}
	
	static function GetTempDirectory(){
		return "/home/printsma/TempFiles";
	}
	static function GetImageCacheDirectory(){
		return "/home/printsma/ImageCaching";
	}
	static function GetReportCacheDirectory(){
		return "/home/printsma/ReportCaching";
	}
	static function GetTempImageDirectory(){
		return Constants::GetWebserverBase() . "/image_preview";
	}
	static function GetFileAttachDirectory(){
		return Constants::GetWebserverBase() . "/customer_attachments";
	}

	static function GetFontBase(){
		return Constants::GetWebserverBase() . "/fonts/";
	}
	
	
	static function GetMingBase(){
		return Constants::GetWebserverBase() . "/ming";
	}
	
	static function GetWebserverBase(){
		return "/home/printsma/public_html";
	}
	
	static function GetInvoiceLogoPath(){
		return Constants::GetWebserverBase() . "/domain_logos";
	
	}
	
	static function GetAccountBase(){
		return "/home/printsma";
	}
	
	
	static function GetImageCreateCommand(){
		return "imagecreatetruecolor";
	}
	
	static function GetDevelopmentServer(){
		return false;
	}
	
	static function GetAdminName(){
		return "Brian Piere";
	}
	static function GetAdminEmail(){
		return "Brian@PrintsMadeEasy.com";
	}
	
	static function GetMasterServerEmailName() {
		return "Dot Copy Server";
	}
	static function GetMasterServerEmailAddress() {
		return "Server@PrintsMadeEasy.com";
	}
	
	
	
	static function GetShippingDB_datasrc(){
		return "printsma_shipping";
	}
	static function GetShippingDB_userid(){
		return "printsma_worldsh";
	}
	static function GetShippingDB_password(){
		return "MyShippingDB*";
	}
	static function GetShippingDB_hostname(){
		return "localhost";
	}
	
	static function GetTarFileListCommand($DestFile, $FileList){
	
		#-- Make a space deliminates string of the files --#
		$fileStr = "";
		foreach($FileList as $fileName){
			$fileStr .= $fileName . " ";
		}
	
		return "tar cf $DestFile $fileStr";
	}
	
	
	
	
	static function FlushBufferOutput(){
	
		//ob_flush();flush();
		flush();
	}
	
	
	static function GetPDFlibLicenseKey(){
	
		return "L600202-020500-720714-245A22";
	}
	
	static function getEmailContactsForServerReports(){
		return array("Brian@PrintsMadeEasy.com", "Brian@DotGraphics.net");
	}
	
	// Who do we let know when stuff isn't going out on time.
	static function getEmailContactsForLateShipments(){
		return array("Brian@PrintsMadeEasy.com", "Brian@DotGraphics.net", "Susie@PrintsMadeEasy.com", "hope@printsmadeeasy.com");
	}
	
	static function getAdminSecuritySalt() {
		
		$saltFile = "/home/printsma/constants/AdminSecuritySalt";
		if(!file_exists ($saltFile))
			throw new Exception( "Error in static function getAdminSecuritySalt" );
		
		$fd = fopen ($saltFile, "r");
		$salt = fread ($fd, filesize ($saltFile));
		fclose ($fd);
		
		return trim($salt);
	}
	
	static function getGeneralSecuritySalt() {

		$saltFile = "/home/printsma/constants/GeneralSecuritySalt";
		if(!file_exists ($saltFile))
			throw new Exception( "Error in static function getGeneralSecuritySalt" );
		
		$fd = fopen ($saltFile, "r");
		$salt = fread ($fd, filesize ($saltFile));
		fclose ($fd);
		
		return trim($salt);
	}	
	
	
}

