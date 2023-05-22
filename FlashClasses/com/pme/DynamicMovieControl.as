
import com.pme.Hash;
import com.pme.DownloadManager;
import mx.events.EventDispatcher;


// This is meant to work in coordination with the DownloadManager class
// It will allow you to play movies and hide them after the download/inialization part is complete.
// Only one movie is allowed to be played at a time through this control.  When another movie starts... it will hide the one before it.
// If you want to have multiple Dynamic movies playing at the same time... then create multiple instances of this class.
// ... if you want multiple movies playing at the same time... keep in mind that the download manager only contains references to movie clips.
// ... so you can safely play 2 separate SWF's simutaneouly at the same time... but not 2 of the same clip.  
// ... If you want to do this then you will need two copies of a download manager object.

class com.pme.DynamicMovieControl {


	// Keeps track of the interval ID used for downloading Dynamic SWF files.
	private var dynamicDownloadIntervalRef:Number;
	
	// Keeps track of the interval ID used inspecint the movie clip's state.
	private var iteratorIntervalRef:Number;

	// We want to keep track of the last move that we sent notification about being on the last frame.
	// This is because if we fire off an event that the movie clip hit the "last frame"... 
	// ... we don't want to keep duplicating that event if the playhead has stopped there.
	private var lastMovieNotifiedOnLastFrame:String;

	// When we are going to play a dynamic movie clip this will keep track of the fileName we are playing
	private var dynamicMovieToPlay:String;
	
	// This is a reference to the movie that currently playing.
	private var currentMoviePlaying:MovieClip;
	
	
	// Helps us track if the movie "currently playing" has stopped.
	private var lastMovieToCheckIfStillPlaying:String;
	

	// There could be some overlap between the movie we are trying to play and the one that it will be replacing.

	private var lastDynamicMovieNameInit:String;
	

	// The coordinates to start playing the movie at (as soon as it becomes visible

	private var dynamicMovieCoordinate_X:Number;
	private var dynamicMovieCoordinate_Y:Number;
	
	// Let's us know if a dynamically downloaded animation clip is currently visible on the stage.
	private var dynamicMovieActive:Boolean;
	
	private var dupRandomMovieTracker:Hash;
	
	// These are methods required by the EventDispatcher
	// Define them here so our IDE works better.
	public var dispatchEvent:Function;
        public var addEventListener:Function;
        public var removeEventListener:Function;

	
	// Keep a reference to the download manager.
	private var downloadManager:DownloadManager;
	
	
	// Constructor.
	// Pass in a reference to a Download manager object that we will be using to play the movies with.
	public function DynamicMovieControl(downloadManagerObj:DownloadManager){
	
		this.downloadManager = downloadManagerObj;
		
		this.dynamicMovieActive = false;
		
		this.lastMovieToCheckIfStillPlaying = null;
		
		this.dupRandomMovieTracker = new Hash();
		
		EventDispatcher.initialize(this);
		
		
	}


	// You must call playMovie() before calling this method.
	// It will return true or false depedending on whether the dynamic movie is on stopped (or randomly on) on the last frame at the time of this method call.
	// If the movie is still in the process if downloading/initializing then it will return false also.
	public function checkIfFinishedPlaying():Boolean {

	
		if(!this.dynamicMovieActive){
			trace("In method checkIfFinishedPlaying...No movie is currently playing yet..");
			return false;
		}
		
		
		// In case this variable has a Null value.. set it to the current movie playing.
		// This could mean that this is the very first time calling this function... or the this function has returned "True" for a previous movie clip.
		if(!this.lastMovieToCheckIfStillPlaying)
			this.lastMovieToCheckIfStillPlaying = this.lastDynamicMovieNameInit;
			

		// There are 2 ways that we know if a movie has finished playing.
		// 1) If the current movie playing as a End Frame number matching the total frames inside of the clip.
		// 2) If the movie we first checked (to see if it has finished) is no longer playing anymore.
		// The latter could happen in a critical race condition in which you don't always know what the current frame is on the movieclip.
		if(this.currentMoviePlaying._totalframes == this.currentMoviePlaying._currentframe || this.lastMovieToCheckIfStillPlaying != this.lastDynamicMovieNameInit){

			// Set this member variable to null.  That way we will acknowlege that the MovieClip currently playing was eventually finished.
			this.lastMovieToCheckIfStillPlaying = null;
			
			return true;
		}
		else{
			return false;
		}

	}
	
	
	
