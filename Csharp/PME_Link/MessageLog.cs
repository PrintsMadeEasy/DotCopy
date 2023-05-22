using System;
using System.Data;
using System.IO;
using System.Web;
using System.Text.RegularExpressions;

namespace PME_Link
{

	/// <summary>
	/// Manages the messages sent to the WebBrowser control
	/// </summary>
	public class MessageLog : System.IDisposable
	{
		private string LogFileName;
		private AxSHDocVw.AxWebBrowser webBrowserObj;
		private StreamWriter outputStream;
		private bool FileOpenFlag;
		private string appPath;
		private ApplicationSettings appSettings;
		private int bookMarkTracker;
		private Form1 formObj;
		private string currentLogEntryHtml;
		private string previousLogHtml;

		public MessageLog(string fPath, string fName, AxSHDocVw.AxWebBrowser webObj, ApplicationSettings appSettingObj, Form1 frmObj)
		{
			this.webBrowserObj = webObj;
			this.LogFileName = fName;
			this.FileOpenFlag = false;
			this.appPath = fPath;
			this.formObj = frmObj;

			// Always start off fresh when the program loads
			this.ClearLogFile();

			this.appSettings = appSettingObj;

			this.bookMarkTracker = 0;
		}

		public void DisplayError( string Msg, string projNumStr )
		{
			this.CreateBookMark();

			this.ShowOrderNumber( "", projNumStr );

			WriteToLog(
				"<font style='font-family:Arial; font-size:18px;' color='#990000'>" +
				Msg + 
				"</font><br>"
				);

			this.ClearPage();

			this.DisplayLogBuffer();
		}
		
		public void ShowOrderCount( int ProjectsInOrder )
		{
			string LogMsg;

			if(ProjectsInOrder == 1)
				LogMsg = "Only 1 project in this order.";
			else
				LogMsg = "Total of " + ProjectsInOrder.ToString() + " projects in this order.";

			WriteToLog(
				"<font style='font-family:Arial; font-size:18px;'>" +
				LogMsg + 
				"</font><br><font style='font-size:4px;'>&nbsp;</font><br>"
				);
		}

