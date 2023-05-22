
// Requires Flash Version 7
// This is meant to help out with downloading SWF files from the server.
// Some animated SWF files involove lots of graphics and the file sizes can be big.
// We can use the Download Manager to download components ahead of time so that they are cached on the User's Machine.
// For example... when a user is busy working with the editing tool then we could download stuff that will show up in their shopping cart.
// Or sometimes within an application we can download Animations for future Application States.
// With high quality 3D animations we can wait for a characters "random" actions to continue only after we have downloaded the next clip. 
class com.pme.DownloadManager {


	// Holds all of the names of the Files to be downloaded.
	private var filesArr:Array;
	
	// Holds a completed list of files that were downloaded
	private var filesAlreadyDownloadedArr:Array;
	
	// Holds a completed list of files that were downloaded (And Initialized)
	private var filesInitializedArr:Array;
	
	// When a movie clip is downloaded it is stored in this array. 
	// The indexes are parallel to filesArr
	public var downloadedMovieClipsArr:Array;
	
	// Holds the Path/Name to the file that is currently being downloaded.
	private var currentFileBeingDownloaded:String;
	
	// The Index (corresponding to the this.filesArr) that is currenty being downloaded.
	private var currentIndexDownloadingPlusOne:Number;
	
	// Holds the percentage of completion of the current file.
	private var fileDownloadPercentage:Number;
	
	// Holds the size (in bytes) of the file being downloaded
	private var fileDownloadSize:Number;
	
	// Holds the Number of bytes that have been downloaded for the file
	private var fileBytesDownloaded:Number;
	
	// This is a lock we set... while a method is in the process of changing file pointers etc.
	// Some parts of this class will be executed Asyncronously.  This variable isn't a perfect cure but it will limit our chances.
	// Hopefully in AS3 Macromedia will add a Syncronys class to lock certain code executions.
	private var fileChangeLock:Boolean;
	
	// Let's us know if they download manager has started fetching files.
	private var downloadsHaveStarted:Boolean;
	
	// Every time we try to download a file but get an error we will add the filename to this array.
	private var filesWithDownloadErrorsArr:Array;

	// Tells us whether we are just using this to cach SWF files ahead of time (or if we will make use if the downloaded clips)
	private var discardMovieClipAfterDownload:Boolean;
	
	// Holds a Reference to the Interval (used for the Start-up Delay)
	private var delayStartupIntervalRef:Number;
	
	// Holds a Reference to the Interval (used for delaying the next download in the queue)
	private var nextDownloadIntervalRef:Number;
	
	// Increments everytime another movie clip has been downloaded an initialized.
	private var numberOfDownloadsInitialized:Number;

	// this is the Object that controls the feedback from our downloads
	private var mcLoaderObj:MovieClipLoader;
	
	// This will be the parent of the downloaded clips.
	private var parentMovieClip:MovieClip;

	// Output trace events or not.
	private var debug:Boolean;



	
	// The constructor
	// Pass in a reference to a MovieClip where the download clips should be stored (The Parent).
	// If you want to discard the downloaded clips (for caching only)... just pass in "_root".
	// The reason this is necessary is because you can can't reassign the parent of a Movie Clip after it has been downloaded.  
	// There are no tricks with "duplicateMovieClip" or "attachMoveClip" which seem possible.  It needs to have its linkage set in the Library already.  
	// So download them into the corect parent (to start with), then you can swap depths and other things..
	function DownloadManager (downloadObjectsTo:MovieClip){
	
		this.debug = false;
		this.filesArr = new Array();
		this.filesAlreadyDownloadedArr = new Array();
		this.filesWithDownloadErrorsArr = new Array();
		this.filesInitializedArr = new Array();
		this.downloadedMovieClipsArr = new Array();
		this.downloadsHaveStarted = false;
		this.discardMovieClipAfterDownload = true;
		this.fileChangeLock = false;
		this.parentMovieClip = downloadObjectsTo;
		
	}
	

	
	// Indicates if we want to keep the movie clip after downloading it...(so we can fetch it through a method)
	// If we are just using this as a pre-loader ahead of time (in a different application)... then we should discard to free memory and CPU
	public function discardAfterDownload(flag:Boolean):Void {
		this.discardMovieClipAfterDownload = flag;
	}

	
	// Pass in a path to file on server.
	// Can be relative or may include include the transfer protocal... like...  http://www.server.com/flash/download.swf
	// The first file passed into this method will have the highest priority... and so on.
	public function addFile(pathToFileOnServer:String):Void {		
	
		if(this.downloadsHaveStarted){
			trace("You can't add more files after the downloads have started");
			return;
		}
	
		this.filesArr.push(pathToFileOnServer);
		
		var nextHighestDepthForParent:Number = this.parentMovieClip.getNextHighestDepth();
		
		// Create an empty movie Clip which is parrallel to our File Names Arr
		// That way if a file download does not succeed we can be certain that our function will return the matching Object type at least
		var nextHighestDepthForParent:Number = this.parentMovieClip.getNextHighestDepth();
		this.parentMovieClip.createEmptyMovieClip(("emptyMC" + nextHighestDepthForParent), nextHighestDepthForParent);
		this.downloadedMovieClipsArr.push(this.parentMovieClip[("emptyMC" + nextHighestDepthForParent)]);


		

			
	
	}
	
