using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;
using System.Data;
using System.Data.OleDb;
using System.Threading;
using System.Text.RegularExpressions;
using System.IO;




namespace PME_Queue
{
	/// <summary>
	/// Summary description for Form1.
	/// </summary>
	public class Form1 : System.Windows.Forms.Form
	{
		private System.Windows.Forms.MainMenu mainMenu1;
		private System.Windows.Forms.MenuItem menuItem1;
		private System.Windows.Forms.MenuItem menuItem2;
		private System.Windows.Forms.MenuItem menuItem3;
		private System.Windows.Forms.MenuItem menuItem4;
		private System.ComponentModel.IContainer components;
		private System.Windows.Forms.ContextMenu contextMenu1;
		private System.Windows.Forms.MenuItem menuItem6;
		private System.Windows.Forms.MenuItem menuItem7;

		private System.Windows.Forms.Panel panel1;
		private System.Windows.Forms.Splitter splitter1;
		private System.Windows.Forms.Panel panel2;
		private System.Windows.Forms.StatusBar statusBar1;
		private System.Windows.Forms.Splitter splitter2;
		private System.Windows.Forms.Panel panel3;
		private System.Windows.Forms.TabControl tabControl1;
		private System.Windows.Forms.TabPage tabPage1;
		private SourceGrid2.Grid gridCommands;
		private System.Windows.Forms.TabPage tabPage2;
		private System.Windows.Forms.Splitter splitter3;
		private SourceGrid2.Grid gridBatches;
		private System.Windows.Forms.Splitter splitter4;
		private System.Windows.Forms.RichTextBox richTextBox1;
		private System.Windows.Forms.MenuItem menuItem8;
		private System.Windows.Forms.MenuItem menuItem9;
		private System.Windows.Forms.StatusBarPanel statusBarPanel1;
		private System.Windows.Forms.MenuItem menuItem10;
		private SetupForm FrmSetup;
		private SearchForm SearchProjectFrm;
		private ConfirmPrinting FrmConfirm;
		private EditBatchesForm FrmEditBatches;
		private OleDbConnection aConnection;
		private OleDbConnection aConnection2;
		private OleDbDataAdapter adapter;
		private OleDbDataAdapter adapter2;
		private CommandQueue CmdQueue;
		private Hashtable BtnBatchesTracker;
		private SourceGrid2.Grid gridHistory;
		private System.Windows.Forms.Timer timer1;
		private System.Windows.Forms.Timer timerRefreshCounts;
		private System.Windows.Forms.NotifyIcon notifyIcon1;
		private Hashtable BtnJobTracker;
		private System.Windows.Forms.StatusBarPanel statusBarPanel2;
		private System.Windows.Forms.TabControl tabControl2;
		private System.Windows.Forms.TabPage[] productTabPages;
		private bool shouldExitApplication;
		private string selectedProductName; // Keeps track of what Tab is activated within our Batch Counts
		private bool productTabSwitchInProgress = false;
		private bool productTabsBuildInProgress = false;

		static private bool singletonIntialized = false;
		private System.Windows.Forms.MenuItem menuItem5;
		private System.Windows.Forms.MenuItem menuItem11;
		static private Form1 singletonInstance;

		


		public Form1()
		{
			//
			// Required for Windows Form Designer support
			//

			InitializeComponent();

			//
			// TODO: Add any constructor code after InitializeComponent call
			//


			//create the 2 database connections
			this.aConnection = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=PME_QueueDB.mdb");
			this.adapter = new OleDbDataAdapter();
			this.aConnection2 = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=PME_QueueDB.mdb");
			this.adapter2 = new OleDbDataAdapter();

			this.ReBuildProductTabs();

			// Use this flag to keep track if we are trying to exit.
			// When someone tries to close the form we will actually try to minimize the application instead...
			this.shouldExitApplication = false;

			// The key to this Hashtable should be the Row# within the "Batches" grid.
			// We can't store the BatchID within the Button object... so we need to keep track of our values by relating to the button position
			this.BtnBatchesTracker = new Hashtable();
			this.BtnJobTracker = new Hashtable();

			this.FrmSetup = new SetupForm();
			this.FrmConfirm = new ConfirmPrinting();
			this.SearchProjectFrm = new SearchForm();
			this.AddOwnedForm(this.FrmSetup);
			this.AddOwnedForm(this.FrmConfirm);
			this.AddOwnedForm(this.SearchProjectFrm);


			this.RefreshGridBatches();
			this.RefreshGridCommands();
			this.RefreshBatchCounts("");
			this.RefreshGridHistory();

			this.CmdQueue = new CommandQueue();

			this.CmdQueue.OnQueueChange += new CommandQueue.QueueChangedDelegate(CmdQueue_OnQueueChange);
			this.CmdQueue.OnProgressChange += new CommandQueue.ProgressUpdateDelegate(CmdQueue_OnProgressChange);

			Form1.singletonIntialized = true;
			Form1.singletonInstance = this;

		}

		// Singleton Pattern for getting the form object.
		static public Form1 getForm1Object()
		{
			if(!Form1.singletonIntialized)
				Form1.ShowError("Form 1 must already be instialized before calling this method.");

			return Form1.singletonInstance;
		}


		// Theses Delegates are needed to keep multi-threading safe on Form controls
		delegate void RefreshGridDelegate();
		delegate void RefreshBatchessDelegate(string productString);
		delegate void WriteMessageDelegate(string somText, bool param);
		delegate void QueueUpdateDelegate(string somText); 

		private void CmdQueue_OnQueueChange( string cmdDescription )
		{
			this.statusBar1.Panels[0].Text = cmdDescription;
			this.statusBar1.Refresh();
		}
		private void CmdQueue_OnProgressChange( string progressDescription )
		{

			if(InvokeRequired)
			{
				Invoke(new QueueUpdateDelegate(CmdQueue_OnProgressChange), new Object[] {progressDescription} ); 
				return;
			}
			this.statusBar1.Panels[1].Text = progressDescription;
			this.statusBar1.Refresh();

		}

		// We want to dynamically create a Tab For Each Product in our Batch Setup
		public void ReBuildProductTabs()
		{
			if(InvokeRequired)
			{
				Invoke(new RefreshGridDelegate(ReBuildProductTabs),null); 
				return;
			}

			this.productTabsBuildInProgress = true;

			DataSet DSproducts = new DataSet("productsDS");

			try
			{
				this.aConnection.Open();

				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT DISTINCT ProductName FROM batches"
					, this.aConnection);

				this.adapter.Fill(DSproducts, "batches");
		
				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				ShowError("Error: " + e.Errors[0].Message);
			}

			DataTable t = DSproducts.Tables["batches"];
		
			if(t.Rows.Count == 0)
				Form1.ShowError("No Products ID's have been defined yet.");

			
			this.productTabPages = new System.Windows.Forms.TabPage[100];

			this.tabControl2.TabPages.Clear();

			int i = 0;
			foreach(DataRow r in t.Rows)
			{
				this.productTabPages[i] = new System.Windows.Forms.TabPage();

				this.productTabPages[i].SuspendLayout();

				
				this.productTabPages[i].Location = new System.Drawing.Point(4, 25);
				this.productTabPages[i].Name = r[t.Columns["ProductName"]].ToString();
				this.productTabPages[i].Size = new System.Drawing.Size(824, 107);
				this.productTabPages[i].TabIndex = i;
				this.productTabPages[i].Text = r[t.Columns["ProductName"]].ToString();

				// By Default the first tab is selected... so we only need to put the grid on that page.
				if(i == 0)
				{
					this.selectedProductName = this.productTabPages[i].Name;
					this.productTabPages[i].Controls.Add(this.gridBatches);
				}

				this.tabControl2.Controls.Add(this.productTabPages[i]);

				this.productTabPages[i].ResumeLayout(false);

				i++;
			}

			this.productTabsBuildInProgress = false;

		}

		public void RefreshGridBatches()
		{

			if(InvokeRequired)
			{
				Invoke(new RefreshGridDelegate(RefreshGridBatches),null); 
				return;
			}

			// I don't really have a good way to check if someone is scrolling within the Grid Frame
			// If we are scrolling while this function is called, an exception will get thrown and there is not a good way to catch/handle it
			// So if we find out the left mouse button is down at the time this method is called, we just return
			// The only exception is if we are switching tab for Product Batches.
			// ... in this case the mouse button may not have come up completely when clicking on a tab
			// ... so we check the productTabSwitchInProgress Flag to see if this is the operation being performed.
			if((Control.MouseButtons & MouseButtons.Left) != 0 && !this.productTabSwitchInProgress)
				return;


			DataSet DSbatches = new DataSet("batchesDS");
			this.BtnBatchesTracker.Clear();

			try
			{
				this.aConnection.Open();

				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT BCL.CategoryID, BCL.BatchID, BCL.ProjectCount, BCL.PageCount, B.ProductName, BC.CategoryName, B.FilenamePrefix, BCL.ID, B.ImpressionsPerHour FROM " +
					"(batchcategorylink AS BCL INNER JOIN batches AS B ON BCL.BatchID = B.ID) " +
					"INNER JOIN batchcategory AS BC ON BCL.CategoryID = BC.ID " +
					"WHERE B.ProductName='" + this.selectedProductName + "' " +
					"GROUP BY BCL.CategoryID, BCL.BatchID, BCL.ProjectCount, BCL.PageCount, B.ProductName, BC.CategoryName, B.FilenamePrefix, BCL.ID, B.ImpressionsPerHour"
					
					, this.aConnection);

				this.adapter.Fill(DSbatches, "batches");
		
				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				ShowError("Error: " + e.Errors[0].Message);
			}