	// You should call playMovie() before calling this method.
	// It will return 0 if no movie is downloading AND there is no movie currently playing.
	// It will return 100 if no movie is downloading but there is currently a movie playing.
	public function getPercentageOfDownload():Number {

		if(this.dynamicDownloadIntervalRef == null && !this.dynamicMovieActive){
			trace("In method getPercentageOfDownload...No movie is currently being downloaded and no movie is playing.");
			return 0;
		}
		else if(this.dynamicDownloadIntervalRef == null && this.dynamicMovieActive){
			trace("In method getPercentageOfDownload...No movie is currently being downloaded and there IS a Moving Playing.");
			return 100;
		}


		return this.downloadManager.getPercentageOfDownload(this.dynamicMovieToPlay);
	}
	
	
	
	// Sometimes a looping Frame sequence (animation) may want to check if another Movie is has been download and intialized.
	// If so, then you know that it may be played immediately (without a pause).
	// In this case you may Stop the looping sequence of the animation (on a neutral/matching frame) and play the dynamic movie that is ready.
	public function checkIfMovieHasInitialized(swfFileToPlay:String){
	
		return this.downloadManager.checkIfDownloadInitialized(swfFileToPlay);
	}
	
	
	// Call this if we want to dynamically download and play a movie.
	// After the movie has been downloaded (and hopefully it is cached)... it will use the movieClipTargets X/Y coordinates & depth to start playing.
	// This will throw an error message if the swfFileToDownload has not been intialized within the Download Manager.
	public function playMovie(swfFileToPlay:String, loc_X:Number, loc_Y:Number):Void {
	
		if(!this.downloadManager.checkFileWasAdded(swfFileToPlay)){
			trace("The dynanmic movie that you are trying to play has not been initialized: " + swfFileToPlay);
			return;
		}
		
		// Keep looping at an interval until the movie has finished downloading.
		// Then we want to replace the movieClipTarget with what we downloaded and start playing it.
		if(this.dynamicDownloadIntervalRef != null){
			trace("You are not allowed to call the method playMovie until the previous call has finished.  New call on:" + swfFileToPlay + " intervalID: " + this.dynamicDownloadIntervalRef);
			clearInterval(this.dynamicDownloadIntervalRef);
			return;
		}
		
		
		// Record the coordinates into memory... so when it is ready to play it will know where to go.
		this.dynamicMovieCoordinate_X = loc_X;
		this.dynamicMovieCoordinate_Y = loc_Y;

		
		// Set our parameters into memory for this object so that the Callback method will have access.
		this.dynamicMovieToPlay = swfFileToPlay;

		// Since we are trying to play it now, make sure it has a high priority.
		this.downloadManager.increasePriority(swfFileToPlay);
		
		
		// If the movie is already initialized then there is no point in doing a callBack on the interval... would could start it immediatly.
		if(this.downloadManager.checkIfDownloadInitialized(this.dynamicMovieToPlay))
			this.callBackForPlayMovie();
		else
			this.dynamicDownloadIntervalRef = setInterval(this, "callBackForPlayMovie", 30); // 30 milliseconds.  :::84 milliseconds = 1 Frame at 12 fps
	}



