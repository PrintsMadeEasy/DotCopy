<?php

class Math {

	// Check if a float value equals Zero. Because of rounding issues you can't check == 0 directly.
	static function checkIfFloatIsZero($floatValue){
		
		if(intval(round($floatValue * 10000)) == 0)
			return true;
		else 
			return false;
	}
	
	static function checkIfFirstFloatParamIsGreater($floatNumber1, $floatNumber2){
		
		$intNumber1 = intval(round($floatNumber1 * 10000));
		$intNumber2 = intval(round($floatNumber2 * 10000));
		
		if($intNumber1 > $intNumber2)
			return true;
		else
			return false;
	}
	
	static function checkIfFirstFloatParamIsGreaterOrEqual($floatNumber1, $floatNumber2){
		
		$intNumber1 = intval(round($floatNumber1 * 10000));
		$intNumber2 = intval(round($floatNumber2 * 10000));
		
		if($intNumber1 >= $intNumber2)
			return true;
		else
			return false;
	}
	
	static function checkIfFirstFloatParamIsLessOrEqual($floatNumber1, $floatNumber2){
		
		$intNumber1 = intval(round($floatNumber1 * 10000));
		$intNumber2 = intval(round($floatNumber2 * 10000));
		
		if($intNumber1 <= $intNumber2)
			return true;
		else
			return false;
	}
	
	static function checkIfFirstFloatParamIsLess($floatNumber1, $floatNumber2){
		
		$intNumber1 = intval(round($floatNumber1 * 10000));
		$intNumber2 = intval(round($floatNumber2 * 10000));
		
		if($intNumber1 < $intNumber2)
			return true;
		else
			return false;
	}
	
	static function checkIfFloatNumbersAreEqual($floatNumber1, $floatNumber2){
		
		$intNumber1 = intval(round($floatNumber1 * 10000));
		$intNumber2 = intval(round($floatNumber2 * 10000));
		
		if($intNumber1 == $intNumber2)
			return true;
		else
			return false;
	}
	
	
}

?>