			DataTable t = DSbatches.Tables["batches"];
		
			if(t.Rows.Count == 0)
				return;

			// Clear the table out before redrawing it each time.
			this.gridBatches.Redim(0,0);

			// Gread the header columns for the Grid Batches
			this.gridBatches.Columns.Insert(0);
			this.gridBatches.Columns.Insert(1);
			this.gridBatches.Columns.Insert(2);
			this.gridBatches.Columns.Insert(3);
			this.gridBatches.Columns.Insert(4);
			this.gridBatches.Columns.Insert(5);

			this.gridBatches.Columns[0].Width = 150;
			this.gridBatches.Columns[1].Width = 190;
			this.gridBatches.Columns[2].Width = 100;
			this.gridBatches.Columns[3].Width = 80;
			this.gridBatches.Columns[4].Width = 100;
			this.gridBatches.Columns[5].Width = 70;

			this.gridBatches.Rows.Insert(0);

			SourceGrid2.Cells.Real.Cell ColHeader1 = new SourceGrid2.Cells.Real.Cell("Product");
			ColHeader1.BackColor = Color.DarkGray;
			ColHeader1.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader2 = new SourceGrid2.Cells.Real.Cell("Description");
			ColHeader2.BackColor = Color.DarkGray;
			ColHeader2.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader3 = new SourceGrid2.Cells.Real.Cell("Project Count");
			ColHeader3.BackColor = Color.DarkGray;
			ColHeader3.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader4 = new SourceGrid2.Cells.Real.Cell("Impressions");
			ColHeader4.BackColor = Color.DarkGray;
			ColHeader4.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader5 = new SourceGrid2.Cells.Real.Cell("Est. Time");
			ColHeader5.BackColor = Color.DarkGray;
			ColHeader5.ForeColor = Color.White;

			this.gridBatches[0,0] = ColHeader1;
			this.gridBatches[0,1] = ColHeader2;
			this.gridBatches[0,2] = ColHeader3;
			this.gridBatches[0,3] = ColHeader4;
			this.gridBatches[0,4] = ColHeader5;

			// Define the behavior for the totals Row
			GridTotalsBarBehavior totals_Behavior = new GridTotalsBarBehavior();

			GridBatchColumnHeaderBehavior h_Behavior = new GridBatchColumnHeaderBehavior();
			this.gridBatches[0,0].Behaviors.Add(h_Behavior);
			this.gridBatches[0,1].Behaviors.Add(h_Behavior);
			this.gridBatches[0,2].Behaviors.Add(h_Behavior);
			this.gridBatches[0,3].Behaviors.Add(h_Behavior);
			this.gridBatches[0,4].Behaviors.Add(h_Behavior);


			// Holds totals for the details within the active Jobs.
			int TotalEsitmatedMinutes = 0;
			int TotaProjectCount = 0;
			int TotaImpressions = 0;


			// Now We want to loop through all of the combinations of Categories and Batches
	
			string LastCategoryID = "0";
			int CurrentGridRow = 1;
			int rowIndexCounter = 0;

