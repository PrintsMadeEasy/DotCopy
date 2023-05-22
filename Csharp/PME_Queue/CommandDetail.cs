using System;
using System.Data;
using System.Data.OleDb;
using System.Collections;
using System.IO;
using System.Net;
using System.Text.RegularExpressions;




namespace PME_Queue
{
	public class CommandDetail
	{
		private string cmdDesc;
		private CommandQueue cmdQueue;
		private CommandType cmdType;  // Will be used to hold a value from the CommandType enumeration
		private ApplicationSettings appSettings;
		private Form1 form1Obj;
		private OleDbConnection aConnection;
		private OleDbDataAdapter adapter;

		private string projectIDlist;
		private string batchID;
		private string jobID;
		private string fNamePrefix;
		private string pdfProfile;
		private string downloadURL;
		private string filenamePDF;
		private string pageCount;
		private string systemCommand;
		private string queueName;
		private string personsNameThatPrinted;
		private string prodID;

		// Sends out an event everytime a command notifies us of a progress update... such as a percentage value
		// ... when generating large artwork files.
		public delegate void ProgressUpdateDelegate( string progressDesc );
		public event ProgressUpdateDelegate OnProgressChange;

		public CommandDetail( CommandQueue CmdQueueObj, ApplicationSettings appSet, Form1 formObjectRef )
		{
			this.cmdQueue = CmdQueueObj;
			this.cmdDesc = "Error, Command Description has not been set yet.";
			this.appSettings = appSet;
			this.form1Obj = formObjectRef;

			//create the database connection
			this.aConnection = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=PME_QueueDB.mdb");
			this.adapter = new OleDbDataAdapter();

			this.projectIDlist = "";
			this.batchID = "0";
			this.jobID = "0";
			this.fNamePrefix = "";
			this.pdfProfile = "";
			this.downloadURL = "";
			this.filenamePDF = "";
			this.pageCount = "";
			this.personsNameThatPrinted = "";
			this.prodID = "";
		
		}
		private void ProgressChanged(string progressDesc)
		{
			if( OnProgressChange != null )
				OnProgressChange( progressDesc );			
		}