	// If there is a dynamic movie visible on stage this will hide it.
	// Or you can call this to remove the visible movie from stage.
	public function hideMovie():Void {

		if(this.dynamicMovieActive && this.lastDynamicMovieNameInit != null){
			//trace("Hiding Movie: " + this.lastDynamicMovieNameInit);
			
			this.currentMoviePlaying.gotoAndStop(this.currentMoviePlaying._totalframes);
			this.currentMoviePlaying._visible = false;
			
			this.dynamicMovieActive = false;
			
			// After the moving is hidden... we don't want to keep a reference to it.
			this.currentMoviePlaying = null;
		}

	}
	
	
	// Works almost the same as playMovie except you pass in an array of file names instead.
	// It will play any of them at random... but it will give preference to ones that have already been downloaded an initialized.
	// If it can't find any that have been downloaded and initialized then it will play one of them at random... knowing that there will be a wait.
	// Pass in an areaDescription like "homePage", "xxxx34"... something unique to tell us where the random movies are sourced from.
	// .... we do this to keep track of the last movie selected for random playback within the batch... we try not to play the same random movie back-to-back.. (Yes, black can come up 8 times in a row on a roulette table... I learned the hard way ;(.
	public function playRandomMovie(swfFiles:Array, areaDescription:String, loc_X:Number, loc_Y:Number):Void {
	
		if(areaDescription == null || areaDescription == ""){
			trace("The parameter areaDescription must not be null in method playRandomMovie.");
			return;
		}
	
		var numberOfSwfFiles:Number = swfFiles.length;
		
		
		if(numberOfSwfFiles == 0){
			trace("The value of swfFiles can not be an empty array in method playRandomMovie.");
			return;
		}
		


		// Fill upeach slot of the randomizedOrder array... keeping in mind not to miss any number.
		var randomizedOrder:Array = new Array(numberOfSwfFiles);
		
		// Everytime we find a new random number (that we haven't found a home for in randomizedOrder yet) we record it into this array.
		var alreadyFoundSpot:Array = new Array();
		var x:Number = 0;
		var xFound:Boolean;
		
		var currentIndex:Number = 0;
		var randomNumber:Number;
	
	
		// Populate randomizedOrder array
		while(currentIndex < numberOfSwfFiles){
		
			randomNumber = Math.floor(Math.random() * numberOfSwfFiles);
			
			xFound = false;
			
			// If the random number we just generated it not in the alreadyFoundSpot array... then add it and increase the currentIndex.
			for(x = 0; x < alreadyFoundSpot.length; x++){
				if(alreadyFoundSpot[x] == randomNumber)
					xFound = true;
			}
			
			if(!xFound){
				randomizedOrder[currentIndex] = randomNumber;
				alreadyFoundSpot.push(randomNumber);
				currentIndex++;
			}
		}
		
		
		// We would like to record 2 random movies... available for playing (initialized & downloaded)
		// That will give us the capability to play the second one if we find out the last one was just played last (Yes, black can come up 5 times in a row on a roulette table).
		// This little bit of extra logic can give a "wow factor" to someone who might have seen the same animation twice in a row.
		var swfFileToPlayStr:String = "";
		var swfFileToPlayStrTwo:String = "";
		var tempSwfName:String;
		
		// Now loop through our randomizedOrder array and take the first movie that is initialized.
		for(x = 0; x < numberOfSwfFiles; x++){
		
			tempSwfName = swfFiles[randomizedOrder[x]];
		
			if(this.downloadManager.checkIfDownloadInitialized(tempSwfName)){
				if(swfFileToPlayStr == ""){
					swfFileToPlayStr = tempSwfName;
				}
				else{
					swfFileToPlayStrTwo = tempSwfName;
					break;
				}
			}
		}
		
		
		// If we were not able to find a movie out of the list that has been downloaded/intialized then just play the first one in the Randomized array.
		if(swfFileToPlayStr == ""){
		
			// Before we try to play a random movie... let's see if one of our SWF files are currently being downloading... if so, then play that because it will be quicker.
			for(x = 0; x < numberOfSwfFiles; x++){

				if(this.downloadManager.checkIfDownloadInProgress(swfFiles[x])){
					
					// So we know the next time around that we just played this one.
					this.dupRandomMovieTracker.addEntry(areaDescription, swfFiles[x]);
					this.playMovie(swfFiles[x], loc_X, loc_Y);
					
					return;
				}
			}
			
		
			// So we know the next time around that we just played this one.
			this.dupRandomMovieTracker.addEntry(areaDescription, swfFiles[randomizedOrder[0]]);
			
			// Play our first choice even though the user will have to wait for it to finish downloading.
			this.playMovie(swfFiles[randomizedOrder[0]], loc_X, loc_Y);
		}
		else{
		
			var lastMoviePlayed:String = this.dupRandomMovieTracker.valueFromKey(areaDescription).toString();
		
			if(lastMoviePlayed == swfFileToPlayStr && swfFileToPlayStrTwo != ""){
				this.dupRandomMovieTracker.addEntry(areaDescription, swfFileToPlayStrTwo);
				this.playMovie(swfFileToPlayStrTwo, loc_X, loc_Y);
			}
			else{
				this.dupRandomMovieTracker.addEntry(areaDescription, swfFileToPlayStr);
				this.playMovie(swfFileToPlayStr, loc_X, loc_Y);
			}
		}
	}
	
	
	