	// Call this method to begin the downloads
	// Pass in a Number of seconds to delay the Download Queue.
	// You may not want to start the pre-loader right away incase HTML images and other stuff are still being downloaded.
	// Does not hurt to call this method many times... will only start the download process once.
	public function startDownloads(delayInSeconds:Number):Void {

		// If there is no delay... then start the downloads immediately
		if(delayInSeconds <= 0){
			this.startDownloadsNow();
			return;
		}


		// Delay the startup Sequence.
		this.delayStartupIntervalRef = setInterval( 
			
			function(parentObj:DownloadManager) {

				if(this.debug)
					trace("Finished waiting.  About to start the downloads now.");

				// Start the download immediately
				parentObj.startDownloadsNow();

				clearInterval(parentObj.delayStartupIntervalRef);

			}
			, (delayInSeconds * 1000), this );  // Convert delayInSeconds to Milliseconds



	}
	
	// Works almost the same as the method startDownloads... but without the delay
	// Does not hurt to call this method many times... will only start the download process once.
	private function startDownloadsNow():Void {
	
		// Prevent the downloads from starting more than once.
		if(this.downloadsHaveStarted)
			return;
	
	
		this.downloadsHaveStarted = true;
		
		this.currentIndexDownloadingPlusOne = 0;
		
		this.numberOfDownloadsInitialized = 0;
		
		
		// Create a Listener Object
		this.mcLoaderObj = new MovieClipLoader();
		
		// create an object used for capturing the events from the Loader.
		var myListener:Object = new Object();
		
		myListener.callerObject = this;
		
		myListener.onLoadStart = function(target_mc:MovieClip) {
			if(this.debug)
				trace("Loading has begun on movie clip = "+target_mc);
		   
		   // Make sure that it is invisible to begin with.
		   target_mc._visible = false;
		};
		myListener.onLoadProgress = function(target_mc:MovieClip, loadedBytes:Number, totalBytes:Number) {

		   //trace(loadedBytes+" = bytes loaded at progress callback On MovieClip:" + target_mc);
		   //trace(totalBytes+" = bytes total at progress callback On MovieClip:" + target_mc);
		   
		   this.callerObject.fileDownloadSize = totalBytes;
		   this.callerObject.fileBytesDownloaded = loadedBytes;
		   this.callerObject.fileDownloadPercentage = Math.round(loadedBytes / totalBytes * 100);
		   
		};
		myListener.onLoadComplete = function(target_mc:MovieClip) {
		
			if(this.debug)
				trace("Finished Downloadin movie clip: "+target_mc + " with a level of " + target_mc._level);	   
		   
			// Within the movie clip we want to store a variable that says where this file was downloaded from.
			// That way after it has been intialized "onLoadInit" (which is asycronys) we will be able to tell where it came from.
			target_mc.pathToSourceOnServer = this.callerObject.currentFileBeingDownloaded;

		   // Some debug info that could help us determine when a movie clip has been destroyed.
		   target_mc.onUnload = function () {
			   if(this.debug)
		       	trace ("+++ onUnload called for the movieclip: " + this.callerObject.currentFileBeingDownloaded);
		   }

	   
		   // If we have chosen to keep the Movie clip after loading, then store it into the array of Completed clips
		   if(!this.callerObject.discardMovieClipAfterDownload){
		   	var arrayIndexOfFile:Number = this.callerObject.getArrayIndexOfFilename(this.callerObject.currentFileBeingDownloaded);
		   	this.callerObject.downloadedMovieClipsArr[arrayIndexOfFile] = target_mc;
		   			   }
		   else{
		   	target_mc.removeMovieClip();
		   }
		   
		   this.callerObject.filesAlreadyDownloadedArr.push(this.callerObject.currentFileBeingDownloaded);
		   
		   
		   // On to the next file.  The method will stop when there is no more.
		   this.callerObject.downloadNextFile();
		   	
		};
		myListener.onLoadInit = function(target_mc:MovieClip) {
			if(this.debug)
		   		trace("Movie clip = "+target_mc+" is now initialized");
	   
		   // you can now do any setup required, for example:
		   //target_mc._width = 100;
		   //target_mc._height = 100;
		   
		   // We want to make sure the movie clip is stopped.
		   // It could be an animation which would eat up the CPU (even if not visible).
		   target_mc.stop();
		   target_mc._visible = false;
		   
		   this.callerObject.numberOfDownloadsInitialized++;	   
		   
		   this.callerObject.filesInitializedArr.push(target_mc.pathToSourceOnServer);
		   	   
		};
		myListener.onLoadError = function(target_mc:MovieClip, errorCode:String) {

		   trace("ERROR CODE = "+errorCode);
		   trace("Failed on movie clip = "+target_mc);
		   
		   this.callerObject.filesWithDownloadErrorsArr.push(this.callerObject.currentFileBeingDownloaded);
		   
		   trace("length of Errors:" + this.callerObject.filesWithDownloadErrorsArr.length);
		   trace("current File:" + this.callerObject.currentFileBeingDownloaded);
		   
		   // On to the next file.  The method will stop when there is no more.
		   // Just because this file didn't download OK doesn't mean that we shouldn't try others.
		   this.callerObject.downloadNextFile();
		   
		};
		
		// Put the Listner (for events) into the Movieclip Loader Object.
		this.mcLoaderObj.addListener(myListener);
		
		// this will kick off the download queues because this method is reccurive.
		this.downloadNextFile();
		

	}
	