			foreach(DataRow r in t.Rows)
			{

				rowIndexCounter++;

			
				// Everytime that we come accross a unique batch we need to draw a header with a column span
				if(r[t.Columns["CategoryID"]].ToString() != LastCategoryID.ToString())
				{
					LastCategoryID = r[t.Columns["CategoryID"]].ToString();


					this.gridBatches.Rows.Insert(CurrentGridRow);
					SourceGrid2.Cells.Real.Cell header_Cell = new SourceGrid2.Cells.Real.Cell( r[t.Columns["CategoryName"]] );
					header_Cell.BackColor = Color.SteelBlue;
					header_Cell.ForeColor = Color.White;
					header_Cell.TextAlignment = SourceLibrary.Drawing.ContentAlignment.MiddleCenter;
					this.gridBatches[CurrentGridRow,0] = header_Cell;
					this.gridBatches[CurrentGridRow,0].ColumnSpan = 6;

					GridBatchDividerCellBehavior div_Behavior = new GridBatchDividerCellBehavior();
					this.gridBatches[CurrentGridRow,0].Behaviors.Add(div_Behavior);

					CurrentGridRow++;
				}

				this.gridBatches.Rows.Insert(CurrentGridRow);
				this.gridBatches[CurrentGridRow,0] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["ProductName"]] );
				this.gridBatches[CurrentGridRow,1] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["FilenamePrefix"]] );
				this.gridBatches[CurrentGridRow,2] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["ProjectCount"]] );
				this.gridBatches[CurrentGridRow,3] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["PageCount"]] );

				// get the estimated impressions per minute for this batch.... and prevent a Divide by zero
				int impressionsPerHour = System.Int32.Parse(r[t.Columns["ImpressionsPerHour"]].ToString());
				int impressionsPerMinute = (impressionsPerHour / 60);
				if(impressionsPerMinute == 0)
						impressionsPerMinute = 1;

				TotaImpressions += System.Int32.Parse(r[t.Columns["PageCount"]].ToString());
				TotaProjectCount += System.Int32.Parse(r[t.Columns["ProjectCount"]].ToString());

				
				string estimatesMinutesForBatch = "";
				
				// If there are no projects then leave the time estimate blank... don't even show 0 minutes.
				// Otherwise add up the totals.
				if(r[t.Columns["ProjectCount"]].ToString() != "0")
				{
					int estimatedTime = System.Int32.Parse(r[t.Columns["PageCount"]].ToString()) / impressionsPerMinute;

					if(estimatedTime == 0)
						estimatedTime = 1;
					TotalEsitmatedMinutes += estimatedTime;

					estimatesMinutesForBatch = estimatedTime.ToString() + " Minutes";
				}


				this.gridBatches[CurrentGridRow,4] = new SourceGrid2.Cells.Real.Cell( estimatesMinutesForBatch );

				this.gridBatches[CurrentGridRow,5] = new SourceGrid2.Cells.Real.Button("Start", new SourceGrid2.PositionEventHandler(Btn_Batches_Start));

				GridBatchMainCellBehavior l_Behavior = new GridBatchMainCellBehavior();
				for (int c = 0; c < this.gridBatches.ColumnsCount; c++)
					this.gridBatches[CurrentGridRow,c].Behaviors.Add(l_Behavior);

				// Store the value of the Batch Category Link ID within a Hash Table.... so we can know the value when a button is clicked.
				this.BtnBatchesTracker.Add(CurrentGridRow.ToString(), r[t.Columns["ID"]].ToString());
		


				// If we are on the last row within our result set... of if the NEXT row will be switching categories 
				// ... then we want to show totals within that category.
				if(t.Rows.Count == rowIndexCounter || t.Rows[rowIndexCounter][t.Columns["CategoryID"]].ToString() != LastCategoryID.ToString())
				{
					CurrentGridRow++;

					int numHours = TotalEsitmatedMinutes / 60;
					int numMinutes = TotalEsitmatedMinutes - numHours * 60;
				
					this.gridBatches.Rows.Insert(CurrentGridRow);

					SourceGrid2.Cells.Real.Cell TotalProjectsCell = new SourceGrid2.Cells.Real.Cell( TotaProjectCount.ToString() );
					SourceGrid2.Cells.Real.Cell TotalImpressionsCell = new SourceGrid2.Cells.Real.Cell( TotaImpressions.ToString() );

					// Don't show "0 Hrs. 0 Min."
					string totalTimeDesc = "";
					if(numHours > 0)
							totalTimeDesc = numHours.ToString() + " Hrs. " + numMinutes.ToString() + " Min.";
					else if(numMinutes > 0)
						totalTimeDesc = numMinutes.ToString() + " Minutes";
						
					SourceGrid2.Cells.Real.Cell TotalTimeCell = new SourceGrid2.Cells.Real.Cell( totalTimeDesc );



					// Change the Background colors
					TotalProjectsCell.BackColor = Color.FromArgb(210, 210, 255);
					TotalImpressionsCell.BackColor = Color.FromArgb(210, 210, 255);
					TotalTimeCell.BackColor = Color.FromArgb(210, 210, 255);

					// Make the Font Bold
					TotalTimeCell.Font = new Font("Arial", 9, FontStyle.Bold);


					this.gridBatches[CurrentGridRow,0] = new SourceGrid2.Cells.Real.Cell( "" );
					this.gridBatches[CurrentGridRow,1] = new SourceGrid2.Cells.Real.Cell( "" );
					this.gridBatches[CurrentGridRow,2] = TotalProjectsCell;
					this.gridBatches[CurrentGridRow,3] = TotalImpressionsCell;
					this.gridBatches[CurrentGridRow,4] = TotalTimeCell;

					for(int i=0; i<5; i++)
						this.gridBatches[CurrentGridRow,i].Behaviors.Add(totals_Behavior);


					// Now that we created a Totals row... wipe out the totals counter so the next category group can start counting.
					TotalEsitmatedMinutes = 0;
					TotaProjectCount = 0;
					TotaImpressions = 0;
				}

				CurrentGridRow++;
			}

		}



		public void RefreshGridCommands()
		{

			if(InvokeRequired)
			{
				Invoke(new RefreshGridDelegate(RefreshGridCommands),null); 
				return;
			}

			// I don't really have a good way to check if someone is scrolling within the Grid Frame
			// If we are scrolling while this function is called, an exception will get thrown and there is not a good way to catch/handle it
			// So if we find out the left mouse button is down at the time this method is called, we just return
			if((Control.MouseButtons & MouseButtons.Left) != 0)
				return;


			DataSet DScommands = new DataSet("commandsDS");
			this.BtnJobTracker.Clear();

			try
			{

				this.aConnection.Open();

				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT COM.ID, COM.FileName, COM.ProjectCount, COM.PageCount, COM.Status, COM.ProjectList, BT.ImpressionsPerHour " + 
					"FROM commands AS COM INNER JOIN batches AS BT ON COM.BatchID = BT.ID " + 
					"ORDER BY COM.ID ASC"
					, this.aConnection);

				this.adapter.Fill(DScommands, "commands");
		
				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				ShowError("Error: " + e.Errors[0].Message);
			}

			DataTable t = DScommands.Tables["commands"];
		

			// Clear the table out before redrawing it each time.
			this.gridCommands.Redim(0,0);

			// Gread the header columns for the Grid Batches
			this.gridCommands.Columns.Insert(0);
			this.gridCommands.Columns.Insert(1);
			this.gridCommands.Columns.Insert(2);
			this.gridCommands.Columns.Insert(3);
			this.gridCommands.Columns.Insert(4);
			this.gridCommands.Columns.Insert(5);
			this.gridCommands.Columns.Insert(6);

			this.gridCommands.Columns[0].Width = 170;
			this.gridCommands.Columns[1].Width = 340;
			this.gridCommands.Columns[2].Width = 90;
			this.gridCommands.Columns[3].Width = 80;
			this.gridCommands.Columns[4].Width = 100;
			this.gridCommands.Columns[5].Width = 80;
			this.gridCommands.Columns[6].Width = 130;

			this.gridCommands.Rows.Insert(0);

			SourceGrid2.Cells.Real.Cell ColHeader1 = new SourceGrid2.Cells.Real.Cell("Status");
			ColHeader1.BackColor = Color.DarkGray;
			ColHeader1.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader2 = new SourceGrid2.Cells.Real.Cell("File Name");
			ColHeader2.BackColor = Color.DarkGray;
			ColHeader2.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader3 = new SourceGrid2.Cells.Real.Cell("Project Count");
			ColHeader3.BackColor = Color.DarkGray;
			ColHeader3.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader4 = new SourceGrid2.Cells.Real.Cell("Impressions");
			ColHeader4.BackColor = Color.DarkGray;
			ColHeader4.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader5 = new SourceGrid2.Cells.Real.Cell("Est. Time");
			ColHeader5.BackColor = Color.DarkGray;
			ColHeader5.ForeColor = Color.White;

			this.gridCommands[0,0] = ColHeader1;
			this.gridCommands[0,1] = ColHeader2;
			this.gridCommands[0,2] = ColHeader3;
			this.gridCommands[0,3] = ColHeader4;
			this.gridCommands[0,4] = ColHeader5;

			// Define the behavior for the totals Row
			GridTotalsBarBehavior totals_Behavior = new GridTotalsBarBehavior();
			GridCommandsMainCellBehavior row_Behavior = new GridCommandsMainCellBehavior();
			GridCommandsPDFfileCellBehavior pdfFile_Behavior = new GridCommandsPDFfileCellBehavior();


			GridBatchColumnHeaderBehavior h_Behavior = new GridBatchColumnHeaderBehavior();
			this.gridCommands[0,0].Behaviors.Add(h_Behavior);
			this.gridCommands[0,1].Behaviors.Add(h_Behavior);
			this.gridCommands[0,2].Behaviors.Add(h_Behavior);
			this.gridCommands[0,3].Behaviors.Add(h_Behavior);
			this.gridCommands[0,4].Behaviors.Add(h_Behavior);

			// Holds totals for the details within the active Jobs.
			int TotalEsitmatedMinutes = 0;
			int TotaProjectCount = 0;
			int TotaImpressions = 0;
			
			int CurrentGridRow = 1;

			if(t.Rows.Count == 0)
				return;

			foreach(DataRow r in t.Rows)
			{

				string StatusChar = r[t.Columns["Status"]].ToString();

				this.gridCommands.Rows.Insert(CurrentGridRow);
				this.gridCommands[CurrentGridRow,0] = new SourceGrid2.Cells.Real.Cell( Utility.GetStatusDescriptionLocal(StatusChar ) );
				this.gridCommands[CurrentGridRow,1] = new CellLinkForPDF(r[t.Columns["FileName"]].ToString(), typeof(Form1), r[t.Columns["FileName"]].ToString());
				this.gridCommands[CurrentGridRow,2] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["ProjectCount"]] );
				this.gridCommands[CurrentGridRow,3] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["PageCount"]] );
				
				TotaProjectCount += System.Int32.Parse(r[t.Columns["ProjectCount"]].ToString());
				TotaImpressions += System.Int32.Parse(r[t.Columns["PageCount"]].ToString());


				// get the estimated impressions per minute for this batch.... and prevent a Divide by zero
				int impressionsPerHour = System.Int32.Parse(r[t.Columns["ImpressionsPerHour"]].ToString());
				int impressionsPerMinute = (impressionsPerHour / 60);
				if(impressionsPerMinute == 0)
					impressionsPerMinute = 1;

				int estimatedTime = System.Int32.Parse(r[t.Columns["PageCount"]].ToString()) / impressionsPerMinute;
				if(estimatedTime == 0)
					estimatedTime = 1;
				TotalEsitmatedMinutes += estimatedTime;

				this.gridCommands[CurrentGridRow,4] = new SourceGrid2.Cells.Real.Cell( estimatedTime.ToString() + " Minutes" );
			

				// Add the main cell behavior to all columns except for the PDF file link.
				for (int c = 0; c < 5; c++)
				{
					if(c == 1)
						continue;

					this.gridCommands[CurrentGridRow,c].Behaviors.Add(row_Behavior);
				}

				// The PDF file behavior will listen for Mouse clicks on the cell and it will change the cursor to a hand.
				this.gridCommands[CurrentGridRow, 1].Behaviors.Add(pdfFile_Behavior);

				// Show different buttons depending on the job status
				if(StatusChar == "Q")
				{
					this.gridCommands[CurrentGridRow,5] = new SourceGrid2.Cells.Real.Button("Cancel", new SourceGrid2.PositionEventHandler(Btn_Jobs_Cancel));
					this.gridCommands[CurrentGridRow,6] = new SourceGrid2.Cells.Real.Button("Confirm Printing", new SourceGrid2.PositionEventHandler(Btn_Jobs_Confirm));

					this.gridCommands[CurrentGridRow,5].Behaviors.Add(row_Behavior);
					this.gridCommands[CurrentGridRow,6].Behaviors.Add(row_Behavior);
				}
				else if(StatusChar == "F" || StatusChar == "P" || StatusChar == "G" || StatusChar == "D" || StatusChar == "U" || StatusChar == "C")
				{
					this.gridCommands[CurrentGridRow,5] = new SourceGrid2.Cells.Real.Button("Cancel", new SourceGrid2.PositionEventHandler(Btn_Jobs_Cancel));
					this.gridCommands[CurrentGridRow,5].Behaviors.Add(row_Behavior);
				}


				// Store the value of the Job ID within a Hash Table.... so we can know the value when a button is clicked.
				this.BtnJobTracker.Add(CurrentGridRow.ToString(), r[t.Columns["ID"]].ToString());

				CurrentGridRow++;

			}


			// The last row will show a Sum of everything... only if there is more than 1 Active Jobs.
			if(CurrentGridRow > 2)
			{

				int numHours = TotalEsitmatedMinutes / 60;
				int numMinutes = TotalEsitmatedMinutes - numHours * 60;
				
				this.gridCommands.Rows.Insert(CurrentGridRow);

				SourceGrid2.Cells.Real.Cell TotalProjectsCell = new SourceGrid2.Cells.Real.Cell( TotaProjectCount.ToString() );
				SourceGrid2.Cells.Real.Cell TotalImpressionsCell = new SourceGrid2.Cells.Real.Cell( TotaImpressions.ToString() );


				// Don't show "0 Hrs. 0 Min."
				string totalTimeDesc = "";
				if(numHours > 0)
					totalTimeDesc = numHours.ToString() + " Hrs. " + numMinutes.ToString() + " Min.";
				else if(numMinutes > 0)
					totalTimeDesc = numMinutes.ToString() + " Minutes";

				SourceGrid2.Cells.Real.Cell TotalTimeCell = new SourceGrid2.Cells.Real.Cell( totalTimeDesc );



				// Change the Background colors
				TotalProjectsCell.BackColor = Color.FromArgb(210, 210, 255);
				TotalImpressionsCell.BackColor = Color.FromArgb(210, 210, 255);
				TotalTimeCell.BackColor = Color.FromArgb(210, 210, 255);

				// Make the Font Bold
				TotalTimeCell.Font = new Font("Arial", 9, FontStyle.Bold);


				this.gridCommands[CurrentGridRow,0] = new SourceGrid2.Cells.Real.Cell( "" );
				this.gridCommands[CurrentGridRow,1] = new SourceGrid2.Cells.Real.Cell( "" );
				this.gridCommands[CurrentGridRow,2] = TotalProjectsCell;
				this.gridCommands[CurrentGridRow,3] = TotalImpressionsCell;
				this.gridCommands[CurrentGridRow,4] = TotalTimeCell;

				for(int i=0; i<5; i++)
					this.gridCommands[CurrentGridRow,i].Behaviors.Add(totals_Behavior);
				
			}	

		}



		public void RefreshGridHistory()
		{
			if(InvokeRequired)
			{
				Invoke(new RefreshGridDelegate(RefreshGridHistory),null); 
				return;
			}

			// I don't really have a good way to check if someone is scrolling within the Grid Frame
			// If we are scrolling while this function is called, an exception will get thrown and there is not a good way to catch/handle it
			// So if we find out the left mouse button is down at the time this method is called, we keep just return
			if((Control.MouseButtons & MouseButtons.Left) != 0)
				return;

			DataSet DS_History = new DataSet("historyDS");

			try
			{

				this.aConnection.Open();

				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT * FROM history ORDER BY ID DESC"
					, this.aConnection);

				this.adapter.Fill(DS_History, "history");
		
				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				ShowError("Error: " + e.Errors[0].Message);
			}

			DataTable t = DS_History.Tables["history"];
		

			if(t.Rows.Count == 0)
				return;

			// Clear the table out before redrawing it each time.
			this.gridHistory.Redim(0,0);

			// Gread the header columns for the Grid Batches
			this.gridHistory.Columns.Insert(0);
			this.gridHistory.Columns.Insert(1);
			this.gridHistory.Columns.Insert(2);
			this.gridHistory.Columns.Insert(3);
			this.gridHistory.Columns.Insert(4);

			this.gridHistory.Columns[0].Width = 150;
			this.gridHistory.Columns[1].Width = 260;
			this.gridHistory.Columns[2].Width = 90;
			this.gridHistory.Columns[3].Width = 90;
			this.gridHistory.Columns[4].Width = 120;


			this.gridHistory.Rows.Insert(0);

			SourceGrid2.Cells.Real.Cell ColHeader1 = new SourceGrid2.Cells.Real.Cell("Date Completed");
			ColHeader1.BackColor = Color.DarkOliveGreen;
			ColHeader1.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader2 = new SourceGrid2.Cells.Real.Cell("File Name");
			ColHeader2.BackColor = Color.DarkOliveGreen;
			ColHeader2.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader3 = new SourceGrid2.Cells.Real.Cell("Page Count");
			ColHeader3.BackColor = Color.DarkOliveGreen;
			ColHeader3.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader4 = new SourceGrid2.Cells.Real.Cell("Project Count");
			ColHeader4.BackColor = Color.DarkOliveGreen;
			ColHeader4.ForeColor = Color.White;
			SourceGrid2.Cells.Real.Cell ColHeader5 = new SourceGrid2.Cells.Real.Cell("Operator");
			ColHeader5.BackColor = Color.DarkOliveGreen;
			ColHeader5.ForeColor = Color.White;


			this.gridHistory[0,0] = ColHeader1;
			this.gridHistory[0,1] = ColHeader2;
			this.gridHistory[0,2] = ColHeader3;
			this.gridHistory[0,3] = ColHeader4;
			this.gridHistory[0,4] = ColHeader5;

			GridBatchColumnHeaderBehavior h_Behavior = new GridBatchColumnHeaderBehavior();
			this.gridHistory[0,0].Behaviors.Add(h_Behavior);
			this.gridHistory[0,1].Behaviors.Add(h_Behavior);
			this.gridHistory[0,2].Behaviors.Add(h_Behavior);
			this.gridHistory[0,3].Behaviors.Add(h_Behavior);
			this.gridHistory[0,4].Behaviors.Add(h_Behavior);

		
			int CurrentGridRow = 1;

			foreach(DataRow r in t.Rows)
			{
				this.gridHistory.Rows.Insert(CurrentGridRow);
				this.gridHistory[CurrentGridRow,0] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["DateCompleted"]] );
				this.gridHistory[CurrentGridRow,1] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["FileName"]] );
				this.gridHistory[CurrentGridRow,2] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["PageCount"]] );
				this.gridHistory[CurrentGridRow,3] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["ProjectCount"]] );
				this.gridHistory[CurrentGridRow,4] = new SourceGrid2.Cells.Real.Cell( r[t.Columns["PersonsName"]] );

				GridHistoryMainCellBehavior hist_Behavior = new GridHistoryMainCellBehavior();
				for (int c = 0; c < 5; c++)
					this.gridHistory[CurrentGridRow,c].Behaviors.Add(hist_Behavior);

				CurrentGridRow++;
			}



			// We don't want the records to grow too large.  
			// If we have more records than the variable below, then delete them
			int MaxRecordsForHistory = 250;
			DataSet DS_MaxID = new DataSet("maxID");
			this.aConnection.Open();
			this.adapter.SelectCommand = new OleDbCommand(
				"SELECT MAX(ID) AS ID FROM history"
				, this.aConnection);
			this.adapter.Fill(DS_MaxID, "history");
			this.aConnection.Close();
			DataTable t2 = DS_MaxID.Tables["history"];
			DataRow r2 = t2.Rows[0];
			int maxHistoryID; 
			maxHistoryID = 0;
			try { maxHistoryID = System.Int32.Parse(r2[t2.Columns["ID"]].ToString()); } 
			catch(System.Exception e) { } 

			int BottomLimit = (maxHistoryID - MaxRecordsForHistory);
			if(BottomLimit > 0)
			{
				this.aConnection.Open();
				OleDbCommand ClearHistoryQry = new OleDbCommand(
					"DELETE FROM history WHERE ID < " + BottomLimit.ToString()
					, this.aConnection);
				ClearHistoryQry.ExecuteNonQuery();
				this.aConnection.Close();
			}

		}


		// This will start up a command to Confirm the print job on the server by changing the status to printed
		// It will also remove the PDF file from disk and it will move the job into the history
		public void PrintingConfirmed(string jbID, string personsName, string confirmationStringFromOperator, string passwordOverride)
		{

			// Get the project List from the local database.
			// We can figure out what the confirmation number is by adding up all of the Project IDs
			DataSet DS_PrintConfirmCheck = new DataSet("printConfirm");
			this.aConnection.Open();
			this.adapter.SelectCommand = new OleDbCommand(
				"SELECT ProjectList FROM commands WHERE ID = " + jbID
				, this.aConnection);
			this.adapter.Fill(DS_PrintConfirmCheck, "commands");
			this.aConnection.Close();
			DataTable t1 = DS_PrintConfirmCheck.Tables["commands"];
			DataRow r1 = t1.Rows[0];
			string projectListForConfirm = r1[t1.Columns["ProjectList"]].ToString();

			Regex r = new Regex("(\\|)"); // Split on pipe symbols.
			string[] projectListArr = r.Split(projectListForConfirm);

			// The printing confirmation number is the sum of all project ID's in the batch
			// Then we only take the last 3 digits
			int printingConfirmationNum = 0;

			foreach ( string projectNum in projectListArr )  
			{
				// Make sure to filter out all of the pipe symbols, blanks spaces, etc.
				Regex numCheck = new Regex(@"^\d+$"); 
				Match m = numCheck.Match(projectNum); 
				if (m.Success) 
					printingConfirmationNum += Int32.Parse(projectNum);
			}

			string confirmationString = printingConfirmationNum.ToString();
			if(confirmationString.Length > 3)
				confirmationString = confirmationString.Substring((confirmationString.Length - 3));

			if(confirmationStringFromOperator != confirmationString){

				// Hardcode the password override to be "pme" for now.
				if(passwordOverride != "pme")
					confirmationString = "Password Protected";
				
				Form1.ShowError("The confirmation number is not correct.\n\nThe expected value is: " + confirmationString);
				return;
			}

			// This command is reacting to the "Start Button" clicked within the batches window.  It will contact the server, mark the batch as queued, and start generating artwork
			CommandDetail CmdDt2 = new CommandDetail( this.CmdQueue, this.FrmSetup.appSetting, this);
			CmdDt2.command = CommandDetail.CommandType.ConfirmPrinting;
			CmdDt2.personsName = personsName;
			CmdDt2.jobNumber = jbID;
			this.CmdQueue.AddCommand(CmdDt2);
		}

		public class GridBatchMainCellBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{
			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}


			public override void OnMouseEnter(SourceGrid2.PositionEventArgs e)
			{
				base.OnMouseEnter (e);

				int gridBatchRow = e.Position.Row;

				// Get Singleton of Form object and call a method to change the background color on the row that the mouse if over
				Form1 parentForm = Form1.getForm1Object();
				parentForm.mouseOverBatchRow(gridBatchRow);

			}
			public override void OnMouseLeave(SourceGrid2.PositionEventArgs e)
			{
				base.OnMouseLeave (e);

				int gridBatchRow = e.Position.Row;

				// Get Singleton of Form object and call a method to change the background color on the row back to white that the mouse is leaving.
				Form1 parentForm = Form1.getForm1Object();
				parentForm.mouseLeaveBatchRow(gridBatchRow);
			}


		}

		public class GridHistoryMainCellBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{
			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}

		}

		public class GridCommandsMainCellBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{
			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}

			public override void OnMouseEnter(SourceGrid2.PositionEventArgs e)
			{
				base.OnMouseEnter (e);

				int gridCommandRow = e.Position.Row;

				// Get Singleton of Form object and call a method to change the background color on the row that the mouse if over
				Form1 parentForm = Form1.getForm1Object();
				parentForm.mouseOverCommandRow(gridCommandRow);

			}
			public override void OnMouseLeave(SourceGrid2.PositionEventArgs e)
			{
				base.OnMouseLeave (e);

				int gridCommandRow = e.Position.Row;

				// Get Singleton of Form object and call a method to change the background color on the row back to white that the mouse is leaving.
				Form1 parentForm = Form1.getForm1Object();
				parentForm.mouseLeaveCommandRow(gridCommandRow);
			}

		}

		public class GridCommandsPDFfileCellBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{
			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}


			public override void OnMouseEnter(SourceGrid2.PositionEventArgs e)
			{
				base.OnMouseEnter (e);

				int gridCommandRow = e.Position.Row;

				// Get Singleton of Form object and call a method to change the background color on the row that the mouse if over
				Form1 parentForm = Form1.getForm1Object();
				parentForm.mouseOverCommandRow(gridCommandRow);

			}
			public override void OnMouseLeave(SourceGrid2.PositionEventArgs e)
			{
				base.OnMouseLeave (e);

				int gridCommandRow = e.Position.Row;

				// Get Singleton of Form object and call a method to change the background color on the row back to white that the mouse is leaving.
				Form1 parentForm = Form1.getForm1Object();
				parentForm.mouseLeaveCommandRow(gridCommandRow);

			}

		}

		private class CellLinkForPDF : SourceGrid2.Cells.Real.Link
		{
			private Type m_FormType;
			private string fileNameOfPDF;
			public CellLinkForPDF(string p_Caption, Type p_FormType, string fileName):base(p_Caption)
			{
				m_FormType = p_FormType;
				fileNameOfPDF = fileName;
				this.Font = new Font("Arial", 9, FontStyle.Underline);
			}

			public override void OnClick(SourceGrid2.PositionEventArgs e)
			{
				base.OnClick (e);

				// Get Singleton of Form object and call a method to change the background color on the row back to white that the mouse is leaving.
				Form1 parentForm = Form1.getForm1Object();

				// Open the PDF file
				string pathToPDFfile = System.Environment.CurrentDirectory + "\\PDF_Files\\" + this.fileNameOfPDF;
				if (File.Exists(pathToPDFfile))
				{

					System.Diagnostics.Process process = new System.Diagnostics.Process(); 
					process.StartInfo.FileName = pathToPDFfile;
					process.StartInfo.CreateNoWindow = false; 
					process.StartInfo.UseShellExecute = true; 
					process.Start(); 
				}
				else
				{
					Form1.ShowError("Please wait for the PDF file to finish generating.");
				}
					
			}
		}


		public class GridBatchDividerCellBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{

			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}

		}
		public class GridBatchColumnHeaderBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{

			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}

		}
		public class GridTotalsBarBehavior : SourceGrid2.BehaviorModels.BehaviorModelGroup
		{

			public override void OnFocusEntering(SourceGrid2.PositionCancelEventArgs e)
			{
				e.Cancel = true;
			}

		}
		// If IsError is true... then the message will be displayed in Red Text
		public void WriteMessageToLog(string Msg, bool IsError)
		{

			if(InvokeRequired)
			{
				Invoke(new WriteMessageDelegate(WriteMessageToLog), new Object[] {Msg, IsError} ); 
				return;
			}


			// If the log file grows too big... we want to purge it out.
			if(this.richTextBox1.TextLength > 500000)
				this.richTextBox1.Text = "";

			// Put the cursor at the end of the file
			this.richTextBox1.SelectionStart = this.richTextBox1.Text.Length;

			this.richTextBox1.SelectionColor = Color.DarkGray;
			this.richTextBox1.SelectionFont = new Font("Courier New", 8, FontStyle.Bold); 
			this.richTextBox1.SelectedText = "\n" + DateTime.Now.ToString() + ">> ";

			if(IsError)
			{
				this.richTextBox1.SelectionColor = Color.DarkRed;
				this.richTextBox1.SelectionFont = new Font("Courier New", 9, FontStyle.Bold); 
			}
			else
			{
				this.richTextBox1.SelectionColor = Color.Black;
				this.richTextBox1.SelectionFont = new Font("Courier New", 9, FontStyle.Regular); 
			}

			this.richTextBox1.SelectedText = Msg;

			//Scroll to the bottom of the file
			this.richTextBox1.Select();
			this.richTextBox1.SelectionStart = this.richTextBox1.Text.Length;
			this.richTextBox1.ScrollToCaret();

			// We don't want the cursor to be blinking on the log file... so remove focus from the RichTextbox
			this.gridBatches.Select();
		}

		private void Btn_Batches_Start(object sender, SourceGrid2.PositionEventArgs e)
		{
			SourceGrid2.Cells.Real.Button btnCell = (SourceGrid2.Cells.Real.Button)e.Cell;

			
			// This command is reacting to the "Start Button" clicked within the batches window.  It will contact the server, mark the batch as queued, and start generating artwork
			CommandDetail CmdDt2 = new CommandDetail( this.CmdQueue, this.FrmSetup.appSetting, this);
			CmdDt2.command = CommandDetail.CommandType.StartBatch;
			CmdDt2.batchCategoryLinkID = this.BtnBatchesTracker[btnCell.Row.ToString()].ToString();
			this.CmdQueue.AddCommand(CmdDt2);

		}
		private void Btn_Jobs_Cancel(object sender, SourceGrid2.PositionEventArgs e)
		{
			SourceGrid2.Cells.Real.Button btnCell = (SourceGrid2.Cells.Real.Button)e.Cell;

			if(this.statusBarPanel1.Text != "Ready")
			{
				Form1.ShowError("Please wait for the current operation to finish before you attempt to cancel anything.");
				return;
			}

			if (MessageBox.Show("Are you sure that you want to cancel this job?","Confirm delete", MessageBoxButtons.YesNo) == DialogResult.Yes)
			{
			
				// One somebody cancels an active job... we need to change the status back to "proofed" for all project ID's
				CommandDetail CmdDt2 = new CommandDetail( this.CmdQueue, this.FrmSetup.appSetting, this);
				CmdDt2.command = CommandDetail.CommandType.CancelJob;
				CmdDt2.jobNumber = this.BtnJobTracker[btnCell.Row.ToString()].ToString();
				this.CmdQueue.AddCommand(CmdDt2);
			}
		}

		private void Btn_Jobs_Confirm(object sender, SourceGrid2.PositionEventArgs e)
		{
			SourceGrid2.Cells.Real.Button btnCell = (SourceGrid2.Cells.Real.Button)e.Cell;

			string theJobID = this.BtnJobTracker[btnCell.Row.ToString()].ToString();

			// We have to get the Page count out of the DB
			DataSet DS_PageCount = new DataSet("pageCNT");
			this.aConnection.Open();
			this.adapter.SelectCommand = new OleDbCommand(
				"SELECT PageCount FROM commands " +
				"WHERE ID=" + theJobID
				, this.aConnection);
			this.adapter.Fill(DS_PageCount, "commands");
			this.aConnection.Close();
			DataTable t2 = DS_PageCount.Tables["commands"];

			// In case the top grid window is not current with the database... let the operator know that an error has occured
			// Then refresh the top window.
			// it is rare that this could happen... but in case someone had their left mouse button held down while the grid was trying to update... it would not have happened
			if(t2.Rows.Count == 0)
			{
				Form1.ShowError("This batch is not available any more.  The top window frame will be refreshed.");
				this.RefreshGridCommands();
				return;
			}

			DataRow r2 = t2.Rows[0];
			string thePageCount = r2[t2.Columns["PageCount"]].ToString();
			
			// Show the pop-up form to the user requesting them to enter there name
			this.FrmConfirm.DisplayPrintCount(thePageCount);
			this.FrmConfirm.ShowInTaskbar = false;
			this.FrmConfirm.jobID = theJobID;
			this.FrmConfirm.ShowDialog();

		}
		static public void ShowError(string ErrorMessage)
		{
			MessageBox.Show(ErrorMessage, "An error has occured.");
		}


		/// <summary>
		/// Clean up any resources being used.
		/// </summary>
		protected override void Dispose( bool disposing )
		{
			if( disposing )
			{
				if (components != null) 
				{
					components.Dispose();
				}
			}
			base.Dispose( disposing );
		}

		#region Windows Form Designer generated code
		/// <summary>
		/// Required method for Designer support - do not modify
		/// the contents of this method with the code editor.
		/// </summary>
		private void InitializeComponent()
		{
			this.components = new System.ComponentModel.Container();
			System.Resources.ResourceManager resources = new System.Resources.ResourceManager(typeof(Form1));
			this.mainMenu1 = new System.Windows.Forms.MainMenu();
			this.menuItem1 = new System.Windows.Forms.MenuItem();
			this.menuItem2 = new System.Windows.Forms.MenuItem();
			this.menuItem3 = new System.Windows.Forms.MenuItem();
			this.menuItem4 = new System.Windows.Forms.MenuItem();
			this.menuItem10 = new System.Windows.Forms.MenuItem();
			this.menuItem8 = new System.Windows.Forms.MenuItem();
			this.menuItem9 = new System.Windows.Forms.MenuItem();
			this.contextMenu1 = new System.Windows.Forms.ContextMenu();
			this.menuItem6 = new System.Windows.Forms.MenuItem();
			this.menuItem7 = new System.Windows.Forms.MenuItem();
			this.panel1 = new System.Windows.Forms.Panel();
			this.splitter1 = new System.Windows.Forms.Splitter();
			this.panel2 = new System.Windows.Forms.Panel();
			this.panel3 = new System.Windows.Forms.Panel();
			this.tabControl1 = new System.Windows.Forms.TabControl();
			this.tabPage1 = new System.Windows.Forms.TabPage();
			this.gridCommands = new SourceGrid2.Grid();
			this.tabPage2 = new System.Windows.Forms.TabPage();
			this.gridHistory = new SourceGrid2.Grid();
			this.splitter3 = new System.Windows.Forms.Splitter();
			this.tabControl2 = new System.Windows.Forms.TabControl();
			this.splitter4 = new System.Windows.Forms.Splitter();
			this.richTextBox1 = new System.Windows.Forms.RichTextBox();
			this.splitter2 = new System.Windows.Forms.Splitter();
			this.statusBar1 = new System.Windows.Forms.StatusBar();
			this.statusBarPanel1 = new System.Windows.Forms.StatusBarPanel();
			this.statusBarPanel2 = new System.Windows.Forms.StatusBarPanel();
			this.gridBatches = new SourceGrid2.Grid();
			this.timer1 = new System.Windows.Forms.Timer(this.components);
			this.timerRefreshCounts = new System.Windows.Forms.Timer(this.components);
			this.notifyIcon1 = new System.Windows.Forms.NotifyIcon(this.components);
			this.menuItem5 = new System.Windows.Forms.MenuItem();
			this.menuItem11 = new System.Windows.Forms.MenuItem();
			this.panel2.SuspendLayout();
			this.panel3.SuspendLayout();
			this.tabControl1.SuspendLayout();
			this.tabPage1.SuspendLayout();
			this.tabPage2.SuspendLayout();
			((System.ComponentModel.ISupportInitialize)(this.statusBarPanel1)).BeginInit();
			((System.ComponentModel.ISupportInitialize)(this.statusBarPanel2)).BeginInit();
			this.SuspendLayout();
			// 
			// mainMenu1
			// 
			this.mainMenu1.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem1,
																					  this.menuItem3,
																					  this.menuItem8,
																					  this.menuItem5});
			// 
			// menuItem1
			// 
			this.menuItem1.Index = 0;
			this.menuItem1.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem2});
			this.menuItem1.Text = "File";
			// 
			// menuItem2
			// 
			this.menuItem2.Index = 0;
			this.menuItem2.Text = "Exit Application";
			this.menuItem2.Click += new System.EventHandler(this.menuItem2_Click);
			// 
			// menuItem3
			// 
			this.menuItem3.Index = 1;
			this.menuItem3.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem4,
																					  this.menuItem10});
			this.menuItem3.Text = "Edit";
			// 
			// menuItem4
			// 
			this.menuItem4.Index = 0;
			this.menuItem4.Text = "Batches";
			this.menuItem4.Click += new System.EventHandler(this.menuItem4_Click);
			// 
			// menuItem10
			// 
			this.menuItem10.Index = 1;
			this.menuItem10.Text = "Settings";
			this.menuItem10.Click += new System.EventHandler(this.menuItem10_Click);
			// 
			// menuItem8
			// 
			this.menuItem8.Index = 2;
			this.menuItem8.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem9});
			this.menuItem8.Text = "View";
			// 
			// menuItem9
			// 
			this.menuItem9.Index = 0;
			this.menuItem9.Text = "Refresh Project Count";
			this.menuItem9.Click += new System.EventHandler(this.menuItem9_Click);
			// 
			// contextMenu1
			// 
			this.contextMenu1.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																						 this.menuItem6,
																						 this.menuItem7});
			// 
			// menuItem6
			// 
			this.menuItem6.Index = 0;
			this.menuItem6.Text = "Generate Projects";
			// 
			// menuItem7
			// 
			this.menuItem7.Index = 1;
			this.menuItem7.Text = "Edit Batch";
			// 
			// panel1
			// 
			this.panel1.BackColor = System.Drawing.SystemColors.ControlLight;
			this.panel1.BackgroundImage = ((System.Drawing.Image)(resources.GetObject("panel1.BackgroundImage")));
			this.panel1.Dock = System.Windows.Forms.DockStyle.Top;
			this.panel1.Location = new System.Drawing.Point(0, 0);
			this.panel1.Name = "panel1";
			this.panel1.Size = new System.Drawing.Size(1142, 30);
			this.panel1.TabIndex = 0;
			// 
			// splitter1
			// 
			this.splitter1.Dock = System.Windows.Forms.DockStyle.Top;
			this.splitter1.Enabled = false;
			this.splitter1.Location = new System.Drawing.Point(0, 30);
			this.splitter1.Name = "splitter1";
			this.splitter1.Size = new System.Drawing.Size(1142, 3);
			this.splitter1.TabIndex = 1;
			this.splitter1.TabStop = false;
			// 
			// panel2
			// 
			this.panel2.Controls.Add(this.panel3);
			this.panel2.Controls.Add(this.splitter2);
			this.panel2.Controls.Add(this.statusBar1);
			this.panel2.Dock = System.Windows.Forms.DockStyle.Fill;
			this.panel2.Location = new System.Drawing.Point(0, 33);
			this.panel2.Name = "panel2";
			this.panel2.Size = new System.Drawing.Size(1142, 948);
			this.panel2.TabIndex = 2;
			// 
			// panel3
			// 
			this.panel3.AutoScrollMargin = new System.Drawing.Size(0, 45);
			this.panel3.Controls.Add(this.tabControl1);
			this.panel3.Controls.Add(this.splitter3);
			this.panel3.Controls.Add(this.tabControl2);
			this.panel3.Controls.Add(this.splitter4);
			this.panel3.Controls.Add(this.richTextBox1);
			this.panel3.Dock = System.Windows.Forms.DockStyle.Fill;
			this.panel3.DockPadding.All = 6;
			this.panel3.Location = new System.Drawing.Point(0, 0);
			this.panel3.Name = "panel3";
			this.panel3.Size = new System.Drawing.Size(1142, 924);
			this.panel3.TabIndex = 9;
			// 
			// tabControl1
			// 
			this.tabControl1.Controls.Add(this.tabPage1);
			this.tabControl1.Controls.Add(this.tabPage2);
			this.tabControl1.Dock = System.Windows.Forms.DockStyle.Fill;
			this.tabControl1.ItemSize = new System.Drawing.Size(82, 21);
			this.tabControl1.Location = new System.Drawing.Point(7, 6);
			this.tabControl1.Name = "tabControl1";
			this.tabControl1.SelectedIndex = 0;
			this.tabControl1.Size = new System.Drawing.Size(1128, 388);
			this.tabControl1.TabIndex = 4;
			// 
			// tabPage1
			// 
			this.tabPage1.Controls.Add(this.gridCommands);
			this.tabPage1.Location = new System.Drawing.Point(4, 25);
			this.tabPage1.Name = "tabPage1";
			this.tabPage1.Size = new System.Drawing.Size(1120, 359);
			this.tabPage1.TabIndex = 0;
			this.tabPage1.Text = "Active Jobs";
			// 
			// gridCommands
			// 
			this.gridCommands.AutoSizeMinHeight = 10;
			this.gridCommands.AutoSizeMinWidth = 10;
			this.gridCommands.AutoStretchColumnsToFitWidth = false;
			this.gridCommands.AutoStretchRowsToFitHeight = false;
			this.gridCommands.ContextMenuStyle = SourceGrid2.ContextMenuStyle.None;
			this.gridCommands.CustomSort = false;
			this.gridCommands.Dock = System.Windows.Forms.DockStyle.Fill;
			this.gridCommands.GridToolTipActive = true;
			this.gridCommands.Location = new System.Drawing.Point(0, 0);
			this.gridCommands.Name = "gridCommands";
			this.gridCommands.Size = new System.Drawing.Size(1120, 359);
			this.gridCommands.SpecialKeys = SourceGrid2.GridSpecialKeys.Default;
			this.gridCommands.TabIndex = 3;
			// 
			// tabPage2
			// 
			this.tabPage2.Controls.Add(this.gridHistory);
			this.tabPage2.Location = new System.Drawing.Point(4, 25);
			this.tabPage2.Name = "tabPage2";
			this.tabPage2.Size = new System.Drawing.Size(1120, 359);
			this.tabPage2.TabIndex = 1;
			this.tabPage2.Text = "History";
			this.tabPage2.Visible = false;
			// 
			// gridHistory
			// 
			this.gridHistory.AutoSizeMinHeight = 10;
			this.gridHistory.AutoSizeMinWidth = 10;
			this.gridHistory.AutoStretchColumnsToFitWidth = false;
			this.gridHistory.AutoStretchRowsToFitHeight = false;
			this.gridHistory.ContextMenuStyle = SourceGrid2.ContextMenuStyle.None;
			this.gridHistory.CustomSort = false;
			this.gridHistory.Dock = System.Windows.Forms.DockStyle.Fill;
			this.gridHistory.GridToolTipActive = true;
			this.gridHistory.Location = new System.Drawing.Point(0, 0);
			this.gridHistory.Name = "gridHistory";
			this.gridHistory.Size = new System.Drawing.Size(1120, 359);
			this.gridHistory.SpecialKeys = SourceGrid2.GridSpecialKeys.Default;
			this.gridHistory.TabIndex = 0;
			// 
			// splitter3
			// 
			this.splitter3.BackColor = System.Drawing.SystemColors.ControlDark;
			this.splitter3.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.splitter3.Location = new System.Drawing.Point(7, 394);
			this.splitter3.Name = "splitter3";
			this.splitter3.Size = new System.Drawing.Size(1128, 6);
			this.splitter3.TabIndex = 3;
			this.splitter3.TabStop = false;
			// 
			// tabControl2
			// 
			this.tabControl2.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.tabControl2.Location = new System.Drawing.Point(7, 400);
			this.tabControl2.Name = "tabControl2";
			this.tabControl2.SelectedIndex = 0;
			this.tabControl2.Size = new System.Drawing.Size(1128, 416);
			this.tabControl2.TabIndex = 5;
			this.tabControl2.SelectedIndexChanged += new System.EventHandler(this.tabControl2_SelectedIndexChanged);
			// 
			// splitter4
			// 
			this.splitter4.BackColor = System.Drawing.SystemColors.ControlDark;
			this.splitter4.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.splitter4.Location = new System.Drawing.Point(7, 816);
			this.splitter4.Name = "splitter4";
			this.splitter4.Size = new System.Drawing.Size(1128, 5);
			this.splitter4.TabIndex = 1;
			this.splitter4.TabStop = false;
			// 
			// richTextBox1
			// 
			this.richTextBox1.BackColor = System.Drawing.SystemColors.Info;
			this.richTextBox1.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.richTextBox1.Location = new System.Drawing.Point(7, 821);
			this.richTextBox1.Name = "richTextBox1";
			this.richTextBox1.ReadOnly = true;
			this.richTextBox1.Size = new System.Drawing.Size(1128, 97);
			this.richTextBox1.TabIndex = 0;
			this.richTextBox1.Text = "";
			// 
			// splitter2
			// 
			this.splitter2.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.splitter2.Location = new System.Drawing.Point(0, 924);
			this.splitter2.Name = "splitter2";
			this.splitter2.Size = new System.Drawing.Size(1142, 3);
			this.splitter2.TabIndex = 1;
			this.splitter2.TabStop = false;
			// 
			// statusBar1
			// 
			this.statusBar1.Location = new System.Drawing.Point(0, 927);
			this.statusBar1.Name = "statusBar1";
			this.statusBar1.Panels.AddRange(new System.Windows.Forms.StatusBarPanel[] {
																						  this.statusBarPanel1,
																						  this.statusBarPanel2});
			this.statusBar1.ShowPanels = true;
			this.statusBar1.Size = new System.Drawing.Size(1142, 21);
			this.statusBar1.TabIndex = 0;
			this.statusBar1.Text = "statusBar1";
			// 
			// statusBarPanel1
			// 
			this.statusBarPanel1.AutoSize = System.Windows.Forms.StatusBarPanelAutoSize.Spring;
			this.statusBarPanel1.Text = "Ready";
			this.statusBarPanel1.Width = 926;
			// 
			// statusBarPanel2
			// 
			this.statusBarPanel2.Width = 200;
			// 
			// gridBatches
			// 
			this.gridBatches.AutoSizeMinHeight = 10;
			this.gridBatches.AutoSizeMinWidth = 10;
			this.gridBatches.AutoStretchColumnsToFitWidth = false;
			this.gridBatches.AutoStretchRowsToFitHeight = false;
			this.gridBatches.ContextMenuStyle = SourceGrid2.ContextMenuStyle.None;
			this.gridBatches.CustomSort = false;
			this.gridBatches.Dock = System.Windows.Forms.DockStyle.Fill;
			this.gridBatches.GridToolTipActive = true;
			this.gridBatches.Location = new System.Drawing.Point(0, 0);
			this.gridBatches.Name = "gridBatches";
			this.gridBatches.Size = new System.Drawing.Size(824, 107);
			this.gridBatches.SpecialKeys = SourceGrid2.GridSpecialKeys.Default;
			this.gridBatches.TabIndex = 2;
			// 
			// timer1
			// 
			this.timer1.Enabled = true;
			this.timer1.Interval = 60000;
			this.timer1.Tick += new System.EventHandler(this.timer1_Tick);
			// 
			// timerRefreshCounts
			// 
			this.timerRefreshCounts.Enabled = true;
			this.timerRefreshCounts.Interval = 600000;
			this.timerRefreshCounts.Tick += new System.EventHandler(this.timerRefreshCounts_Tick);
			// 
			// notifyIcon1
			// 
			this.notifyIcon1.Icon = ((System.Drawing.Icon)(resources.GetObject("notifyIcon1.Icon")));
			this.notifyIcon1.Text = "PME Printing Press Pal";
			this.notifyIcon1.Visible = true;
			this.notifyIcon1.DoubleClick += new System.EventHandler(this.notifyIcon1_DoubleClick);
			// 
			// menuItem5
			// 
			this.menuItem5.Index = 3;
			this.menuItem5.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem11});
			this.menuItem5.Text = "Tools";
			// 
			// menuItem11
			// 
			this.menuItem11.Index = 0;
			this.menuItem11.Text = "Search";
			this.menuItem11.Click += new System.EventHandler(this.menuItem11_Click);
			// 
			// Form1
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(6, 15);
			this.ClientSize = new System.Drawing.Size(1142, 981);
			this.Controls.Add(this.panel2);
			this.Controls.Add(this.splitter1);
			this.Controls.Add(this.panel1);
			this.Icon = ((System.Drawing.Icon)(resources.GetObject("$this.Icon")));
			this.MaximizeBox = false;
			this.Menu = this.mainMenu1;
			this.Name = "Form1";
			this.Text = "PME Printing Press Pal";
			this.Resize += new System.EventHandler(this.Form1_Resize);
			this.Closing += new System.ComponentModel.CancelEventHandler(this.Form1_Closing);
			this.panel2.ResumeLayout(false);
			this.panel3.ResumeLayout(false);
			this.tabControl1.ResumeLayout(false);
			this.tabPage1.ResumeLayout(false);
			this.tabPage2.ResumeLayout(false);
			((System.ComponentModel.ISupportInitialize)(this.statusBarPanel1)).EndInit();
			((System.ComponentModel.ISupportInitialize)(this.statusBarPanel2)).EndInit();
			this.ResumeLayout(false);

		}
		#endregion

		/// <summary>
		/// The main entry point for the application.
		/// </summary>
		[STAThread]
		static void Main() 
		{
			try
			{
				Application.Run(new Form1());
			}
			catch(System.ObjectDisposedException)
			{
			}

		}

		private void menuItem10_Click(object sender, System.EventArgs e)
		{
			this.FrmSetup.ShowDialog();
		}

		private void menuItem9_Click(object sender, System.EventArgs e)
		{
			// contact the server and refresh our project counts that are "proofed"
			CommandDetail CmdDt = new CommandDetail( this.CmdQueue, this.FrmSetup.appSetting, this);
			CmdDt.command = CommandDetail.CommandType.RefreshProjectCount;
			this.CmdQueue.AddCommand(CmdDt);

			// Refresh the grid in the top window pain (as well as the grid history) with information from our local DB
			this.RefreshGridCommands();
			this.RefreshGridHistory();
		}

		// Will build a request for every unique batch/category combination in the sytem.
		// Will contact the server and fill our local database with the new project counts
		// Pass in a Product ID if you only want to restrict the Refresh to those Products... otherwise pass in a blank string for all products.
		// Restricting to a product ID can be useful when starting or canceling a batch... in which case you don't need to calculate the counts for every other product in the system.
		public void RefreshBatchCounts(string productIDlimit)
		{


			if(InvokeRequired)
			{
				Invoke(new RefreshBatchessDelegate(RefreshBatchCounts), new Object[] {productIDlimit} ); 
				return;
			}
			
			DataSet DS_batchCategory = new DataSet("batchCat_DS");
			try
			{

				// Clear out any old counts and set them to 0. 
				// Just in case of a internet connection failure... if we can't get an update we don't want to show that any projects are ready
				this.aConnection.Open();
				OleDbCommand UpdateQry = new OleDbCommand(
					"UPDATE batchcategorylink SET ProjectCount=0"
					, this.aConnection);
				UpdateQry.ExecuteNonQuery();
				this.aConnection.Close();

				// We may be limiting our Batch Count request to a specific ProductID.
				string productIDSQLrestrict = "";
				if(productIDlimit != "")
						productIDSQLrestrict = " WHERE B.ProductID=" + productIDlimit;


				this.aConnection.Open();
				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT BCL.ID, B.FilenamePrefix, B.ProductID, B.ProjectOptions, B.ProjectCap, B.MaxPages, BC.ShippingMethods, BC.DueToday, BC.Urgent, BC.PDFprofile, BC.MinProjectQuantity, BC.MaxProjectQuantity FROM " +
					"(batchcategorylink AS BCL INNER JOIN batches AS B ON BCL.BatchID = B.ID) " +
					"INNER JOIN batchcategory AS BC ON BCL.CategoryID = BC.ID " + productIDSQLrestrict
					, this.aConnection);


				this.adapter.Fill(DS_batchCategory, "batches");

				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				ShowError("Error: " + e.Errors[0].Message);
			}

			DataTable t = DS_batchCategory.Tables["batches"];
		
			if(t.Rows.Count == 0)
				return;

			// Now we are going to contact the server through the API.
			ServerAPI srvAPI = new ServerAPI(this.FrmSetup.appSetting);
			srvAPI.command = "get_project_count";


			foreach(DataRow r in t.Rows)
			{
				// We want to get counts of multipe batches... all with 1 request to the webserver
				ProjectCountRequest pcr = new ProjectCountRequest();
				pcr.batchID = r[t.Columns["ID"]].ToString();
				pcr.productID = r[t.Columns["ProductID"]].ToString();
				pcr.projectOptions = r[t.Columns["ProjectOptions"]].ToString();
				pcr.shippingMethods = r[t.Columns["ShippingMethods"]].ToString();
				pcr.pdfProfile = r[t.Columns["PDFprofile"]].ToString();
				pcr.dueToday = (bool) r[t.Columns["DueToday"]];
				pcr.urgent = (bool) r[t.Columns["Urgent"]];
				pcr.statusOnServer = "P"; // We only want to count "proofed" orders.
				pcr.projectCap = r[t.Columns["ProjectCap"]].ToString();
				pcr.maxPages = r[t.Columns["MaxPages"]].ToString();
				pcr.maxProjectQuantity = r[t.Columns["MaxProjectQuantity"]].ToString();
				pcr.minProjectQuantity = r[t.Columns["MinProjectQuantity"]].ToString();

				srvAPI.addBatchCountRequest(pcr); 
			}


			srvAPI.FireRequest();

			if(srvAPI.error)
			{
				this.WriteMessageToLog(srvAPI.errorDescription, true);
			}


			Hashtable ProjCnt = srvAPI.projectCountHash;
			Hashtable PageCnt = srvAPI.pageCountHash;

			foreach (string btchID in ProjCnt.Keys) 
			{

				// Update the ProjectCount for every BatchCategory Link
				this.aConnection.Open();
				OleDbCommand UpdateQry = new OleDbCommand(
					"UPDATE batchcategorylink SET ProjectCount=" + ProjCnt[btchID] + ", PageCount=" + PageCnt[btchID] + " WHERE ID=" + btchID
					, this.aConnection);
				UpdateQry.ExecuteNonQuery();
				this.aConnection.Close();

			}

			// Go out to the database and refresh the grid... now that data may have changed
			this.RefreshGridBatches();


		}

		public void mouseOverCommandRow(int rowNumber)
		{
			// When we move our mouse over a cell... an event if fired which in-turn calls this method to highlight the row (by row number).
			// Loop through all of the columns we want the background color to change for.
			for(int i=0; i<5; i++)
			{
				SourceGrid2.Cells.Real.Cell changeCellColorObj = (SourceGrid2.Cells.Real.Cell) this.gridCommands.GetCell(rowNumber, i);
				changeCellColorObj.BackColor = Color.FromArgb(255, 210, 220);
			}
		}

		public void mouseLeaveCommandRow(int rowNumber)
		{
		
			for(int j=0; j<5; j++)
			{
				SourceGrid2.Cells.Real.Cell changeCellColorObj = (SourceGrid2.Cells.Real.Cell) this.gridCommands.GetCell(rowNumber, j);
				changeCellColorObj.BackColor = Color.White;
			}
	
		}

		public void mouseOverBatchRow(int rowNumber)
		{
			// When we move our mouse over a cell... an event if fired which in-turn calls this method to highlight the row (by row number).
			// Loop through all of the columns we want the background color to change for.
			for(int i=0; i<5; i++)
			{
				SourceGrid2.Cells.Real.Cell changeCellColorObj = (SourceGrid2.Cells.Real.Cell) this.gridBatches.GetCell(rowNumber, i);
				changeCellColorObj.BackColor = Color.FromArgb(255, 210, 220);
			}
		}

		public void mouseLeaveBatchRow(int rowNumber)
		{
		
			for(int j=0; j<5; j++)
			{
				SourceGrid2.Cells.Real.Cell changeCellColorObj = (SourceGrid2.Cells.Real.Cell) this.gridBatches.GetCell(rowNumber, j);
				changeCellColorObj.BackColor = Color.White;
			}
	
		}
		

		private void menuItem4_Click(object sender, System.EventArgs e)
		{
			this.FrmEditBatches = new EditBatchesForm();
			this.AddOwnedForm(this.FrmEditBatches);
			this.FrmEditBatches.ShowDialog();
		}

		private void timer1_Tick(object sender, System.EventArgs e)
		{
			// We want to search the database every 10 mintues
			if(System.Int32.Parse(DateTime.Now.Minute.ToString()) % 10 != 0)
				return;

			// We need to get the time stamp as of right now in the format "03:34" ... Always 4 digits
			string theHour = DateTime.Now.Hour.ToString();
			if(theHour.Length == 1)
				theHour = "0" + theHour;

			string theMinutes = DateTime.Now.Minute.ToString();
			if(theMinutes.Length == 1)
				theMinutes = "0" + theMinutes;

			string searchTime = theHour + ":" + theMinutes;

			string qryDay = "";
			switch( DateTime.Now.DayOfWeek )
			{
				case System.DayOfWeek.Monday:
					qryDay = "AutoMon";
					break;
				case System.DayOfWeek.Tuesday:
					qryDay = "AutoTue";
					break;
				case System.DayOfWeek.Wednesday:
					qryDay = "AutoWed";
					break;
				case System.DayOfWeek.Thursday:
					qryDay = "AutoThu";
					break;
				case System.DayOfWeek.Friday:
					qryDay = "AutoFri";
					break;
				case System.DayOfWeek.Saturday:
					qryDay = "AutoSat";
					break;
				case System.DayOfWeek.Sunday:
					qryDay = "AutoSun";
					break;
				default:
					break;
			}

			string qrySting = "SELECT BCL.ID AS ID FROM " +
								"(batchcategorylink AS BCL INNER JOIN batches AS B ON BCL.BatchID = B.ID) " +
								"INNER JOIN batchcategory AS BC ON BCL.CategoryID = BC.ID " +
								"WHERE BC." + qryDay + "=True AND BC.AutoTime='" + searchTime + "'";


			DataSet DS_AutoTime = new DataSet("AutoTimer");
			this.aConnection.Open();
			this.adapter.SelectCommand = new OleDbCommand(qrySting, this.aConnection);
			this.adapter.Fill(DS_AutoTime, "batchcategorylink");
			this.aConnection.Close();
			DataTable t8 = DS_AutoTime.Tables["batchcategorylink"];


			foreach(DataRow r in t8.Rows)
			{
				// Start up the batch
				CommandDetail CmdDt3 = new CommandDetail( this.CmdQueue, this.FrmSetup.appSetting, this);
				CmdDt3.command = CommandDetail.CommandType.StartBatch;
				CmdDt3.batchCategoryLinkID = r[t8.Columns["ID"]].ToString();
				this.CmdQueue.AddCommand(CmdDt3);
			}
		}

		// Automatically refreshes every grids on an interval
		private void timerRefreshCounts_Tick(object sender, System.EventArgs e)
		{
			CommandDetail CmdDt = new CommandDetail( this.CmdQueue, this.FrmSetup.appSetting, this);
			CmdDt.command = CommandDetail.CommandType.RefreshProjectCount;
			this.CmdQueue.AddCommand(CmdDt);

			// Refresh the grid in the top window pain (as well as the grid history) with information from our local DB
			// Normally this shouldn't be necessary.  The grids should always be current with the database but...
			// Sometimes if the user is scrolling within one of the frames while a GridUpdate method is invoked we can't update the grid because of a weird exception
			// So just be be totally safe, we will update the grids from our local DB along with the project counts from the server on an interval.
			this.RefreshGridCommands();
			this.RefreshGridHistory();
		}

		private void Form1_Resize(object sender, System.EventArgs e)
		{
			if (FormWindowState.Minimized == WindowState)
				Hide();
		}

		private void notifyIcon1_DoubleClick(object sender, System.EventArgs e)
		{
			Show();
			WindowState = FormWindowState.Normal;
		}

		private void menuItem2_Click(object sender, System.EventArgs e)
		{
			this.shouldExitApplication = true;
			this.Close();
		}

		// If someone tries to close the application .... just minimize it and leave it in the System Tray.
		// Unless of couse they explicitly exit the application
		private void Form1_Closing(object sender, System.ComponentModel.CancelEventArgs e)
		{
			// If the form is already minimized and something is trying to close this application, then let it.
			// It could be the computer trying to reboot.
			if(WindowState == FormWindowState.Minimized)
				return;

			if(!this.shouldExitApplication)
			{
				e.Cancel = true;
				WindowState = FormWindowState.Minimized;
			}
		}

		// When the Tab Control is Changed, we want to Put the Grid Object into the Selected Tab page
		// Then we want to refresh the Grid.  We are going to tell the grid what product we have chosen
		private void tabControl2_SelectedIndexChanged(object sender, System.EventArgs e)
		{

			// If we make a change to out Batch setup and then hit "Save" it will remove all of the tabs and then build new ones
			// This process of clearing out the tabs will fire off this event and it will fail.
			if(!this.productTabsBuildInProgress)
			{
				System.Windows.Forms.TabControl tabObj = (System.Windows.Forms.TabControl) sender;

				tabObj.TabPages[tabObj.SelectedIndex].Controls.Add(this.gridBatches);

				this.selectedProductName = tabObj.SelectedTab.Name;
			
				this.productTabSwitchInProgress = true;
				this.RefreshGridBatches();
				this.productTabSwitchInProgress = false;
			}

		}

		private void menuItem11_Click(object sender, System.EventArgs e)
		{
			this.SearchProjectFrm.ShowDialog();
		}

	


	}
}