	// Will check to see if the movie has finished being downloaded and initialized.
	// In which case it will move the dynamic movie to the correct position on stage and begin playing it.
	// Otherwise it will continue looping with the interval.
	private function callBackForPlayMovie():Void {

		if(this.downloadManager.checkIfDownloadInitialized(this.dynamicMovieToPlay)){

			// In case another dynamic movie is active, this will hide it so that our new one can play.
			this.hideMovie();
	
			// Keep track of the current movie that has been initialized (about to start playing)
			// That way when hideMovie() is called next it will know what movie to hide.
			this.lastDynamicMovieNameInit = this.dynamicMovieToPlay;

			// This will get a reference to the Movie clip that was downloaded... that we want to start playing on stage.
			var downloadedClipRef:MovieClip = this.downloadManager.getDownloadedMovieClip(this.dynamicMovieToPlay);
			


			// Make the downloaded clip take on the X & Y coordinates that we specified. 
			downloadedClipRef._x = this.dynamicMovieCoordinate_X;
			downloadedClipRef._y = this.dynamicMovieCoordinate_Y;
			
			
			// By default the download manager hides the clips.
			downloadedClipRef._visible = true;
			
			
			// Let our object have a reference to the currently playing movie.
			this.currentMoviePlaying = downloadedClipRef;

			// Let's us know that our Dynamic animation is visible on stage.
			this.dynamicMovieActive = true;


			// Start playing the movie.
			downloadedClipRef.gotoAndPlay(1);
			
			
			// Because we are playing a new movie... reset the lastMovieNotifiedOnLastFrame... It is used for preventing duplicate events from firing.  
			this.lastMovieNotifiedOnLastFrame = null;

			//trace("Starting Playing Dynamic Movie: " + this.dynamicMovieToPlay);
			
			// Fire off an event (to any subscribed listeners)... than want to know as soon as the movie begans its playback.
			dispatchEvent({type:"movieBegan", target:this, movieName:this.dynamicMovieToPlay });
			
			// Start listening in for changes on the movie clip... this will never stop once it starts.... even if we hide the movie.
			this.startIteration();
			
			if(this.dynamicDownloadIntervalRef != null){
				clearInterval(this.dynamicDownloadIntervalRef);
				this.dynamicDownloadIntervalRef = null;
			}	
		}
		
	}
	
	
	

	// We want to listen for when the dynamic movie clip is on the last frame.
	// If so then we want to fire off an event to any subscribers letting them know it hit the end of the timeline.
	// At that point they may choose to Start playing a new Movie... or let the current one continue to loop... or just stop.
	// The event "movieLastFrame" will not be fired multiple times if the playhead stops on the last frame either.... Just one time.
	private function checkDynamicMovieAtInterval(){
	
		if(this.dynamicMovieActive){	
			if(this.lastMovieNotifiedOnLastFrame != this.dynamicMovieToPlay && this.currentMoviePlaying._currentframe == this.currentMoviePlaying._totalframes){
				
				this.lastMovieNotifiedOnLastFrame = this.dynamicMovieToPlay;
				
				dispatchEvent({type:"movieLastFrame", target:this, movieName:this.dynamicMovieToPlay });
			}
		}
	}
	
	
	
	// Don't start the iteration until we have a movie actually start playing.
	// Ideally I would like to call this only when the delegate "addEventListener" is called on this object... but I am not sure how to tie into that.
	private function startIteration():Void {
	
		// Make sure that the iterator can not start more than once.
		if(this.iteratorIntervalRef != null)
			return;

		// Delay the startup Sequence.
		this.iteratorIntervalRef = setInterval( 
			
			function(parentObj:DynamicMovieControl) {
				parentObj.checkDynamicMovieAtInterval();
			}
			, 30, this );  // Call the method checkDynamicMovieAtInterval every 30 milliseconds (or a little less than 1/2 a frame)
	}


}
