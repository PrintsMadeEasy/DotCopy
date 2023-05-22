<?php

putenv ( 'TZ=America/Los_Angeles' );
define("TWENTY_FOUR_HOURS", 86400);

class Constants {
	
	static function GetDatabaseName() {
		return "dot";
	}
	static function GetDatabaseHost() {
		return "localhost";
	}
	static function GetDatabaseUserID() {
		return "root";
	}
	
	// For extra security, don't store the password in CVS or on a local computer. 
	// The password should manually be typed in on the server.
	static function GetDatabasePassword() {
		
		$passwordFile = Constants::GetWebserverBase() . "\\constants\\dbPasswordContainer";
		if(!file_exists ($passwordFile))
			exit ( "Error in static function GetDatabasePassword" );
		
		$fd = fopen ($passwordFile, "r");
		$pass = fread ($fd, filesize ($passwordFile));
		fclose ($fd);
		
		return trim($pass);
	}
	
	static function GetGoogleAdwordsPassword() {
		
		$passwordFile = Constants::GetWebserverBase() . "\\constants\\googleAdwordsPasswordContainer";
		if(!file_exists ($passwordFile))
			exit ( "Error in static function GetGoogleAdwordsPassword" );
		
		$fd = fopen ($passwordFile, "r");
		$pass = fread ($fd, filesize ($passwordFile));
		fclose ($fd);
		
		return trim($pass);
	}
	
	
	static function GetSalesTaxConstant($State) {
		if (strtoupper ( $State ) == Constants::GetSalesTaxState ()) {
			return 0.0825;
		} else {
			return 0;
		}
	}
	
	static function GetSalesTaxState() {
		return "CA";
	}
	
	// Setting this to TRUE will look for an encrypted cookie on the users machine before letting them access a secure area of the site
	// Be aware that cookies must be able to work... so an IP address will not do
	static function AuthenticateMemberSecurity() {
		return true;
	}
	

	static function GetServerSSL() {
		return "http";
	}
	
	
	static function GetPathToImageMagick() {
		return "C:\progra~1\ImageMagick-6.2.6-Q16/";
	}
	
	// Get a shell command for limiting the maximum file size a shell may execute and the max amount of seconds that it may run for.
	// PHP will lose these settings on subsequent "exec" calls.  In Linux you need to execute the whole call in one excec command. 
	// The semicolon let's us keep the ulimit settings for the rest of the "exec" call.
	static function GetUpperLimitsShellCommand($maxTempFileSize_Megabytes, $maxExecutionTime_Seconds) {
		
		if(!empty($maxTempFileSize_Megabytes)){
			if(!empty($maxExecutionTime_Seconds)){
				// Nothing... just preventing a compiler warning for unused variables on the Dev machine.
				return "";
			}
		}
		return "";
	}
	
	static function GetCurlCommand() {
		return "curl";
	}
	
	static function GetTempDirectory() {
		return 'C:\\WINDOWS\\Temp';
	}
	static function GetImageCacheDirectory() {
		return 'C:\\WINDOWS\\Temp';
	}
	static function GetReportCacheDirectory() {
		return 'C:\\WINDOWS\\Temp';
	}
	static function GetTempImageDirectory() {
		return Constants::GetWebserverBase () . "\\image_preview";
	}
	static function GetFileAttachDirectory() {
		return Constants::GetWebserverBase () . "\\customer_attachments";
	}
	

	
	static function GetFontBase() {
		return Constants::GetWebserverBase () . "\\fonts\\";
	}

	static function GetMingBase() {
		return Constants::GetWebserverBase () . "\\ming";
	}
	static function GetWebserverBase() {
		return "C:\\inet\\dot";
	}
	static function GetInvoiceLogoPath() {
		return Constants::GetWebserverBase () . "\\domain_logos";
	
	}
	static function GetAccountBase() {
		return "C:\\inet";
	}
	
	static function GetImageCreateCommand() {
		return "imagecreate";
	}
	
	static function GetDevelopmentServer() {
		return true;
	}
	
	static function GetAdminName() {
		return "Brian Piere";
	}
	static function GetAdminEmail() {
		return "Brian@PrintsMadeEasy.com";
	}
	
	
	static function GetMasterServerEmailName() {
		return "Dot Copy Server";
	}
	static function GetMasterServerEmailAddress() {
		return "Server@PrintsMadeEasy.com";
	}
	
	
	static function GetShippingDB_datasrc() {
		return "dot";
	}
	static function GetShippingDB_userid() {
		return "";
	}
	static function GetShippingDB_password() {
		return "";
	}
	static function GetShippingDB_hostname() {
		return "";
	}
	
	static function GetTarFileListCommand($DestFile, $FileList) {
		
		// Make a space deliminates string of the files
		$fileStr = "";
		foreach ( $FileList as $fileName ) {
			$fileStr .= $fileName . " ";
		}
		
		return "tar cf $DestFile $fileStr";
	}
	
	
	static function FlushBufferOutput() {
		flush ();
	}
	
	static function GetPDFlibLicenseKey() {
		
		return "L600202-020500-720714-245A22";
	}
	
	// Returns an array of email addresses to send reports to for various server-related activites.
	static function getEmailContactsForServerReports(){
		return array("Brian@PrintsMadeEasy.com", "Brian@DotGraphics.net", "BillBench@Msn.com");
	}
	
	// Who do we let know when stuff isn't going out on time.
	static function getEmailContactsForLateShipments(){
		return array("Brian@PrintsMadeEasy.com", "Brian@DotGraphics.net", "BillBench@Msn.com", "Susie@PrintsMadeEasy.com", "hope@printsmadeeasy.com");
	}
	
	static function getAdminSecuritySalt() {
		
		$saltFile = Constants::GetWebserverBase() . "\\constants\\AdminSecuritySalt";
		if(!file_exists ($saltFile))
			throw new Exception( "Error in static function getAdminSecuritySalt" );
		
		$fd = fopen ($saltFile, "r");
		$salt = fread ($fd, filesize ($saltFile));
		fclose ($fd);
		
		return trim($salt);
	}
	
	static function getGeneralSecuritySalt() {

		$saltFile = Constants::GetWebserverBase() . "\\constants\\GeneralSecuritySalt";
		if(!file_exists ($saltFile))
			throw new Exception( "Error in static function getGeneralSecuritySalt" );
		
		$fd = fopen ($saltFile, "r");
		$salt = fread ($fd, filesize ($saltFile));
		fclose ($fd);
		
		return trim($salt);
	}	

}