		public void ShowDescription(string OrderDescription, string OptionsDescription)
		{
			WriteToLog(
				"<font style='font-family:Arial; font-size:18px;'>" +
				OrderDescription + " " + OptionsDescription +
				"</font><br><font style='font-size:4px;'>&nbsp;</font><br>"
				);
		}
		public void ShowShippingMethod(string shipCountryCode, string shipCarrier, string shipMethod, string shipMethodDescription, string shipPriority)
		{
			string MessageColor = "#000000";
			string TableBorderColor = "#FFFFFF";
			string InnerTableColor = "#FFFFFF";

			// Show Colors For Shipping Priority
			if(shipPriority == "Normal")
			{
				MessageColor = "#000000";
				TableBorderColor = "#333333";
				InnerTableColor = "#EEEEEE";
			}

			else if(shipPriority == "Medium")
			{
				MessageColor = "#777700";
				TableBorderColor = "#444400";
				InnerTableColor = "#FFFFEE";
			}
			else if(shipPriority == "Elevated")
			{
				MessageColor = "#0000CC";
				TableBorderColor = "#000099";
				InnerTableColor = "#EEEEFF";
			}
			else if(shipPriority == "High")
			{
				MessageColor = "#CC0000";
				TableBorderColor = "#990000";
				InnerTableColor = "#FFEEEE";
			}
			else if(shipPriority == "Urgent")
			{
				MessageColor = "#00CC00";
				TableBorderColor = "#009900";
				InnerTableColor = "#eeffee";
			}
			else
			{
				Form1.ShowError("Illegal Shipping Priority: " + shipPriority);
			}

			WriteToLog(
				"<table cellpadding='0' cellspacing='0' border='0' width='100%' bgcolor='" + TableBorderColor + "'><tr><td>" +
				"<table cellpadding='2' cellspacing='1' border='0' width='100%'><tr bgcolor='" + InnerTableColor + "'>" +
				"<td style='font-family:Arial; font-size:18px;'>Carrier: "+ shipCarrier + ", Shipping Method: <font color='" + MessageColor + "'>" + shipMethodDescription +  "</font></td>" +
				"</tr></table>" +
				"</td></tr></table><font style='font-size:4px;'>&nbsp;</font><br>"
				);
		}
		public void ShowStatus(string statusStr)
		{
			WriteToLog(
				"<font style='font-family:Arial; font-size:18px;'>Current Status: <b>" +
				statusStr +
				"</b></font><br><font style='font-size:4px;'>&nbsp;</font><br>"
				);
		}
		public void ShowInternationalShipment()
		{
			WriteToLog(
				"<img src='./Flags.jpg'><br>"
				);
		}
		public void ShowBoxCount( int BoxesInProject, int BoxesInOrder )
		{
			string LogMsg1 = "";

			if(BoxesInProject == 1)
				LogMsg1 += "1 Box for this project.";
			else
				LogMsg1 += BoxesInProject.ToString() + " Boxes for this project.";

			string LogMsg2 = "";

			if(BoxesInOrder == 1)
				LogMsg2 += "1 Box for this order.";
			else
				LogMsg2 += BoxesInOrder.ToString() + " Boxes for this order.";


			WriteToLog(
				"<table cellpadding='0' cellspacing='0' border='0' width='100%' bgcolor='#cccc99'><tr><td>" +
				"<table cellpadding='2' cellspacing='1' border='0' width='100%'><tr bgcolor='#ffffee'>" +
				"<td style='font-family:Arial; font-size:18px;'>&nbsp;" + LogMsg1 +  "</td>" +
				"<td style='font-family:Arial; font-size:18px;'>&nbsp;" + LogMsg2 +  "</td>" +
				"</tr></table>" +
				"</td></tr></table><font style='font-size:4px;'>&nbsp;</font><br>"
				);
		}
		public enum RackColor
		{
			Blue = 1,
			Orange = 2,
			Green = 3,
		}
		public void ShowRackPosition( string rack, string row, string column, RackColor rackCol )
		{
			string bgColor1;
			string bgColor2;

			switch ( rackCol )
			{
				case RackColor.Blue:
				{
					bgColor1 = "#336699";
					bgColor2 = "#cceeff";
					break;
				}
				case RackColor.Orange:
				{
					bgColor1 = "#CC0000";
					bgColor2 = "#FFEECC";
					break;
				}
				case RackColor.Green:
				{
					bgColor1 = "#339933";
					bgColor2 = "#EEFFEE";
					break;
				}
				default:
				{
					Form1.ShowError("Illegal color type sent to method ShowRackPosition.");
					return;
				}
			}

			WriteToLog(
				"<table cellpadding='0' cellspacing='0' border='0' width='50%' bgcolor='" + bgColor1 + "'><tr><td>" +
				"<table cellpadding='14' cellspacing='2' border='0' width='100%'><tr bgcolor='" + bgColor2 + "'>" +
				"<td style='font-family:Arial; font-size:30px;' align='center'>" + rack +  "</td>" +
				"<td style='font-family:Arial; font-size:30px;' align='center'>" + row +  "</td>" +
				"<td style='font-family:Arial; font-size:30px;' align='center'>" + column +  "</td>" +
				"</tr></table>" +
				"</td></tr></table>"
				);

		}
		public void ShowOrderNumber( string ordNum, string projNum )
		{
			string orderLink = "Order # <a href=\"javascript:ShowLink('" + this.appSettings.OrderLink  + ordNum +"')\">" + 
				ordNum+ "</a>" + " - " + 
			"<a href=\"javascript:ShowLink('" + this.appSettings.ProjectLink +  projNum+"')\">P" + projNum + "</a>";

			WriteToLog(
				"<table cellpadding='0' cellspacing='0' border='0' width='100%' bgcolor='#CCCCCC'><tr><td>" +
				"<table cellpadding='8' cellspacing='1' border='0' width='100%'><tr bgcolor='#f3f3f3'>" +
				"<td style='font-family:Arial; font-size:20px;' align='center'>" + orderLink +  "</td>" +
				"</tr></table>" +
				"</td></tr></table><font style='font-size:7px;'>&nbsp;</font><br>"
				);
		}

		public void ShowProductionNote( string note )
		{
			if( note != "" )
			{

				WriteToLog(
					"<font style='font-size:4px;'>&nbsp;</font><br>" +
					"<table cellpadding='0' cellspacing='0' border='0' width='100%' bgcolor='#990000'><tr><td>" +
					"<table cellpadding='3' cellspacing='1' border='0' width='100%'><tr bgcolor='#FFEEEE'>" +
					"<td style='font-family:Arial; font-size:20px; color:#660000'><b>NOTE:</b> " + HttpUtility.HtmlEncode( note ) +  "</td>" +
					"</tr></table>" +
					"</td></tr></table><font style='font-size:4px;'>&nbsp;</font><br>"
					);
			}
		}

