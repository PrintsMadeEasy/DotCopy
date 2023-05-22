<?php

//------------------------------------------------------------------------
// ABC Excel Parser Pro (Debug class)
//
// Version: 4.0
// PHP compatibility: 4.3.x
// Copyright (c) 2002 Zakkis Technology, Inc.
// All rights reserved.
//
// This script parses a binary Excel file and store all data in an array.
// For more information see README.TXT file included in this distribution.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
// "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
// LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
// FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
// REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
// INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
// (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
// SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
// STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
// ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
// OF THE POSSIBILITY OF SUCH DAMAGE.
//
//------------------------------------------------------------------------

define('ABC_CRITICAL',      0);
define('ABC_ERROR',         1);
define('ABC_ALERT',         2);
define('ABC_WARNING',       3);
define('ABC_NOTICE',        4);
define('ABC_INFO',          5);
define('ABC_DEBUG',         6);
define('ABC_TRACE',         7);
define('ABC_VAR_DUMP',      8);

define('ABC_NO_LOG',      -1);

$php_version = split( "\.", phpversion() );

if( $php_version[0] == 4 && $php_version[1] <= 1 ) {
    if( !function_exists('var_export') ) {
        function var_export( $exp, $ret ) {
				ob_start();
				var_dump( $exp );
				$result = ob_get_contents();
				ob_end_clean();
				return $result;
		}
	}
}

function print_bt()
{
	print "<code>\n";
	$cs = debug_backtrace();
	for( $i = 1; $i < count($cs) ; $i++ )
	{
		$item = $cs[ $i ];
		
		for( $j = 0; $j < count($item['args']); $j++ )
			if( is_string($item['args'][$j]) )
				$item['args'][$j] = "\"" . $item['args'][$j] . "\"";
		$args = join(",", $item['args'] );
			
		if( isset( $item['class'] ) )
			$str = sprintf("%s(%d): %s%s%s(%s)",
				$item['file'],
				$item['line'],
				$item['class'],
				$item['type'],
				$item['function'],
				$args );
		else
			$str = sprintf("%s(%d): %s(%s)",
				$item['file'],
				$item['line'],
				$item['function'],
				$args );
		echo $str . "<br>\n";
	}
	print "</code>\n";
}

function _die( $str )
{
	print "Script died with reason: $str<br>\n";
	print_bt();
	exit;
}

class DebugOut
{

	public $priorities = array(ABC_CRITICAL    => 'critical',
	                        ABC_ERROR       => 'error',
	                        ABC_ALERT       => 'alert',
	                        ABC_WARNING     => 'warning',
	                        ABC_NOTICE      => 'notice',
	                        ABC_INFO        => 'info',
	                        ABC_DEBUG       => 'debug',
	                        ABC_TRACE       => 'trace',
	                        ABC_VAR_DUMP        => 'dump'
	                        );
	private $_ready = false;
	
	private $_currentPriority = ABC_DEBUG;
	
	private $_consumers = array();
	
	private  $_filename;
	private  $_fp;
	private  $_logger_name;


 function DebugOut($name, $logger_name, $level ){
     $this->_filename = $name;
     $this->_currentPriority = $level;
     $this->_logger_name = $logger_name;
     if ($level > ABC_NO_LOG){
        $this->_openfile();
     }

     /*Destructor Registering*/
     register_shutdown_function(array($this,"close"));
 }



 function log($message, $priority = ABC_INFO) {
        // Abort early if the priority is above the maximum logging level.
        if ($priority > $this->_currentPriority) {
            return false;
        }
        // Add to loglines array
        return $this->_writeLine($message, $priority, strftime('%b %d %H:%M:%S'));
 }

 function dump($variable,$name) {
       $priority = ABC_VAR_DUMP;
       if ($priority > $this->_currentPriority ) {
            return false;
       }
       $time = strftime('%b %d %H:%M:%S');
       $message = var_export($variable,true);
       return fwrite($this->_fp,
                     sprintf("%s %s [%s] variable %s = %s \r\n",
                             $time,
                             $this->_logger_name,
                             $this->priorities[$priority],
                             $name,
                             $message)
                             );
 }

 function info($message) {
        return $this->log($message, ABC_INFO);
 }

 function debug($message) {
        return $this->log($message, ABC_DEBUG);
 }

 function notice($message) {
        return $this->log($message, ABC_NOTICE);
 }

 function warning($message) {
        return $this->log($message, ABC_WARNING);
 }

 function trace($message) {
        return $this->log($message, ABC_TRACE);
 }

 function error($message) {
        return $this->log($message, ABC_ERROR);
 }



 /**
  * Writes a line to the logfile
  *
  * @param  string $line      The line to write
  * @param  integer $priority The priority of this line/msg
  * @return integer           Number of bytes written or -1 on error
  * @access private
  */
 function _writeLine($message, $priority, $time) {
    if( fwrite($this->_fp, sprintf("%s %s [%s] %s\r\n", $time, $this->_logger_name, $this->priorities[$priority], $message)) ) {
        return fflush($this->_fp);
    } else {
        return false;
    }
 }

 function _openfile() {
    if (($this->_fp = @fopen($this->_filename, 'a')) == false) {
        return false;
    }
        return true;
 }

 function close(){
    if($this->_currentPriority != ABC_NO_LOG){
        $this->info("Logger stoped");
        return fclose($this->_fp);
    }
    return false;
 }

 /*
  * Managerial Functions.
  *
  */

 function Factory($name, $logger_name, $level) {
    $instance = new DebugOut($name, $logger_name, $level);
    return $instance;
 }


 function &getWriterSingleton($name, $logger_name, $level = ABC_DEBUG){

      static $instances;
      if (!isset($instances)){
        $instances = array();
      }
      $signature = serialize(array($name, $level));

      if (!isset($instances[$signature])) {
            $instances[$signature] = DebugOut::Factory($name, $logger_name, $level);
      }
      
      return $instances[$signature];
 }


 function attach(&$logObserver) {
    if (!is_object($logObserver)) {
        return false;
    }

    $logObserver->_listenerID = uniqid(rand());

    $this->_listeners[$logObserver->_listenerID] = &$logObserver;
    return true;
 }

}

?>