using System;
using System.Collections;


namespace PME_Link
{

	/// <summary>
	/// Holds a collection of CommandDeltail objects.  Fires them off one at a time .
	/// Fires an event whenever the stack changes.
	/// Provides methods for adding new commands and checking the status of existing commands
	/// It will continue looping through its queue until there are no more CommandDetail tasks to perform
	/// A CommandDetail object may also add to the CommandQueue as it is fired, which means the Command queue can start growing in size .
	/// Adding a new command will automatically start processing the request. 
	/// </summary>
	/// 

	public class CommandQueue
	{
		private Queue cmdQueue = new Queue();
		private int commandCounter;

		// Send out an event every time a command gets added or removed from the queue
		// Send a bool parameter that says if the queue is now empty
		public delegate void QueueChangedDelegate( bool isEmpty );
		public event QueueChangedDelegate OnQueueChange;

		public CommandQueue()
		{
			this.commandCounter = 0;
		}

		public void QueueChanged()
		{
			if( OnQueueChange != null )
				OnQueueChange( this.isEmpty );			
		}

		public void AddCommand( CommandDetail CmdDetailObj )
		{
			this.cmdQueue.Enqueue( CmdDetailObj );
			this.commandCounter++;

			this.QueueChanged();

			// Start up the Process Command loop if it is not already running
			if( this.commandCounter == 1)
				this.ProcessCommands();
		}

		// Get the description of the command detail at the first in the queue
		public string CurrentCommandDescription()
		{
			if( this.commandCounter == 0 )
				return "Error, no more commands left";

			CommandDetail cmdObj = (CommandDetail) this.cmdQueue.Peek();
			return cmdObj.commandDescription;
		}

		public void ClearCommands( )
		{
			this.cmdQueue.Clear();
			this.commandCounter = 0;

			this.QueueChanged();

		}

		// Will continue processing commands until there are no more
		// This method is called every time that a new command is added to the queue
		// It will start running when the first CommandDetail is added.  That is the only time the loop will start-up.
		public void ProcessCommands()
		{
			while( this.commandCounter > 0 )
			{

				// Scrape off the next Command Detail object and fire whatever its command is
				CommandDetail cmdObj = (CommandDetail) this.cmdQueue.Dequeue();
				cmdObj.FireCommand();
				this.commandCounter--;

				this.QueueChanged();
			}
		}

		// Returns true or false depending on whether any commands are remaining to be executed
		public bool isEmpty
		{
			get
			{
				if( this.commandCounter == 0 )
					return true;
				else
					return false;
			}
		}
	}


}
