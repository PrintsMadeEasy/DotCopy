<?php



class DbConnect {
	
	public $Handle = NULL;
	public $DataSource = NULL;
	
	static protected $defaultDBconnection = NULL;
	
	function __construct($DataSrc = NULL, $UserId = NULL, $Password = NULL, $HostName = NULL) {
		
		//If no parameters passed, then use our default database parameters from our constants file.
		if ($DataSrc == NULL) {
			$DataSrc = Constants::GetDatabaseName();
			$HostName = Constants::GetDatabaseHost();
			$UserId = Constants::GetDatabaseUserID();
			$Password = Constants::GetDatabasePassword();
		}
		
		$this->Handle = mysql_connect( $HostName, $UserId, $Password );
		if(!$this->Handle)
			throw new Exception("Error: Unable to connect to database." );
		
		mysql_select_db( $DataSrc, $this->Handle );
		
		$this->DataSource = $DataSrc;

	}
	
	// Kind of like a Singleton, returns the a default database connection.
	// makes sure not to make create connection object.
	static function getDefaultConnection(){
		if(self::$defaultDBconnection === NULL)
			self::$defaultDBconnection = new DbConnect();
		
		return self::$defaultDBconnection;
	}

}


