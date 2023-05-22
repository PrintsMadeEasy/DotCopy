<?

class LanguageBase {


	// Pass in an integer and different varieties of exention..
	// For Example... You might pass in the parameters...   
	//		GetPluralSuffix(1, "y", "ies")  //Result "1 butterfly"
	//		GetPluralSuffix(3, "", "s")	//Result "3 books"
	//		GetPluralSuffix(0, "", "es")	//Result "0 beaches"
	static function GetPluralSuffix($IntValue, $SingularSuffix, $PluralSuffix){

		if($IntValue == 0)
			return $PluralSuffix;
		else if($IntValue == 1)
			return $SingularSuffix;
		else
			return $PluralSuffix;
	}


	// Returns a Description for the TimeStamp relative to the current time.
	// May include, Today, Tommorow, Yetsterday, or the actual date if anything else.
	static function getRelativeTimeStampDesc($timeStamp){

		$msg = "";

		if(date("n") == date("n", $timeStamp) && date("j") == date("j", $timeStamp)){
			$msg .= "Today, " . date("M jS", $timeStamp) . " at " . date("g:i a", $timeStamp) . " PST.";
		}
		else if(date("n") == date("n", $timeStamp) && (date("j") + 1) == date("j", $timeStamp)){
			$msg .= "Tomorrow, " . date("M jS", $timeStamp) . " at " . date("g:i a", $timeStamp) . " PST.";
		}
		else if(date("n") == date("n", $timeStamp) && (date("j") - 1) == date("j", $timeStamp)){
			$msg .= "Yesterday, " . date("M jS", $timeStamp) . " at " . date("g:i a", $timeStamp) . " PST.";
		}
		else{
			$msg .= date("D, M jS", $timeStamp) . " at " . date("g:i a", $timeStamp) . " PST.";
		}

		return $msg;
	}


	// Will return a String like "2 days, 2 hours" or "23 minutes", or "3 hours, 34 minutes"
	// It will Round off the minutes if it is more than 1 day difference.
	// Does not care if the time difference is in the Past or the Future
	// Pass in a second Timestamp if you want it to use that over the Current Point in Time.
	// If you want to go down to the difference in seconds (if less than 1 minute)... then pass in the 3rd parameter as true.
	static function getTimeDiffDesc($timeStamp, $secondTimeStamp = null, $detailLevel_seconds = false){

		if(!$secondTimeStamp)
			$secondTimeStamp = time();

		$SeondsInMinute = 60;
		$SeondsInHour = 60 * $SeondsInMinute;
		$SeondsInDay = 24 * $SeondsInHour;

		$timeDiff = abs($timeStamp - $secondTimeStamp);

		$totalDays = floor($timeDiff / $SeondsInDay);	
		$totalHours = floor(($timeDiff - $totalDays * $SeondsInDay) / $SeondsInHour);	
		$totalMinutes = round(($timeDiff - ($totalDays * $SeondsInDay) - ($totalHours * $SeondsInHour)) / $SeondsInMinute);



		if($totalDays > 0){

			if($totalMinutes > 30)
				$totalHours++;

			return $totalDays . " day" . LanguageBase::GetPluralSuffix($totalDays, "", "s") . ", " . $totalHours . " hour" . LanguageBase::GetPluralSuffix($totalHours, "", "s");
		}
		else if($totalHours > 0){
			return $totalHours . " hour" . LanguageBase::GetPluralSuffix($totalHours, "", "s") . ", " . $totalMinutes . " minute" . LanguageBase::GetPluralSuffix($totalMinutes, "", "s");
		}
		else{
			
			if($detailLevel_seconds && $timeDiff < 60){
				return $timeDiff . " second" . LanguageBase::GetPluralSuffix($timeDiff, "", "s");
			}
			else{
				// Always show at least mintute.  0 Minutes looks kind of funny
				if($totalMinutes == 0)
					$totalMinutes = 1;
	
				return $totalMinutes . " minute" . LanguageBase::GetPluralSuffix($totalMinutes, "", "s");
			}
		}
	}









}

?>