	// Used to start download the next file.
	// When there are no more files to download it stops.
	private function downloadNextFile():Void {


		// Delay the startup Sequence.
		this.nextDownloadIntervalRef = setInterval( 
			
			function(parentObj:DownloadManager) {

				if(this.debug)
					trace("Finished waiting.  About to download the NextFile in the queue..");

				// download the next file.
				parentObj.downloadNextFileInQueue();

				clearInterval(parentObj.nextDownloadIntervalRef);

			}
			, (300), this );  // Wait a third of a second in between Downloads.  This is to keep the computer from freezing up momentarily (if the SWF's are cached on the users's computer)



	}


	
	
	// Works the same as downloadNextFile() ... but without the Delay.
	private function downloadNextFileInQueue():Void {
	
		this.fileChangeLock = true;
	
		this.currentIndexDownloadingPlusOne++;
	
		if(this.currentIndexDownloadingPlusOne > this.filesArr.length){
			this.fileChangeLock = false;
			return;
		}
			
		// Assign the name of the file that we will be downloading next.
		this.currentFileBeingDownloaded = this.filesArr[(this.currentIndexDownloadingPlusOne - 1)];
		
		if(this.debug)
			trace("\n+++++ Next File To Download: \n" + this.currentFileBeingDownloaded + "\n");		

		
		// Create a dummy clip to load the new file into.
		var nameOfNextMovieClip:String = "downloadClip" + this.currentIndexDownloadingPlusOne.toString();
		this.parentMovieClip.createEmptyMovieClip(nameOfNextMovieClip, this.parentMovieClip.getNextHighestDepth());
		
		// Just make sure that it will not be visible.
		this.parentMovieClip[nameOfNextMovieClip]._x = 0;
		
		// Starts the download process.  When the "onLoadComplete" event occurs it will call this method downloadNextFile to start on the next file.
		this.mcLoaderObj.loadClip(this.currentFileBeingDownloaded, this.parentMovieClip[nameOfNextMovieClip]);
		
		this.fileChangeLock = false;
	
	}
	
	
	// This is useful if we want to start playing a group of random animations.
	// But maybe we want to wait until at least X number have been downloaded so that there are not repetitive loops.
	public function getNumberOfDownloadsInitialized():Number {
	
		if(this.numberOfDownloadsInitialized)
			return this.numberOfDownloadsInitialized;
		else
			return 0;
	}
	
	
	