		public void FireCommand()
		{

			// Setup communication with the Server's API.
			// The API might not get used with every command type.  Just create it anyway to keep code shorter
			ServerAPI srvAPI = new ServerAPI(this.appSettings);

			string pathToPDFfile = "";

			CommandDetail CmdDt = new CommandDetail(this.cmdQueue, this.appSettings, this.form1Obj );

			switch( this.cmdType )
			{

				case CommandType.RefreshProjectCount:

					this.form1Obj.RefreshBatchCounts("");

					break;
				case CommandType.StartBatch:

					if(this.batchID == "0")
					{
						Form1.ShowError("A batch ID must be set before calling this command.");
						break;
					}

					DataSet DS_batchCategory = new DataSet("batchCat_DS");
					// Get details on the batch from the local database
					this.aConnection.Open();
					this.adapter.SelectCommand = new OleDbCommand(
						"SELECT BCL.ID AS BatchCategoryLinkID, B.ID AS BatchID, B.FilenamePrefix, B.ProductID, B.ProjectOptions, B.ProjectCap, B.MaxPages, BC.ShippingMethods, BC.DueToday, BC.Urgent, BC.PDFprofile, BC.MinProjectQuantity, BC.MaxProjectQuantity, BC.SystemCommand, B.QueueNameOnPress FROM " +
						"(batchcategorylink AS BCL INNER JOIN batches AS B ON BCL.BatchID = B.ID) " +
						"INNER JOIN batchcategory AS BC ON BCL.CategoryID = BC.ID WHERE BCL.ID=" + this.batchID
						, this.aConnection);

					this.adapter.Fill(DS_batchCategory, "batches");
					this.aConnection.Close();

					DataTable t = DS_batchCategory.Tables["batches"];
		
					if(t.Rows.Count == 0)
					{
						Form1.ShowError("There is a problem... the database is empty for the given Batch Category Link ID");
						break;
					}

					// Collect a list of all ProjectID's from the batch from the server
					srvAPI.command = "get_project_list";

					DataRow r = t.Rows[0];


					ProjectCountRequest pcr = new ProjectCountRequest();
					pcr.batchID = r[t.Columns["BatchCategoryLinkID"]].ToString();
					pcr.productID = r[t.Columns["ProductID"]].ToString();
					pcr.projectCap = r[t.Columns["ProjectCap"]].ToString();
					pcr.maxPages = r[t.Columns["MaxPages"]].ToString();
					pcr.projectOptions = r[t.Columns["ProjectOptions"]].ToString();
					pcr.shippingMethods = r[t.Columns["ShippingMethods"]].ToString();
					pcr.pdfProfile = r[t.Columns["PDFprofile"]].ToString();
					pcr.maxProjectQuantity = r[t.Columns["MaxProjectQuantity"]].ToString();
					pcr.minProjectQuantity = r[t.Columns["MinProjectQuantity"]].ToString();
					pcr.dueToday = (bool) r[t.Columns["DueToday"]];
					pcr.urgent = (bool) r[t.Columns["Urgent"]];
					pcr.statusOnServer = "P"; // We only want to count "proofed" orders.

					string BatchIndex = r[t.Columns["BatchID"]].ToString();

					// We just want to get the Project List of 1 batch
					srvAPI.ClearProjectCountRequest();
					srvAPI.addBatchCountRequest(pcr); 

					srvAPI.FireRequest();

					if(srvAPI.error)
					{
						this.form1Obj.WriteMessageToLog("A communincation error has occured: " + srvAPI.errorDescription, true);
						break;
					}

					// If there are no projects to be printed in the batch... then show a message to the error log and don't continue
					if(srvAPI.projectCount == "0")
					{
						// Just in case the top frame has not been updated (due to possible weird exceptions with scrolling in the window frames)
						// People maybe be trying to start a  batch for a second time (and the grid wasn't updated)
						// We don't really want to re-connect to the server every time this happens, but refreshing the top frame from our local database is quick. (this may only happen in very rare cases)
						this.form1Obj.RefreshGridCommands();

						this.form1Obj.WriteMessageToLog( "Queue is empty, not starting batch.", false );
						break;
					}

					// Now that we have a project list stored in the API object... we want to notify the server and change the status to Queued
					// We are making 2 handshakes with the server... the 1st gets the project ID list
					// The second sends the project list back to the server and tries to change the status
					// If for some reason the status was changed by somebody else on a project (like placed on hold) then the status change will not happen on the second call
					// The server will return with a new Project ID list (ones that were able to be changed successfully)
					// In this case.... Almost always the server should respond with the same project ID list and project count between the 2 calls.
					// Theoritcaly we could do this in 1 call to the server... however we have to do it this way to act securily with the PME API.
					// We are changing from "proofed" to "queued" on the server
					srvAPI.command = "change_project_status_bulk";
					srvAPI.status = "P";
					srvAPI.statusChange = "Q";

					srvAPI.FireRequest();

					if(srvAPI.error)
					{
						this.form1Obj.WriteMessageToLog("A communincation error has occured: " + srvAPI.errorDescription, true);
						break;
					}

					// If there are no projects to be printed in the batch... then show a message to the error log and don't continue
					if(srvAPI.projectCount == "0")
					{
						this.form1Obj.WriteMessageToLog( "Someting strange happened.  The queue was not empty and then all of a sudden it is, not able to start batch.", true );
						break;
					}


					string FilenamePrefix = r[t.Columns["FilenamePrefix"]].ToString();
					string PDFprofileName = r[t.Columns["PDFprofile"]].ToString();

					this.form1Obj.WriteMessageToLog("A new batch was started for " + FilenamePrefix, false);


					// Insert the new Active Job into our local database.
					this.aConnection.Open();
					OleDbCommand InsertQry = new OleDbCommand(
						"INSERT INTO commands (Status, FileName, PageCount, ProjectCount, ProjectList, BatchID) " +
						"values ('P', '"+FilenamePrefix+"', 0, "+srvAPI.projectCount+", '"+srvAPI.projectList+"', " + BatchIndex + ")"
						, this.aConnection);
					InsertQry.ExecuteNonQuery();
					this.aConnection.Close();


					// We have to get the MaxID of the new command that was created
					// I don't think I can lock the tables with Access, so I will just create specific WHERE query on the record that just went in.
					// We need to make sure we are getting back the right ID
					DataSet DS_MaxID = new DataSet("maxID");
					this.aConnection.Open();
					this.adapter.SelectCommand = new OleDbCommand(
						"SELECT MAX(ID) AS ID FROM commands " +
						"WHERE Status='P' AND ProjectCount=" + srvAPI.projectCount + " AND FileName='" + FilenamePrefix + "'"
						, this.aConnection);
					this.adapter.Fill(DS_MaxID, "commands");
					this.aConnection.Close();
					DataTable t2 = DS_MaxID.Tables["commands"];
					DataRow r2 = t2.Rows[0];
					string InsertID = r2[t2.Columns["ID"]].ToString();


					this.form1Obj.RefreshGridCommands();
					this.form1Obj.RefreshBatchCounts(pcr.productID);


					// Start up a new command and insert it into the queue
					CmdDt.command = CommandType.GenerateArtwork;
					CmdDt.jobNumber = InsertID;
					CmdDt.projectIDlist = srvAPI.projectList;
					CmdDt.pdfProfileName = PDFprofileName;
					CmdDt.fileNamePrefix = FilenamePrefix;
					CmdDt.queueNameOnPress = r[t.Columns["QueueNameOnPress"]].ToString();
					CmdDt.productID = r[t.Columns["ProductID"]].ToString();
					CmdDt.systemCommandForArtFile = r[t.Columns["SystemCommand"]].ToString();
	
					this.cmdQueue.AddCommand(CmdDt);

					break;
				case CommandType.GenerateArtwork:

					if(this.jobID == "0")
					{
						Form1.ShowError("A Job ID must be set before calling this command.");
						break;
					}

					// Connect the event of the API directly to our local event... which will in-turn update the CommandQueue and eventually the Form
					srvAPI.OnProgressChange += new ServerAPI.ProgressUpdateDelegate(ProgressChanged);

					// Change the status to "Generating Artwork";
					this.aConnection.Open();
					OleDbCommand UpdateQry = new OleDbCommand(
						"UPDATE commands SET Status='G' WHERE ID=" + this.jobID
						, this.aConnection);
					UpdateQry.ExecuteNonQuery();
					this.aConnection.Close();

					this.form1Obj.RefreshGridCommands();

					this.form1Obj.WriteMessageToLog("Starting to generate artwork for: " + this.fileNamePrefix, false);


					srvAPI.command = "generate_pdf_doc_from_projectlist";
					srvAPI.projectList = this.projectIDlist;
					srvAPI.fileNamePrefix = this.fileNamePrefix;
					srvAPI.pdfProfileName = this.pdfProfileName;
					srvAPI.productID = this.prodID;

					srvAPI.FireRequest();

					if(srvAPI.error)
					{
						this.form1Obj.WriteMessageToLog("An error occured while generating the artwork: " + srvAPI.errorDescription, true);
						break;
					}

					this.form1Obj.WriteMessageToLog("Downloading artwork: " + srvAPI.fileNameOfPDFdoc, false);

					// Change the status to "Downloading PDF"
					// We also need to update the file name... before it was really being stored just as the "File Name Prefix" but it was missing the date stamp on the file
					// Also we now have the PageCount since the PDF file was just generated.
					this.aConnection.Open();
					OleDbCommand UpdateQry2 = new OleDbCommand(
						"UPDATE commands SET Status='D', FileName='"+ srvAPI.fileNameOfPDFdoc +"', PageCount="+ srvAPI.pageCountInPDF +" WHERE ID=" + this.jobID
						, this.aConnection);
					UpdateQry2.ExecuteNonQuery();
					this.aConnection.Close();

					this.form1Obj.RefreshGridCommands();


					// Start up a new command and insert it into the queue
					CmdDt.command = CommandType.DownloadArtwork;
					CmdDt.jobNumber = this.jobNumber;
					CmdDt.pageCountInPDF = srvAPI.pageCountInPDF;
					CmdDt.downloadURLforPDF = srvAPI.downloadURLforPDF;
					CmdDt.fileNameOfPDFdoc = srvAPI.fileNameOfPDFdoc;
					CmdDt.systemCommandForArtFile = this.systemCommandForArtFile;
					CmdDt.queueNameOnPress = this.queueNameOnPress;
					this.cmdQueue.AddCommand(CmdDt);

					break;

				case CommandType.ConfirmPrinting:

					if(this.jobID == "0")
					{
						Form1.ShowError("A Job ID must be set before calling this command.");
						break;
					}
					if(this.personsNameThatPrinted == "")
					{
						Form1.ShowError("The persons name the printed the job must be set before calling this command.");
						break;
					}

					DataSet DS_JobConfirm = new DataSet("jobConfirm");
					this.aConnection.Open();
					this.adapter.SelectCommand = new OleDbCommand(
						"SELECT * FROM commands " +
						"WHERE ID=" + this.jobID
						, this.aConnection);
					this.adapter.Fill(DS_JobConfirm, "commands");
					this.aConnection.Close();
					DataTable t4 = DS_JobConfirm.Tables["commands"];
					DataRow r4 = t4.Rows[0];

					string theFileName = r4[t4.Columns["FileName"]].ToString();
					string thePageCount = r4[t4.Columns["PageCount"]].ToString();
					string theProjectCount = r4[t4.Columns["ProjectCount"]].ToString();
					string theProjectList = r4[t4.Columns["ProjectList"]].ToString();


					// Now contact the server and change the status from Queued to Printed
					srvAPI.command = "change_project_status_bulk";
					srvAPI.status = "Q";
					srvAPI.statusChange = "T";
					srvAPI.projectList = theProjectList;

					srvAPI.FireRequest();

					if(srvAPI.error)
					{
						this.form1Obj.WriteMessageToLog("A communincation error has occured confirming the print job: " + srvAPI.errorDescription, true);
						break;
					}

					// The server has the confirmation now.  Lets clean up our local database
				
					// After confirming a job delete the file
					pathToPDFfile = "PDF_Files/" + theFileName;
					if(File.Exists(pathToPDFfile))
						File.Delete(pathToPDFfile);
				

					// Remove the job from our local database
					this.aConnection.Open();
					OleDbCommand DeleteQry2 = new OleDbCommand(
						"DELETE FROM commands WHERE ID=" + this.jobID
						, this.aConnection);
					DeleteQry2.ExecuteNonQuery();
					this.aConnection.Close();

					// Get rid of single quotes so that it won't interfere with the insert query
					this.personsNameThatPrinted = Regex.Replace( this.personsNameThatPrinted, "'",  "");

					// Insert the results into our history
					// Insert the new Active Job into our local database.
					this.aConnection.Open();
					OleDbCommand InsertQry3 = new OleDbCommand(
						"INSERT INTO history (FileName, ProjectList, PageCount, ProjectCount, ProjectCountActual, ProjectListActual, PersonsName, DateCompleted) " +
						"values ('"+theFileName+"', '"+theProjectList+"', "+thePageCount+", "+theProjectCount+", "+srvAPI.projectCount+", '"+srvAPI.projectList+"', '"+this.personsNameThatPrinted+"', '"+DateTime.Now.ToString()+"')"
						, this.aConnection);

					InsertQry3.ExecuteNonQuery();
					this.aConnection.Close();

					this.form1Obj.WriteMessageToLog("Printing job has been confirmed by " + this.personsNameThatPrinted + " for: " + theFileName, false);

					this.form1Obj.RefreshGridCommands();
					this.form1Obj.RefreshGridHistory();

					break;
				case CommandType.DownloadArtwork:

					if(this.jobID == "0")
					{
						Form1.ShowError("A Job ID must be set before calling this command.");
						break;
					}
					if(this.downloadURLforPDF == "" || this.fileNameOfPDFdoc == "")
					{
						Form1.ShowError("The download URL must be set before calling this command.");
						break;
					}

					// Download the PDF file and save it to disk
					try
					{
						WebClient Client = new WebClient ();
						Client.DownloadFile(this.downloadURLforPDF, "PDF_Files/" + this.fileNameOfPDFdoc);
					}
					catch ( System.Exception e )
					{
						this.form1Obj.WriteMessageToLog("An error occured downloading the file: " + e.Message, true);
						break;
					}


					// Change the status to "Uploading to Press";
					this.aConnection.Open();
					OleDbCommand UpdateQry3 = new OleDbCommand(
						"UPDATE commands SET Status='U' WHERE ID=" + this.jobID
						, this.aConnection);
					UpdateQry3.ExecuteNonQuery();
					this.aConnection.Close();

					this.form1Obj.RefreshGridCommands();


					// Start up a new command and insert it into the queue
					CmdDt.command = CommandType.UploadingToPress;
					CmdDt.jobNumber = this.jobNumber;
					CmdDt.fileNameOfPDFdoc = this.fileNameOfPDFdoc;
					CmdDt.systemCommandForArtFile = this.systemCommandForArtFile;
					CmdDt.queueNameOnPress = this.queueNameOnPress;
					this.cmdQueue.AddCommand(CmdDt);

					break;
				case CommandType.UploadingToPress:

					if(this.jobID == "0")
					{
						Form1.ShowError("A Job ID must be set before calling this command.");
						break;
					}
					if(this.queueNameOnPress == "" || this.systemCommandForArtFile == "")
					{
						Form1.ShowError("The system command, command Arguments, and Queue Name on press must be set before calling this command.");
						break;
					}

					// Replace the Queue name on the printing Press and our File Name of the Artwork file into the arguments of the Process that we are going to start
					string filePathToArtworkFile = System.Environment.CurrentDirectory + "\\PDF_Files\\" + this.fileNameOfPDFdoc;
					string CommandLineToRun = Regex.Replace( this.systemCommandForArtFile, "{QUEUE}",  this.queueNameOnPress);
					CommandLineToRun = Regex.Replace( CommandLineToRun, "{FILE}",  filePathToArtworkFile);

					this.form1Obj.WriteMessageToLog("Command: " + CommandLineToRun, false);

					try
					{
						// Write a batch file to disk
						using (StreamWriter sw = new StreamWriter("UploadFile.bat")) 
							sw.WriteLine(CommandLineToRun);

						// Execute the batch file we just created in a hidden window
						System.Diagnostics.Process proc = new System.Diagnostics.Process();
						proc.EnableRaisingEvents=false;
						proc.StartInfo.FileName = "UploadFile.bat";
						proc.StartInfo.WindowStyle = System.Diagnostics.ProcessWindowStyle.Hidden;
						proc.Start();
						proc.WaitForExit();
					}
					catch ( System.Exception e )
					{
						this.form1Obj.WriteMessageToLog("An error occured moving the file to the printing press: " + e.Message, true);
						break;
					}

					// Change the status to "Queued to Press";
					this.aConnection.Open();
					OleDbCommand UpdateQry4 = new OleDbCommand(
						"UPDATE commands SET Status='Q' WHERE ID=" + this.jobID
						, this.aConnection);
					UpdateQry4.ExecuteNonQuery();
					this.aConnection.Close();

					this.form1Obj.WriteMessageToLog("File was sent to printing press: " + this.fileNameOfPDFdoc, false);
					this.form1Obj.RefreshGridCommands();

					break;
				case CommandType.CancelJob:

					if(this.jobID == "0")
					{
						Form1.ShowError("A Job ID must be set before calling this command.");
						break;
					}

					DataSet DS_JobCancel = new DataSet("JobCancel_DS");
					// Get details on the job from the local database
					this.aConnection.Open();
					this.adapter.SelectCommand = new OleDbCommand(
						"SELECT ProjectCount, ProjectList, FileName, ProductID FROM " +
						"commands AS COM INNER JOIN batches AS BAT ON COM.BatchID = BAT.ID " +
						"WHERE COM.ID=" + this.jobID
						, this.aConnection);

					this.adapter.Fill(DS_JobCancel, "jobs");
					this.aConnection.Close();

					DataTable t1 = DS_JobCancel.Tables["jobs"];
		
					if(t1.Rows.Count == 0)
					{
						// In case the job was already canceled (but the grid was not refreshed)
						// They may be trying to re-cancel a job.  This could happen if the mouse was held down and scrolling in the window pane (we don't allow the grid to update because of a weird exception)
						// So if we update the grid now it should remove the job from the upper window like the user expects.
						this.form1Obj.RefreshGridCommands();
						break;
					}

					DataRow r1 = t1.Rows[0];

					// We want to change the status from Queued back to Proofed
					srvAPI.command = "change_project_status_bulk";
					srvAPI.status = "Q";
					srvAPI.statusChange = "P";
					srvAPI.projectList = r1[t1.Columns["ProjectList"]].ToString();
					srvAPI.FireRequest();

					if(srvAPI.error)
					{
						this.form1Obj.WriteMessageToLog( r1[t1.Columns["FileName"]].ToString() + ": Job could not be canceled.  API error: " + srvAPI.errorDescription, true );
						break;
					}


					// If some jobs were put on hold or canceled ... those jobs would not be able to reset back to proofed
					// If less jobs were canceled than was requested... then show them the details
					string cancelProjectMismatch = "";
					if(srvAPI.projectCount != r1[t1.Columns["ProjectCount"]].ToString())
						cancelProjectMismatch = "There were " + r1[t1.Columns["ProjectCount"]].ToString() + " jobs that attempted to cancel.";


					// Looks like the API request went through fine and dandy... Let them know how many projects were able to be canceled
					this.form1Obj.WriteMessageToLog( r1[t1.Columns["FileName"]].ToString() + ": " + srvAPI.projectCount + " jobs were canceled. " + cancelProjectMismatch, false);


					// Remove the PDF file from our local drive
					pathToPDFfile = "PDF_Files/" + r1[t1.Columns["FileName"]].ToString();
					if(File.Exists(pathToPDFfile))
						File.Delete(pathToPDFfile);


					// Remove the job from our local database
					this.aConnection.Open();
					OleDbCommand DeleteQry = new OleDbCommand(
						"DELETE FROM commands WHERE ID=" + this.jobID
						, this.aConnection);
					DeleteQry.ExecuteNonQuery();
					this.aConnection.Close();



					this.form1Obj.RefreshGridCommands();
					this.form1Obj.RefreshBatchCounts(r1[t1.Columns["ProductID"]].ToString());

					break;
				default:
					Form1.ShowError( "Illegal Command Type was set" );
					break;
			}

		}

