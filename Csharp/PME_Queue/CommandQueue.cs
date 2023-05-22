using System;
using System.Collections;
using System.Threading;

namespace PME_Queue
{
	/// <summary>
	/// Holds a collection of CommandDeltail objects.  Fires them off one at a time .
	/// Fires an event whenever the stack changes.
	/// A CommandDetail object may also add to the CommandQueue as it is fired, which means the Command queue can start growing in size .
	/// Adding a new command will automatically start processing the request. 
	/// Each command is fired off within its own Thread to keep the UI free from hang ups
	/// </summary>
	/// 

	public class CommandQueue
	{
		private Queue cmdQueue = new Queue();

		// Send out an event every time a command gets added or removed from the queue
		// Sends a string with the event that informs what command is currently under progress.
		public delegate void QueueChangedDelegate( string commandDesc );
		public event QueueChangedDelegate OnQueueChange;

		// Sends out an event everytime a command notifies us of a progress update... such as a percentage value
		// ... when generating large artwork files.
		public delegate void ProgressUpdateDelegate( string progressDesc );
		public event ProgressUpdateDelegate OnProgressChange;

		public CommandQueue()
		{
			// constructor

		}

		private void QueueChanged(string commandDesc)
		{
			if( OnQueueChange != null )
				OnQueueChange( commandDesc );			
		}
		private void ProgressChanged(string progressDesc)
		{
			if( OnProgressChange != null )
				OnProgressChange( progressDesc );			
		}

		public void AddCommand( CommandDetail CmdDetailObj )
		{
			this.cmdQueue.Enqueue( CmdDetailObj );

			CmdDetailObj.OnProgressChange += new CommandDetail.ProgressUpdateDelegate(ProgressChanged);

			Thread thread1 = new Thread(new ThreadStart(ProcessACommand));
			thread1.Start();
		}



		// This method is called every time that a new command is added to the queue
		// Lock the contents of this method. To keep the command description from running into a critical race condition
		// Also, by issuing a lock here we will keep the 1 command running at a time.  We don't want to have 6 batches all generating PDF documents on the server at the same time.
		private void ProcessACommand()
		{
			lock(this)
			{
				CommandDetail cmdObj = (CommandDetail) this.cmdQueue.Dequeue();

				this.QueueChanged(cmdObj.commandDescription);

				cmdObj.FireCommand();

				this.QueueChanged("Ready");
				this.ProgressChanged("");

			}
		}
	}


}