	// return NULL if no files have started downloading... or if all files have finished.
	// Otherwise returns the name of the file being downloaded.
	public function getNameOfFileBeingDownloaded():String {
		
		if(this.checkIfAllDownloadsHaveFinished() || !this.downloadsHaveStarted)
			return null;
			
		return this.currentFileBeingDownloaded;
	
	}
	
	
	// *** IMPORTANT ***  You may be more interested in the method "checkIfDownloadInitialized".  This is when you are able to control movie clip behavior (like make it visible).
	// Pass in the file and and it will return True or false depending on whether it has finished downloading or not
	// If you mess up and pass in a different file name to this method (that was not requested)... it will not show an error.
	public function checkIfDownloadComplete(pathToFileOnServer:String):Boolean {


		for(var i:Number = 0; i < this.filesAlreadyDownloadedArr.length; i++){
		
			if(pathToFileOnServer == this.filesAlreadyDownloadedArr[i])
				return true;
		}
		
		return false;
	
	}
	
	
	// Pass in the file and and it will return True or false depending on whether it has finished initializing or not.
	// If you mess up and pass in a different file name to this method (that was not requested)... it will not show an error.
	public function checkIfDownloadInitialized(pathToFileOnServer:String):Boolean {


		if(this.discardMovieClipAfterDownload){
			trace("^^^^^^^^ You can't check if the download has initialized yet because you have chosen to discard it after downloading.  This Download Manager object is currently being used for browser caching only.");
			return false;
		}

		for(var i:Number = 0; i < this.filesInitializedArr.length; i++){
		
			if(pathToFileOnServer == this.filesInitializedArr[i])
				return true;
		}
		
		return false;
	
	}
	
	
	
	
	
	
	// If we attempted to download the file but had an error... then this method will return true for the given filename.
	// If you mess up and pass in a different file name to this method (that was not requested)... it will not show an error.
	public function checkIfFileDownloadHadError(pathToFileOnServer:String):Boolean {
	
	
		for(var i:Number = 0; i < this.filesWithDownloadErrorsArr.length; i++){
		
			if(pathToFileOnServer == this.filesWithDownloadErrorsArr[i])
				return true;
		}
		
		return false;
	
	}
	
	
	// Will let you know if the file was added to the queue, regardless of its status.
	public function checkFileWasAdded(pathToFileOnServer:String):Boolean {
	
		for(var i:Number = 0; i < this.filesArr.length; i++){
		
			if(pathToFileOnServer == this.filesArr[i])
				return true;
		}
		
		return false;
	}	
	
	// Returns true only if the given file is currently beeing downloaded
	public function checkIfDownloadInProgress(pathToFileOnServer:String):Boolean {
	
		if(pathToFileOnServer == this.currentFileBeingDownloaded)
			return true;
		else
			return false;
	}
	
	
	// It is fairly harmless to call this method.  
	// It will not do anything if the file has already been downloaded (or is currently downloading)
	// If the main application is waiting upon a SWF to download before proceeding you should call this method to make sure that it is the next in line.
	// It will basically swap whatever is next in line with the specified file (to increase the priority).  
	// It will not interupt the current download in progress.
	// This Method is a good reason for not making a single Large animation Too large.   Break them into small chunks that can be looped across.
	public function increasePriority(pathToFileOnServer:String):Void {

		// this.fileChangeLock is not perfect... but it may help in some critical race conditions.
		// Basically if we ever attempt to increase priority in the middle of Critical code change it will abort the priority request.
	
		if(this.checkIfDownloadComplete(pathToFileOnServer) || this.fileChangeLock)
			return;
		
		if(this.checkIfFileDownloadHadError(pathToFileOnServer) || this.fileChangeLock)
			return;

		// If the next Index to be download is the total size of our array... then our file must be next.
		if((this.currentIndexDownloadingPlusOne >= this.filesArr.length) || this.fileChangeLock)
			return;
		
		// Get the index of the Filename we are trying to increase the priority on.
		var indexOfPriorityFile:Number = this.getArrayIndexOfFilename(pathToFileOnServer);
		
		// In case the calling program made a mistake and the filename doesn't really exist.
		if(indexOfPriorityFile < 0 || this.fileChangeLock)
			return;
		
		// If the next index to download matches our Priority file, then no need to change.
		// Or if it equals it... then we are currently downloading it.
		if((indexOfPriorityFile <= this.currentIndexDownloadingPlusOne) || this.fileChangeLock)
			return;
		
		// Otherwise Swap out the filenames so the "priority file" comes next.
		this.filesArr[indexOfPriorityFile] = this.filesArr[currentIndexDownloadingPlusOne];
		this.filesArr[currentIndexDownloadingPlusOne] = pathToFileOnServer;
		
	
	}
	
	
	// Returns a Number between Zero and 100 based upon the completion of download.
	// It will be 0 if the download hasn't started... and 100 if it is finished.
	public function getPercentageOfDownload(pathToFileOnServer:String):Number {
	
	
		if(this.checkIfDownloadComplete(pathToFileOnServer))
			return 100;
		else if(!this.checkIfDownloadInProgress(pathToFileOnServer))
			return 0;
		else
			return this.fileDownloadPercentage;
	
	}
	
	
	