		public enum CommandType
		{
			RefreshProjectCount = 1,
			StartBatch = 2,
			GenerateArtwork = 3,
			DownloadArtwork = 4,
			ConfirmPrinting = 5,
			CancelJob = 6,
			UploadingToPress = 7,
		}

		public CommandType command
		{
			get
			{
				return this.cmdType;
			}
			set
			{
				this.cmdType = value;

				// Create a verbal description to go with each command
				switch( this.cmdType )
				{
					case CommandType.RefreshProjectCount:
						this.cmdDesc = "Refreshing Project Counts from Server";
						break;
					case CommandType.StartBatch:
						this.cmdDesc = "Starting a New Batch.";
						break;
					case CommandType.GenerateArtwork:
						this.cmdDesc = "Generating PDF Document on Server";
						break;
					case CommandType.DownloadArtwork:
						this.cmdDesc = "Downloading PDF";
						break;
					case CommandType.ConfirmPrinting:
						this.cmdDesc = "Confirming Print Job";
						break;
					case CommandType.CancelJob:
						this.cmdDesc = "Canceling";
						break;
					case CommandType.UploadingToPress:
						this.cmdDesc = "Uploading to Printing Press";
						break;
					default:
						Form1.ShowError( "Illegal Command Type was set" );
						break;
				}
			}
		}


