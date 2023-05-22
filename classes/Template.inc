<?php
/*
 * Session Management for PHP3
 *
 * (C) Copyright 1999-2000 NetUSE GmbH
 *                    Kristian Koehntopp
 *
 * $Id: Template.inc,v 1.6 2009/07/13 17:36:25 brian_dot Exp $
 *
 */

/*
 * Change log since version 7.2c
 *
 * Bug fixes to version 7.2c compiled by Richard Archer <rha@juggernaut.com.au>:
 * (credits given to first person to post a diff to phplib mailing list)
 *
 * Normalised all comments and whitespace (rha)
 * replaced "$handle" with "$varname" and "$h" with "$v" throughout (from phplib-devel)
 * added braces around all one-line if statements in: get_undefined, loadfile and halt (rha)
 * set_var was missing two sets of braces (rha)
 * added a couple of "return true" statements (rha)
 * set_unknowns had "keep" as default instead of "remove" (from phplib-devel)
 * set_file failed to check for empty strings if passed an array of filenames (phplib-devel)
 * remove @ from call to preg_replace in subst -- report errors if there are any. (NickM)
 * set_block unnecessarily required a newline in the template file (Marc Tardif)
 * pparse now calls this->finish to replace undefined vars (Layne Weathers)
 * get_var now checks for unset varnames (NickM & rha)
 * get_var when passed an array used the array key instead of the value (rha)
 * get_vars now uses a call to get_var rather than this->varvals to prevent undefined var warning (rha)
 * in finish, the replacement string referenced an unset variable (rha)
 * loadfile would try to load a file if the varval had been set to "" (rha)
 * '$n' in variable values was being stripped by subst in PHP 4.0.4+ (John Mandeville)
 * '\n' was also being stripped. Fix by replacing with &#(36|92); in set_var and unreplacing in finish (rha)
 * in get_undefined, only match non-whitespace in variable tags as in finish. (Layne Weathers & rha)
 *
 */

class Template {
  public $classname = "Template";

  /* if set, echo assignments */
  public $debug     = false;

  /* $file[varname] = "filename"; */
  public $file  = array();

  /* relative filenames are relative to this pathname */
  public $root   = "";

  /* $varkeys[key] = "key"; $varvals[key] = "value"; */
  public $varkeys = array();
  public $varvals = array();
  
  // Keeps track of what Variables allow angle brackets to be substituted.
  public $angleBracketsAllowedForVarsArr = array();

  /* "remove"  => remove undefined variables
   * "comment" => replace undefined variables with comments
   * "keep"    => keep undefined variables
   */
  public $unknowns = "remove";

  /* "yes" => halt, "report" => report error, continue, "no" => ignore error quietly */
  public $halt_on_error  = "yes";

  /* last error message is retained here */
  public $last_error     = "";


  /***************************************************************************/
  /* public: Constructor.
   * root:     template directory.
   * unknowns: how to handle unknown variables.
   */
  function Template($root = ".", $unknowns = "remove") {
    $this->set_root($root);
    $this->set_unknowns($unknowns);
  }


  /***************************************************************************/
  /* public: set_root(pathname $root)
   * root:   new template directory.
   */
  function set_root($root) {
    if (!is_dir($root)) {
      $this->halt("set_root: $root is not a directory.");
      return false;
    }

    $this->root = $root;
    return true;
  }


  /***************************************************************************/
  /* public: set_unknowns(enum $unknowns)
   * unknowns: "remove", "comment", "keep"
   */
  function set_unknowns($unknowns = "remove") {
    $this->unknowns = $unknowns;
  }


  /***************************************************************************/
  /* public: set_file(array $filelist)
   * filelist: array of varname, filename pairs.
   *
   * public: set_file(string $varname, string $filename)
   * varname: varname for a filename,
   * filename: name of template file
   */
  function set_file($varname, $filename = "") {
    if (!is_array($varname)) {
      if ($filename == "") {
        $this->halt("set_file: For varname $varname filename is empty.");
        return false;
      }
      $this->file[$varname] = $this->filename($filename);
    } else {
      reset($varname);
      while(list($v, $f) = each($varname)) {
        if ($f == "") {
          $this->halt("set_file: For varname $v filename is empty.");
          return false;
        }
        $this->file[$v] = $this->filename($f);
      }
    }
    return true;
  }