	// Returns true when all of the files that were requested for download have finished.
	public function checkIfAllDownloadsHaveFinished():Boolean {
	
		// when the Number of successful downloads (plus the Number of errors) equals the total Number of files that we tried to download... then we are done.
		if(this.filesArr.length = (this.filesAlreadyDownloadedArr.length + this.filesWithDownloadErrorsArr.length))
			return true;
		else
			return false;
	}
	
	

	
	// Returns 0 if all files have been downloaded... or if nothing has started yet.
	// Otherwise returns the Number of Bytes that have been downloaded for the current file.
	public function progressBytesOfFileBeingDownloaded():Number {
	
		if(this.checkIfAllDownloadsHaveFinished() || !this.downloadsHaveStarted)
			return 0;
			
		return this.fileBytesDownloaded;
	}
	
	
	// Returns 0 if all files have been downloaded... or if nothing has started yet.
	// Otherwise returns the total Number of bytes for the file being downloaded.
	public function totalBytesOfFileBeingDownloaded():Number {
	
		if(this.checkIfAllDownloadsHaveFinished() || !this.downloadsHaveStarted)
			return 0;
			
		return this.fileDownloadSize;
	}
	
	
	// Returns the movie clip that was downloaded.
	// If it was not downloaded yet... or the movie clip was discarded after download
	// Then this method will return an empty movie clip.
	public function getDownloadedMovieClip(pathToFileOnServer:String):MovieClip {
	
		
	
		if(this.discardMovieClipAfterDownload){
			trace("^^^^^^^^ You can't getDownloadedMovieClip because you have chosen to discard it after downloading.  This Download Manager object is currently being used for browser caching only.");
			
			// Return an empty Movie Clip
			var nextHighestDepth:Number = this.parentMovieClip.getNextHighestDepth();
			this.parentMovieClip.createEmptyMovieClip(("emptyMC" + nextHighestDepth), nextHighestDepth);
			return this.parentMovieClip[("emptyMC" + nextHighestDepth)];
		}
	
		if(!this.checkIfDownloadComplete(pathToFileOnServer)){
		
			trace("You are trying to get the movie clip for " + pathToFileOnServer +" before the download has finished.");
			
			// Return an empty Movie Clip
			var nextHighestDepth:Number = this.parentMovieClip.getNextHighestDepth();
			this.parentMovieClip.createEmptyMovieClip(("emptyMC" + nextHighestDepth), nextHighestDepth);
			return this.parentMovieClip[("emptyMC" + nextHighestDepth)];
		}
		
		// The downloadedMovieClipsArr Index is parrallel to the FileNames Array
		var arrayIndexOfFile:Number = this.getArrayIndexOfFilename(pathToFileOnServer);
		return this.downloadedMovieClipsArr[arrayIndexOfFile];
	
	}
	
	
	
	// Returns the array index of the filename.
	// Returns -1 if the name has not been added to the pre-loader.
	private function getArrayIndexOfFilename(pathToFileOnServer:String):Number {
	
		for(var i:Number = 0; i < this.filesArr.length; i++){
		
			if(pathToFileOnServer == this.filesArr[i])
				return i;
		}
		
		return -1;
	
	}
}
