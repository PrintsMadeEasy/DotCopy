using System;

namespace PME_Queue
{
	/// <summary>
	/// Summary description for Utility.
	/// </summary>
	public class Utility
	{
		public Utility()
		{
			//
			// TODO: Add constructor logic here
			//
		}

		public static string GetStatusDescriptionLocal( string StatusChar )
		{
			switch(StatusChar)
			{
				case "T": 
					return "Confirming Printing";
					break;
				case "G": 
					return "Generating Artwork";
					break;
				case "D": 
					return "Downloading PDF";
					break;
				case "U": 
					return "Uploading to Press";
					break;
				case "Q": 
					return "Queued on Press";
					break;
				case "P": 
					return "Pending";
					break;
				default:
					Form1.ShowError( "Illegal Status type in GetStatusDescriptionLocal: " + StatusChar );
					return "Error";
					break;
			}
		}
	}
}
