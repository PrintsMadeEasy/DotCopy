<?php


//Extended Template Class
class Templatex extends Template
{


	// Pass in a file system path to load the templates off of ... or a relative path.
	// If you don't specify a path then the Domain Object will try to figure out the HTML Sandbox directory based upon the domain name in the URL.
	function __construct($documentRoot = null){
	
		$this->setDirectoryRoot($documentRoot);
		
		parent::set_unknowns("keep");
	}
	
	
	// Override the parent class.
	// Find out if we are going to step down the directory stucture of relative links.
	function finish($str){
		
		$str = parent::finish($str);
		
		$stepDownDir = WebUtil::GetInput("StepDownDir", FILTER_SANITIZE_INT);
		
		if($stepDownDir > 0){
			// Replace all image/javascript/css paths with     ../ x $stepDown
			$dotDotSlashes = "";
			for($i=0; $i<$stepDownDir; $i++){
				$dotDotSlashes .= "../";
			}
			
			// This is a negative look-ahead ... (?!http) .... and ...(?!javascript) ... If the link is hard-coded we don't want to change the directory structure.
			// We do a negative look-ahead on ... (?!/) ... because a single forward slash means go to the webserver root... we can't step that down.
			// Look for anything with a path inside... which is either SRC or HREF attributes
			$str = preg_replace("@\s(src|href|action)\s*=\s*('|\")(\\./)?(?!http)(?!javascript)(?!/)(?!#)@i", (" \\1=\\2" . $dotDotSlashes), $str);
			$str = preg_replace("@(\\.location\s*=\s*)('|\")(\\./)?(?!http)@i", ("\\1\\2" . $dotDotSlashes), $str);
			
			// This is needed for flash objects
			$str = preg_replace("@<object([^>]*)\sdata\s*=\s*('|\")(\\./)?(?!http)@i", ("<object\\1 data=\\2" . $dotDotSlashes), $str);
			$str = preg_replace("@<param\s+name\s*=\s*('|\")(movie|expressinstall)('|\")\s+value\s*=\s*('|\")(\\./)?(?!http)@i", ("<param name=\\1\\2\\3 value=\\4" . $dotDotSlashes), $str);


		}
		
		return $str;
	}
	
	function setDirectoryRoot($documentRoot = null){
		
		if(!empty($documentRoot)){
			parent::set_root($documentRoot);
		}
		else{
			parent::set_root(Domain::getDomainSandboxPath());
		}
	}
	
	function setSandboxPathByDomainID($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			exit("Error in setSandboxPathByDomainID. The Domain ID does not exist.");
			
		$this->setDirectoryRoot(Domain::getDomainSandboxPath(Domain::getDomainKeyFromID($domainID)));
	}

	//Discards block if $condition == true (optional)
	//Benign to call if the block does not exist.
	function discard_block( $parent, $varname, $condition=true )
	{
        if (!parent::loadfile($parent)) {
          parent::halt("set_block: unable to load $parent.");
          return;
        }

		// Make sure that it won't fail in case discard_block is called more than once
		$str = parent::get_var($parent);
		$reg = "/<!--\s+BEGIN $varname\s+-->(.*)\s*<!--\s+END $varname\s+-->/sm";
		if(!preg_match($reg, $str))
	        return;
	
		if( $condition )
		{
			parent::set_block( $parent, $varname, "___discard___" );
			parent::set_var( "___discard___", null ); 
		}
	}
	
	//Copies block to variable names varname. Replaces block in parent with varname,
	// and then parses block in variable varname, if $condition == true
	//If $condition == false, block is discarded from parent
	function parse_block( $parent, $varname, $condition=true )
	{
		if( $condition )
		{
			parent::set_block( $parent, $varname );
			parent::parse( $varname, $varname );		
		}
		else
			$this->discard_block( $parent, $varname );	
	}
	
	//Copies value of template variable from one to another
	function copy_var( $destination, $source )
	{
		parent::set_var( $destination, parent::get_var( $source )); 
	}
	
	//Format template block selection list
	//$tempVar is name of the variable that contains the base block
	//The result is returned in $resultVar if specified else it is put in $tempVar
	function FormatSelections( $selList, $selected, $block, $parent )
	{
		parent::set_var( "_".$block, "");
		parent::set_block( $parent, $block, "_".$block );
		foreach( $selList as $select )
		{
			parent::set_var(  "Selection", $select );
			parent::set_var( "Value", $select );	//Just in case {Value} exists				
			parent::set_var(  "Selected", $select == $selected ? "SELECTED" : null  );			
			parent::parse( "_".$block, $block, true );			
		}			
		parent::set_var(  "Selection", null );
		parent::set_var(  "Selected", null );	
	}	
	
	//Format template block selection list from a 2 dim array
	//Adds value using key, section from fieldname field
	//$tempVar is name of the variable that contains the base block
	//The result is returned in $resultVar if specified else it is put in $tempVar
	function FormatSelectionsValue( $selList, $field, $selected, $block, $parent )
	{
		parent::set_var( "_".$block, "");			
		parent::set_block( $parent, $block, "_".$block );
		foreach( $selList as $value => $select )
		{
			parent::set_var(  "Selection", $select[ $field ] );
			parent::set_var(  "Value", $value );			
			parent::set_var(  "Selected", $value == $selected ? "SELECTED" : null  );			
			parent::parse( "_".$block, $block, true );			
		}			
		parent::set_var(  "Selection", null );
		parent::set_var(  "Value", null );		
		parent::set_var(  "Selected", null );	
	}	
	
	function filename($filename){

		// Find out if there is a name/value pair in the URL that specifies a different template number.
		// Normally templates have the prefix "-template.html".  If we have a Template Number in the URL then add that to the prefix.
		// We may overrite the filename with something like "ShoppingCart-template_3.html
		$templateNumber = WebUtil::GetInput("TemplateNumber", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		// Make TemplateID avaialable too from the URL.
		if(empty($templateNumber))
			$templateNumber = WebUtil::GetInput("TemplateID", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

		if(preg_match('/template\\.html$/', $filename) && preg_match("/^(\d|\w)+$/", $templateNumber)){	
			if(strlen($templateNumber) > 25)
				exit("The Template ID is too long.");
			$filename = preg_replace('/template\\.html$/', "template_" . $templateNumber . ".html", $filename);
		}
		
	    if (substr($filename, 0, 1) != "/") {
      		$filenameCheck = $this->root."/".$filename;
    	}
		
    	// On the Development server show the template error message... on the live server show a 404 error when the template can't be found.
		if (!file_exists($filenameCheck)) {
			
			if(Constants::GetDevelopmentServer()){
				exit("Template file was not found: " . $filenameCheck);
			}
			else {
				WebUtil::print404Error();
				exit();
			}
		}
		else{
			return parent::filename($filename);
		}
	}
}

?>