		public string projectList
		{
			set
			{
				this.projectIDlist = value;
			}
		}
		public string jobNumber
		{
			set
			{
				this.jobID = value;
			}
			get
			{
				return this.jobID;
			}
		}
		public string batchCategoryLinkID
		{
			set
			{
				this.batchID = value;
			}
			get
			{
				return this.batchID;
			}
		}
		public string fileNamePrefix
		{
			set
			{
				this.fNamePrefix = value;
			}
			get
			{
				return this.fNamePrefix;
			}
		}
		public string pdfProfileName
		{
			set
			{
				this.pdfProfile = value;
			}
			get
			{
				return this.pdfProfile;
			}
		}
		public string commandDescription
		{
			get
			{
				return this.cmdDesc;
			}
		}
		public string downloadURLforPDF
		{
			set
			{
				this.downloadURL = value;
			}
			get
			{
				return this.downloadURL;
			}
		}
		public string fileNameOfPDFdoc
		{
			set
			{
				this.filenamePDF = value;
			}
			get
			{
				return this.filenamePDF;
			}
		}
		public string pageCountInPDF
		{
			set
			{
				this.pageCount = value;
			}
			get
			{
				return this.pageCount;
			}
		}
		public string systemCommandForArtFile
		{
			set
			{
				this.systemCommand = value;
			}
			get
			{
				return this.systemCommand;
			}
		}

		public string queueNameOnPress
		{
			set
			{
				this.queueName = value;
			}
			get
			{
				return this.queueName;
			}
		}
		public string personsName
		{
			set
			{
				this.personsNameThatPrinted = value;
			}
			get
			{
				return this.personsNameThatPrinted;
			}
		}
		public string productID
		{
			get
			{
				return this.prodID;
			}
			set
			{
				this.prodID = value;
			}
		}

	}
}