		public void ShowShippingInstructions( string instructions )
		{

			if( instructions != "" )
			{
				// There can be multiple lines within the shipping instructions.
				// First we will split upon the newlines... then we will convert them into <BR> tags as we loop through the array of lines.
				string logHtmlOutput = "";
				
				string[] shippingInstructionLines = Regex.Split(instructions, "\n");

				foreach (string instructionLine in shippingInstructionLines)
				{
					// Don't add a <br> tag until after the first line has been added to the buffer.
					if(logHtmlOutput != "")
					{
						logHtmlOutput += "<br>";
					}

					// Encode the lines as there are added to the buffer.
					// If we encoded the buffer (as a whole) then the "<br>" tags would turn into "&gt;br&lt;"
					logHtmlOutput += HttpUtility.HtmlEncode( instructionLine );
				}


				WriteToLog(
					"<font style='font-size:4px;'>&nbsp;</font><br>" +
					"<table cellpadding='0' cellspacing='0' border='0' width='100%' bgcolor='#00AA33'><tr><td>" +
					"<table cellpadding='3' cellspacing='1' border='0' width='100%'><tr bgcolor='#F6FFF6'>" +
					"<td style='font-family:Arial; font-size:16px; color:#000000'><b><u>Shippping Instructions</u></b><br>" + logHtmlOutput +  "</td>" +
					"</tr></table>" +
					"</td></tr></table><font style='font-size:4px;'>&nbsp;</font><br>"
					);
			}
		}

		public void ClearPage()
		{
			WriteToLog("<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>");
		}
		public void CreateBookMark()
		{
			// Keep the log file from getting too big.  As soon as we hit 30 records, then wipe the slate clean
			if(this.bookMarkTracker > 30)
			{
				this.ClearLogFile();
				this.bookMarkTracker = 0;
			}

			this.bookMarkTracker++;
			WriteToLog("<a name='" + this.bookMarkTracker.ToString() + "'>&nbsp;");
		}
		public void DisplayLogBuffer()
		{

			// We are going to write out to a clean file every time so that the latest log message is at the very top of the screen.
			// So the first thing we write to the file will be the HTML head and body tag.
			try
			{
				outputStream = new StreamWriter(LogFileName);
				outputStream.WriteLine("<html>" +
					"<script> function ShowLink(TheAddress){ newWindow = window.open(TheAddress, 'pme'); newWindow.focus(); } </script>" +
					"<body style='font-family:Arial; font-size:18px;'>");
				outputStream.Close();
			}
			catch(System.Exception)
			{
				Form1.ShowError("The filename could not be opened: " + LogFileName);
			}



			// This will open the log file for appending.
			this.OpenLogForWrite();

			if(FileOpenFlag)
			{

				// Put the latest log entry on top so that we don't have to scroll.
				this.previousLogHtml = this.currentLogEntryHtml + this.previousLogHtml;
				outputStream.WriteLine(this.previousLogHtml);

				// Get the buffer ready for another scan.
				this.currentLogEntryHtml = "";
			}

			this.CloseLog();

			DateTime d1 = DateTime.Now;
			string noCache = d1.Hour.ToString() + d1.Minute.ToString() + d1.Second.ToString() + d1.Month.ToString() + d1.Date.ToString();

			object URL = appPath + "\\" + LogFileName + "?noCache=" + noCache + "#" + this.bookMarkTracker.ToString();
			webBrowserObj.Navigate2(ref URL);
			this.formObj.RefreshForm();  // Make sure that changes are seen immediately to the user
			
		}

		public void WriteToLog(string Msg)
		{
			this.currentLogEntryHtml += Msg;


		}

		// It is not necessary to call this if you are calling the method DisplayLogBuffer
		// DisplayLogBuffer will refresh the page on its own
		public void RefreshPage()
		{
			DateTime d1 = DateTime.Now;
			string noCache = d1.Hour.ToString() + d1.Minute.ToString() + d1.Second.ToString() + d1.Month.ToString() + d1.Date.ToString();
			
			object URL = appPath + "\\" + LogFileName + "?noCache" + noCache;
			webBrowserObj.Navigate2(ref URL);
			this.formObj.RefreshForm();  // Make sure that changes are seen immediately to the user
		}

		public void ClearLogFile()
		{
			// Clear out our temporary variables
			this.currentLogEntryHtml = "";
			this.previousLogHtml = "";

			try
			{
				outputStream = new StreamWriter(LogFileName);
				outputStream.WriteLine("<html>" +
					"<script> function ShowLink(TheAddress){ newWindow = window.open(TheAddress, 'pme'); newWindow.focus(); } </script>" +
					"<body style='font-family:Arial; font-size:18px;'>Ready to Scan</body></html>");
				outputStream.Close();
			}
			catch(System.Exception)
			{
				Form1.ShowError("The filename could not be opened: " + LogFileName);
			}
		}

		private void OpenLogForWrite()
		{
			if(FileOpenFlag)
				return;

			try
			{
				FileOpenFlag = true;

				// Append to the log file 
				outputStream = new StreamWriter(LogFileName, true);
				
			}
			catch(System.Exception)
			{
				FileOpenFlag = false;
				Form1.ShowError("The filename could not be appended: " + LogFileName);
			}
		}
		private void CloseLog()
		{
			if(FileOpenFlag)
			{
				FileOpenFlag = false;
				outputStream.Close();
			}
		}

		void System.IDisposable.Dispose()
		{
			this.CloseLog();
		}

	}
}