  /***************************************************************************/
  /* public: set_block(string $parent, string $varname, string $name = "")
   * extract the template $varname from $parent,
   * place variable {$name} instead.
   */
  function set_block($parent, $varname, $name = "") {
    if (!$this->loadfile($parent)) {
      $this->halt("set_block: unable to load $parent.");
      return false;
    }
    if ($name == "") {
      $name = $varname;
    }

    $str = $this->get_var($parent);
    $reg = "/<!--\s+BEGIN $varname\s+-->(.*)\s*<!--\s+END $varname\s+-->/sm";
    $m = array();
    preg_match_all($reg, $str, $m);
    $str = preg_replace($reg, "{" . "$name}", $str);
    
    if(!isset($m[1][0])){
    	print "Template Error Setting: $varname";
    	exit;
    }
    
    // Blocks should allow HTML inside with brackets.
    $this->allowVariableToContainBrackets($varname);
    $this->allowVariableToContainBrackets($name);
    
    $this->set_var($varname, $m[1][0]);
    $this->set_var($parent, $str);

    return true;
  }


  /***************************************************************************/
  /* public: set_var(array $values)
   * values: array of variable name, value pairs.
   *
   * public: set_var(string $varname, string $value)
   * varname: name of a variable that is to be defined
   * value:   value of that variable
   */
  function set_var($varname, $value = "") {
  	
    if (!is_array($varname)) {
      if (!empty($varname)) {
        if ($this->debug) print "scalar: set *$varname* to *$value*<br>\n";
        $value = preg_replace(array('/\$([0-9])/', '/\\\\([0-9])/'), array('&#36;\1', '&#92;\1'), $value);
        $this->varkeys[$varname] = "/".$this->varname($varname)."/";
        $this->varvals[$varname] = $value;
      }
    } else {
      reset($varname);
      while(list($k, $v) = each($varname)) {
        if (!empty($k)) {
          if ($this->debug) print "array: set *$k* to *$v*<br>\n";
          $v = preg_replace(array('/\$([0-9])/', '/\\\\([0-9])/'), array('&#36;\1', '&#92;\1'), $v);
          $this->varkeys[$k] = "/".$this->varname($k)."/";
          $this->varvals[$k] = $v;
        }
      }
    }
  }


  /***************************************************************************/
  /* public: subst(string $varname)
   * varname: varname of template where variables are to be substituted.
   */
  function subst($varname) {
    if (!$this->loadfile($varname)) {
      $this->halt("subst: unable to load $varname.");
      return false;
    }

    $str = $this->get_var($varname);
    $str = preg_replace($this->varkeys, $this->varvals, $str);
    return $str;
  }


  /***************************************************************************/
  /* public: psubst(string $varname)
   * varname: varname of template where variables are to be substituted.
   */
  function psubst($varname) {
    print $this->subst($varname);

    return false;
  }


  /***************************************************************************/
  /* public: parse(string $target, string $varname, boolean append)
   * public: parse(string $target, array  $varname, boolean append)
   * target: varname of variable to generate
   * varname: varname of template to substitute
   * append: append to target varname
   */
  function parse($target, $varname, $append = false) {
    if (!is_array($varname)) {
      $str = $this->subst($varname);
      if ($append) {
        $this->set_var($target, $this->get_var($target) . $str);
      } else {
        $this->set_var($target, $str);
      }
    } else {
      foreach($varname as $v) {
        $str = $this->subst($v);
        $this->set_var($target, $str);
      }
    }

    return $str;
  }


  /***************************************************************************/
  function pparse($target, $varname, $append = false) {
  	
  	$this->ensureCrossSiteScriptingSecurity();
  	
    print $this->finish($this->parse($target, $varname, $append));
    return false;
  }
  
  // Be sure if you allow angle brackets that you have escaped output with htmlspecialcharacters to avoid XSS cross scripting attacks.
  function allowVariableToContainBrackets($variableName){
  	$this->angleBracketsAllowedForVarsArr[$variableName] = true;
  }
  
  function ensureCrossSiteScriptingSecurity(){
  	
	$allVars = $this->get_vars();
	
	foreach($allVars as $thisKey => $thisValue){
		
		// Find out if we have allowed Angle brackets to come through
		if(array_key_exists($thisKey, $this->angleBracketsAllowedForVarsArr) && $this->angleBracketsAllowedForVarsArr[$thisKey])
			continue;
			
		if($thisKey == "origPage")
			continue;
		
	  	// For Security, make sure that users don't try to pass data through the URL for cross-site scripting
	  	// If we had a page that took parameter and blindly substituted them (with no htmlspecialchars)...
	  	// ... then a user could use our website to inject redirects and malcious scripts.
	  	if(preg_match("/</", $thisValue) || preg_match("/>/", $thisValue)){
	  		$message = "The Template System can not replace angle brackets without an override. Var: { $thisKey } Script: " . $_SERVER['PHP_SELF'];
	  		WebUtil::WebmasterError($message . "\nValue: " . substr($thisValue, 0, 100));
	  		throw new Exception($message);
	  	}


	}
  }


