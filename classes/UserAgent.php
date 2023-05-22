<?

class UserAgent
{
    public $platform;
    public $browser;
    public $version;

    function UserAgent()
    {
          $this->platform = "Unknown";
        // Determine the platform they are on
        if (strstr($this->get_user_agent(),'Win'))
            $this->platform='Windows';
        else if (strstr($this->get_user_agent(),'Mac'))
            $this->platform='Macintosh';
        else if (strstr($this->get_user_agent(),'Linux'))
            $this->platform='Linux';
        else if (strstr($this->get_user_agent(),'Unix'))
            $this->platform='Unix';
        else
            $this->platform='Other';


	$versionRegex = "(\d{1,2}(\.\d{0,2})?)";

		$found = array();
	
        // Next, determine the browser they are using
        if ( preg_match("/Opera $versionRegex/i", $this->get_user_agent(), $found) &&
               strstr($this->get_user_agent(), "MSIE") )
        {
            // This will identify the Opera browser even when it tries to ID itself
            // as MSIE 5.0
             $this->browser = "Opera";
             $this->version = $found[1];
        }
        else if ( preg_match("/Opera $versionRegex/i", $this->get_user_agent(), $found) &&
                 strstr($this->get_user_agent(), "Mozilla") )
        {
              // Finds Opera if ID's itself as Mozilla based browser
             $this->browser = "Opera";
             $this->version = $found[1];
        }
        else if ( preg_match("/Opera\/$versionRegex/i", $this->get_user_agent(), $found) )
        {
          // Finds Opera when ID'ing itself as Opera
          $this->browser = "Opera";
          $this->version = $found[1];
        }
        else if ( preg_match("/Netscape\/$versionRegex/i", $this->get_user_agent(), $found) )
        {
              // For Netscape 7.1
            $this->browser = "Netscape";
            $this->version = $found[1];
        }
        else if ( preg_match("/Netscape[0-9]\/$versionRegex/i", $this->get_user_agent(), $found) )
        {
              // For Netscape 6.x
            $this->browser = "Netscape";
            $this->version = $found[1];
        }
        else if ( preg_match("/Mozilla\/$versionRegex \[en\]/i", $this->get_user_agent(),$found) )
        {
            // For Netscape 4.x
            $this->browser = "Netscape";
            $this->version = $found[1];
        }
        else if ( preg_match("/MSIE $versionRegex/i", $this->get_user_agent(), $found) )
        {
            // For MSIE
            $this->browser = "MSIE";
            $this->version = $found[1];
        }
        else{
          $this->browser = $this->get_user_agent();
          $this->version = "0";
        }
    }



      // Return the user agent string
      function get_user_agent()
      {
		  if(isset($_SERVER['HTTP_USER_AGENT']))
          	return $_SERVER['HTTP_USER_AGENT'];
          else 
          	return null;
      }

}
?>