  /***************************************************************************/
  /* public: get_vars()
   * return all variables as an array (mostly for debugging)
   */
  function get_vars() {
  	$result = array();
    foreach(array_keys($this->varkeys) as $k) {
      $result[$k] = $this->get_var($k);
    }
    return $result;
  }


  /***************************************************************************/
  /* public: get_var(string varname)
   * varname: name of variable.
   *
   * public: get_var(array varname)
   * varname: array of variable names
   */
  function get_var($varname) {
    if (!is_array($varname)) {
      if (isset($this->varvals[$varname])) {
        $str = $this->varvals[$varname];
      } else {
        $str = "";
      }
      return $str;
    } else {
      foreach($varname as $v) {
        if (isset($this->varvals[$v])) {
          $str = $this->varvals[$v];
        } else {
          $str = "";
        }
        $result[$v] = $str;
      }
      return $result;
    }
  }


  /***************************************************************************/
  /* public: get_undefined($varname)
   * varname: varname of a template.
   */
  function get_undefined($varname) {
    if (!$this->loadfile($varname)) {
      $this->halt("get_undefined: unable to load $varname.");
      return false;
    }

    $m = array();
    preg_match_all("/{([^ \t\r\n}]+)}/", $this->get_var($varname), $m);
    $m = $m[1];
    if (!is_array($m)) {
      return false;
    }

    reset($m);
    foreach($m as $v) {
      if (!isset($this->varkeys[$v])) {
        $result[$v] = $v;
      }
    }

    if (count($result)) {
      return $result;
    } else {
      return false;
    }
  }


  /***************************************************************************/
  /* public: finish(string $str)
   * str: string to finish.
   */
  function finish($str) {
    switch ($this->unknowns) {
      case "keep":
      break;

      case "remove":
        $str = preg_replace('/{[^ \t\r\n}]+}/', "", $str);
      break;

      case "comment":
        $str = preg_replace('/{([^ \t\r\n}]+)}/', "<!-- Template variable \\1 undefined -->", $str);
      break;
    }

    $str = preg_replace(array('/&#36;([0-9])/', '/&#92;([0-9])/'), array('$\1', '\\\1'), $str);
    return $str;
  }


  /***************************************************************************/
  /* public: p(string $varname)
   * varname: name of variable to print.
   */
  function p($varname) {
    print $this->finish($this->get_var($varname));
  }


  /***************************************************************************/
  function get($varname) {
    return $this->finish($this->get_var($varname));
  }


  /***************************************************************************/
  /* private: filename($filename)
   * filename: name to be completed.
   */
  function filename($filename) {
    if (substr($filename, 0, 1) != "/") {
      $filename = $this->root."/".$filename;
    }

    if (!file_exists($filename)) {
      $this->halt("filename: file $filename does not exist.");
    }

    return $filename;
  }


  /***************************************************************************/
  /* private: varname($varname)
   * varname: name of a replacement variable to be protected.
   */
  function varname($varname) {
    return preg_quote("{".$varname."}");
  }


  /***************************************************************************/
  /* private: loadfile(string $varname)
   * varname:  load file defined by varname, if it is not loaded yet.
   */
  function loadfile($varname) {
    if (!isset($this->file[$varname])) {
      // $varname does not reference a file so return
      return true;
    }

    if (isset($this->varvals[$varname])) {
      // will only be unset if varname was created with set_file and has never been loaded
      // $varname has already been loaded so return
      return true;
    }
    $filename = $this->file[$varname];

    /* use @file here to avoid leaking filesystem information if there is an error */
    $str = implode("", @file($filename));
    if (empty($str)) {
      $this->halt("loadfile: While loading $varname, $filename does not exist or is empty.");
      return false;
    }
    
    // Added by Brian:  Subtitutes Varaibles inside of the Templates, similar to Server Side Includes.
    $str = ServerSideIncludes::processServerSideIncludes($str);

    $this->set_var($varname, $str);

    return true;
  }


  /***************************************************************************/
  /* public: halt(string $msg)
   * msg:    error message to show.
   */
  function halt($msg) {
    $this->last_error = $msg;

    if ($this->halt_on_error != "no") {
      $this->haltmsg($msg);
    }

    if ($this->halt_on_error == "yes") {
      die("<b>Halted.</b>");
    }

    return false;
  }


  /***************************************************************************/
  /* public, override: haltmsg($msg)
   * msg: error message to show.
   */
  function haltmsg($msg) {
    printf("<b>Template Error:</b> %s<br>\n", $msg);
  }

}
?>
