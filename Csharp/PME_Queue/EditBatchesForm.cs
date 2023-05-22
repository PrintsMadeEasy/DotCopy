using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;
using System.Data;
using System.Data.OleDb;
using System.Text.RegularExpressions;

namespace PME_Queue
{
	/// <summary>
	/// Summary description for EditBatchesForm.
	/// </summary>
	public class EditBatchesForm : System.Windows.Forms.Form
	{
		private System.Windows.Forms.Panel panel1;
		private System.Windows.Forms.Splitter splitter1;
		private System.Windows.Forms.Panel panel2;
		private System.Windows.Forms.Splitter splitter2;
		private System.Windows.Forms.Splitter splitter3;

		/// <summary>
		/// Required designer variable.
		/// </summary>
		private System.ComponentModel.Container components = null;

		private OleDbConnection aConnection;
		public System.Windows.Forms.DataGrid dataGridLink;
		public System.Windows.Forms.DataGrid dataGridCats;
		public System.Windows.Forms.DataGrid dataGridBats;

		private System.Windows.Forms.Button cancelBtn;
		private System.Windows.Forms.Button saveBtn; 

		private DataSet DataSets_All;

		private System.Windows.Forms.Label label1;
		private System.Windows.Forms.Button btnApply;

		private OleDbDataAdapter adapter;



		//OleDbParameter workParam = null; 


		public EditBatchesForm()
		{

			//
			// Required for Windows Form Designer support
			//
			InitializeComponent();

			//
			// TODO: Add any constructor code after InitializeComponent call
			//

			//create the database connection
			this.aConnection = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=PME_QueueDB.mdb");
			this.adapter = new OleDbDataAdapter();

			this.DataSets_All = new DataSet("AllTables");


			this.CreateSelectSQL();


			this.dataGridBats.SetDataBinding(this.DataSets_All, "batches");
			this.dataGridCats.SetDataBinding(this.DataSets_All, "batchcategory");
			this.dataGridLink.SetDataBinding(this.DataSets_All, "batchcategorylink");

			// For validating input
			this.DataSets_All.Tables["batchcategory"].ColumnChanging += new DataColumnChangeEventHandler(this.Category_ColumnChanging);

		}

		private void CreateSelectSQL()
		{
			this.DataSets_All.Clear();

			DataSet TempDataSet = new DataSet("temp");
			DataTable TempTable = new DataTable("temp");

			this.aConnection.Open();

			// For the Batches
			this.adapter.SelectCommand = new OleDbCommand(
				"SELECT * FROM batches"
				, this.aConnection);
			this.adapter.Fill(TempDataSet, "batches");
			TempTable = TempDataSet.Tables["batches"];
			TempTable.Columns[0].ReadOnly = true;
			this.DataSets_All.Tables.Add(TempTable.Copy());

			// For the Categories
			this.adapter.SelectCommand = new OleDbCommand(
				"SELECT * FROM batchcategory"
				, this.aConnection);
			this.adapter.Fill(TempDataSet, "batchcategory");
			TempTable = TempDataSet.Tables["batchcategory"];
			TempTable.Columns[0].ReadOnly = true;
			// Make sure that the grid will not accept any (null) states in the checkbox.  Don't make the ID field "not Null" or we can't insert a new record
			for(int i=1; i< TempTable.Columns.Count; i++)
				TempTable.Columns[i].AllowDBNull = false;
			this.DataSets_All.Tables.Add(TempTable.Copy());


			// For the batch/category links
			this.adapter.SelectCommand = new OleDbCommand(
				"SELECT ID, BatchID, CategoryID  FROM batchcategorylink"
				, this.aConnection);
			this.adapter.Fill(TempDataSet, "batchcategorylink");
			TempTable = TempDataSet.Tables["batchcategorylink"];
			TempTable.Columns[0].ReadOnly = true;
			this.DataSets_All.Tables.Add(TempTable.Copy());

			this.aConnection.Close();

		}



		private void UpdateDBfromGrids(string viewType)
		{

			string qryString;

			DataRowState rowState;
			DataRowVersion rowVersion;

			// Deleted Rows do not have access to the row information (becuase it was deleted).  We need to get the original version out of the row
			if(viewType == "update")
			{
				rowState = DataRowState.Modified;
				rowVersion = DataRowVersion.Current;
			}
			else if(viewType == "insert")
			{
				rowState = DataRowState.Added;
				rowVersion = DataRowVersion.Current;
			}
			else if(viewType == "delete")
			{
				rowState = DataRowState.Deleted;
				rowVersion = DataRowVersion.Original;
			}
			else
			{
				Form1.ShowError("Illegal view type called in the method UpdateDBfromGrids");
				return;
			}

			try
			{
				// -------  For the Batch Category Link -----------
				DataTable t5 = this.DataSets_All.Tables["batchcategorylink"].GetChanges(rowState);
				if(t5 != null)
				{
					foreach(DataRow r5 in t5.Rows)
					{
						if(viewType == "update")
						{
							qryString = "UPDATE batchcategorylink SET " + 
								"BatchID=" + r5[t5.Columns["BatchID"], rowVersion].ToString() + ", CategoryID=" + r5[t5.Columns["CategoryID"], rowVersion].ToString() +
								" WHERE ID=" + r5[t5.Columns["ID"], rowVersion].ToString();
						}
						else if(viewType == "insert")
						{
							qryString = "INSERT INTO batchcategorylink (BatchID, CategoryID, ProjectCount) " +
								"values (" + r5[t5.Columns["BatchID"], rowVersion].ToString() + ", " + r5[t5.Columns["CategoryID"], rowVersion].ToString() +", 0 )";
						}
						else if(viewType == "delete")
						{
							qryString = "DELETE FROM batchcategorylink WHERE ID=" + r5[t5.Columns["ID"], rowVersion].ToString();
						}
						else
							return;
	

						this.aConnection.Open();
						OleDbCommand Qry5 = new OleDbCommand(qryString, this.aConnection);
						Qry5.ExecuteNonQuery();
						this.aConnection.Close();
					}
				}



				// -------  For the Categories -----------
				DataTable t6 = this.DataSets_All.Tables["batchcategory"].GetChanges(rowState);
				if(t6 != null)
				{
					foreach(DataRow r6 in t6.Rows)
					{
						string catID = r6[t6.Columns["ID"], rowVersion].ToString();
						string catName = r6[t6.Columns["CategoryName"], rowVersion].ToString();
						string shipMeth = r6[t6.Columns["ShippingMethods"], rowVersion].ToString();
						string pdfProf = r6[t6.Columns["PDFprofile"], rowVersion].ToString();
						string dueTod = r6[t6.Columns["DueToday"], rowVersion].ToString();
						string urgent = r6[t6.Columns["Urgent"], rowVersion].ToString();
						string sysCom = r6[t6.Columns["SystemCommand"], rowVersion].ToString();
						string minProjQuan = r6[t6.Columns["MinProjectQuantity"], rowVersion].ToString();
						string maxProjQuan = r6[t6.Columns["MaxProjectQuantity"], rowVersion].ToString();
						string sun = r6[t6.Columns["AutoSun"], rowVersion].ToString();
						string mon = r6[t6.Columns["AutoMon"], rowVersion].ToString();
						string tue = r6[t6.Columns["AutoTue"], rowVersion].ToString();
						string wed = r6[t6.Columns["AutoWed"], rowVersion].ToString();
						string thu = r6[t6.Columns["AutoThu"], rowVersion].ToString();
						string fri = r6[t6.Columns["AutoFri"], rowVersion].ToString();
						string sat = r6[t6.Columns["AutoSat"], rowVersion].ToString();
						string time = r6[t6.Columns["AutoTime"], rowVersion].ToString();

						// Get rid of single quotes so that it won't interfere with the insert query
						catName = Regex.Replace( catName, "'",  "");
						shipMeth = Regex.Replace( shipMeth, "'",  "");
						pdfProf = Regex.Replace( pdfProf, "'",  "");
						sysCom = Regex.Replace( sysCom, "'",  "");

						if(viewType == "update")
						{
							qryString = "UPDATE batchcategory SET CategoryName='" + catName + "', ShippingMethods='" + shipMeth + "', AutoTime='" + time + "', " +
								"PDFprofile='" + pdfProf + "', DueToday=" + dueTod + ", Urgent=" + urgent + ", MinProjectQuantity='" + minProjQuan + "', MaxProjectQuantity='" + maxProjQuan + "', SystemCommand='" + sysCom + "', " +
								"AutoSun=" + sun + ", AutoMon=" + mon + ", AutoTue=" + tue + ", AutoWed=" + wed + ", AutoThu=" + thu + ", AutoFri=" + fri + ", AutoSat=" + sat + " " +
								"WHERE ID=" + catID;
						}
						else if(viewType == "insert")
						{
							qryString = "INSERT INTO batchcategory (CategoryName, ShippingMethods, PDFprofile, SystemCommand, MinProjectQuantity, MaxProjectQuantity, AutoTime, DueToday, Urgent, AutoSun, AutoMon, AutoTue, AutoWed, AutoThu, AutoFri, AutoSat) " +
								"values ('"+catName+"', '"+shipMeth+"', '"+pdfProf+"', '"+sysCom+"', '"+minProjQuan+"', '"+maxProjQuan+"', '"+time+"', "+dueTod+", "+urgent+", "+sun+", "+mon+", "+tue+", "+wed+", "+thu+", "+fri+", "+sat+" )";
						}
						else if(viewType == "delete")
						{
							qryString = "DELETE FROM batchcategory WHERE ID=" + catID;
						}
						else
							return;

						this.aConnection.Open();
						OleDbCommand UpdateQry6 = new OleDbCommand(qryString, this.aConnection);
						UpdateQry6.ExecuteNonQuery();
						this.aConnection.Close();
					}
				}



				// -------  For the Batches -----------
				DataTable t7 = this.DataSets_All.Tables["batches"].GetChanges(rowState);
				if(t7 != null)
				{
					foreach(DataRow r7 in t7.Rows)
					{
						string batchID = r7[t7.Columns["ID"], rowVersion].ToString();
						string prodID = r7[t7.Columns["ProductID"], rowVersion].ToString();
						string prodName = r7[t7.Columns["ProductName"], rowVersion].ToString();
						string filePrefix = r7[t7.Columns["FilenamePrefix"], rowVersion].ToString();
						string projOptions = r7[t7.Columns["ProjectOptions"], rowVersion].ToString();
						string queueName = r7[t7.Columns["QueueNameOnPress"], rowVersion].ToString();
						string projCap = r7[t7.Columns["ProjectCap"], rowVersion].ToString();
						string maxPgs = r7[t7.Columns["MaxPages"], rowVersion].ToString();
						string imprPerHour = r7[t7.Columns["ImpressionsPerHour"], rowVersion].ToString();

						// Get rid of single quotes so that it won't interfere with the insert query
						prodName = Regex.Replace( prodName, "'",  "");
						filePrefix = Regex.Replace( filePrefix, "'",  "");
						projOptions = Regex.Replace( projOptions, "'",  "");
						queueName = Regex.Replace( queueName, "'",  "");

						// If ProjecCap or MaxPgs are not set to a number... set them to Zero.  That means to use the global settings for Max Page count
						if(projCap == "")
							projCap = "0";
						if(maxPgs == "")
							maxPgs = "0";
						if(prodID == "")
							prodID = "0";

						if(viewType == "update")
						{
							qryString = "UPDATE batches SET ProductID=" + prodID + ", ProductName='" + prodName + "', FilenamePrefix='" + filePrefix + "', " +
								"ProjectOptions='" + projOptions + "', QueueNameOnPress='" + queueName + "', " +
								"ProjectCap=" + projCap + ", MaxPages=" + maxPgs + " " + ", ImpressionsPerHour=" + imprPerHour + " " +
								"WHERE ID=" + batchID;
;
						}
						else if(viewType == "insert")
						{
							qryString = "INSERT INTO batches (ProductID, ProductName, FilenamePrefix, ProjectOptions, QueueNameOnPress, ProjectCap, MaxPages, ImpressionsPerHour) " +
								"values ("+prodID+", '"+prodName+"', '"+filePrefix+"', '"+projOptions+"', '"+queueName+"', "+projCap+", "+maxPgs+", "+imprPerHour+" )";
						}
						else if(viewType == "delete")
						{
							qryString = "DELETE FROM batches WHERE ID=" + batchID;
						}
						else
							return;

						this.aConnection.Open();
						OleDbCommand UpdateQry6 = new OleDbCommand(qryString, this.aConnection);
						UpdateQry6.ExecuteNonQuery();
						this.aConnection.Close();
					}
				}
			}
			catch(Exception e)
			{
				Form1.ShowError(e.Message.ToString()); 
				this.aConnection.Close();
			}



		}



		/// <summary>
		/// Clean up any resources being used.
		/// </summary>
		protected override void Dispose( bool disposing )
		{
			if( disposing )
			{
				if(components != null)
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
			this.panel1 = new System.Windows.Forms.Panel();
			this.label1 = new System.Windows.Forms.Label();
			this.cancelBtn = new System.Windows.Forms.Button();
			this.saveBtn = new System.Windows.Forms.Button();
			this.splitter1 = new System.Windows.Forms.Splitter();
			this.panel2 = new System.Windows.Forms.Panel();
			this.dataGridBats = new System.Windows.Forms.DataGrid();
			this.splitter3 = new System.Windows.Forms.Splitter();
			this.dataGridCats = new System.Windows.Forms.DataGrid();
			this.splitter2 = new System.Windows.Forms.Splitter();
			this.dataGridLink = new System.Windows.Forms.DataGrid();
			this.btnApply = new System.Windows.Forms.Button();
			this.panel1.SuspendLayout();
			this.panel2.SuspendLayout();
			((System.ComponentModel.ISupportInitialize)(this.dataGridBats)).BeginInit();
			((System.ComponentModel.ISupportInitialize)(this.dataGridCats)).BeginInit();
			((System.ComponentModel.ISupportInitialize)(this.dataGridLink)).BeginInit();
			this.SuspendLayout();
			// 
			// panel1
			// 
			this.panel1.Controls.Add(this.btnApply);
			this.panel1.Controls.Add(this.label1);
			this.panel1.Controls.Add(this.cancelBtn);
			this.panel1.Controls.Add(this.saveBtn);
			this.panel1.Dock = System.Windows.Forms.DockStyle.Top;
			this.panel1.Location = new System.Drawing.Point(0, 0);
			this.panel1.Name = "panel1";
			this.panel1.Size = new System.Drawing.Size(1088, 80);
			this.panel1.TabIndex = 0;
			// 
			// label1
			// 
			this.label1.Location = new System.Drawing.Point(336, 16);
			this.label1.Name = "label1";
			this.label1.Size = new System.Drawing.Size(464, 40);
			this.label1.TabIndex = 2;
			this.label1.Text = "AutoTime\'s format is military time.  In order for a batch to start automatically " +
				"there must be an AutoTime entered with 1 or more week days checked.";
			// 
			// cancelBtn
			// 
			this.cancelBtn.Location = new System.Drawing.Point(240, 24);
			this.cancelBtn.Name = "cancelBtn";
			this.cancelBtn.TabIndex = 1;
			this.cancelBtn.Text = "Cancel";
			this.cancelBtn.Click += new System.EventHandler(this.cancelBtn_Click);
			// 
			// saveBtn
			// 
			this.saveBtn.Location = new System.Drawing.Point(40, 24);
			this.saveBtn.Name = "saveBtn";
			this.saveBtn.TabIndex = 0;
			this.saveBtn.Text = "Save";
			this.saveBtn.Click += new System.EventHandler(this.button1_Click);
			// 
			// splitter1
			// 
			this.splitter1.Dock = System.Windows.Forms.DockStyle.Top;
			this.splitter1.Enabled = false;
			this.splitter1.Location = new System.Drawing.Point(0, 80);
			this.splitter1.Name = "splitter1";
			this.splitter1.Size = new System.Drawing.Size(1088, 3);
			this.splitter1.TabIndex = 1;
			this.splitter1.TabStop = false;
			// 
			// panel2
			// 
			this.panel2.Controls.Add(this.dataGridBats);
			this.panel2.Controls.Add(this.splitter3);
			this.panel2.Controls.Add(this.dataGridCats);
			this.panel2.Controls.Add(this.splitter2);
			this.panel2.Controls.Add(this.dataGridLink);
			this.panel2.Dock = System.Windows.Forms.DockStyle.Fill;
			this.panel2.Location = new System.Drawing.Point(0, 83);
			this.panel2.Name = "panel2";
			this.panel2.Size = new System.Drawing.Size(1088, 444);
			this.panel2.TabIndex = 2;
			// 
			// dataGridBats
			// 
			this.dataGridBats.CaptionText = "Batch Definitions";
			this.dataGridBats.DataMember = "";
			this.dataGridBats.Dock = System.Windows.Forms.DockStyle.Fill;
			this.dataGridBats.HeaderForeColor = System.Drawing.SystemColors.ControlText;
			this.dataGridBats.Location = new System.Drawing.Point(0, 0);
			this.dataGridBats.Name = "dataGridBats";
			this.dataGridBats.Size = new System.Drawing.Size(1088, 174);
			this.dataGridBats.TabIndex = 4;
			// 
			// splitter3
			// 
			this.splitter3.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.splitter3.Location = new System.Drawing.Point(0, 174);
			this.splitter3.Name = "splitter3";
			this.splitter3.Size = new System.Drawing.Size(1088, 3);
			this.splitter3.TabIndex = 3;
			this.splitter3.TabStop = false;
			// 
			// dataGridCats
			// 
			this.dataGridCats.CaptionText = "Category Control";
			this.dataGridCats.DataMember = "";
			this.dataGridCats.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.dataGridCats.HeaderForeColor = System.Drawing.SystemColors.ControlText;
			this.dataGridCats.Location = new System.Drawing.Point(0, 177);
			this.dataGridCats.Name = "dataGridCats";
			this.dataGridCats.Size = new System.Drawing.Size(1088, 160);
			this.dataGridCats.TabIndex = 2;
			// 
			// splitter2
			// 
			this.splitter2.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.splitter2.Location = new System.Drawing.Point(0, 337);
			this.splitter2.Name = "splitter2";
			this.splitter2.Size = new System.Drawing.Size(1088, 3);
			this.splitter2.TabIndex = 1;
			this.splitter2.TabStop = false;
			// 
			// dataGridLink
			// 
			this.dataGridLink.CaptionText = "Batch / Category Links";
			this.dataGridLink.DataMember = "";
			this.dataGridLink.Dock = System.Windows.Forms.DockStyle.Bottom;
			this.dataGridLink.HeaderForeColor = System.Drawing.SystemColors.ControlText;
			this.dataGridLink.Location = new System.Drawing.Point(0, 340);
			this.dataGridLink.Name = "dataGridLink";
			this.dataGridLink.Size = new System.Drawing.Size(1088, 104);
			this.dataGridLink.TabIndex = 0;
			// 
			// btnApply
			// 
			this.btnApply.Location = new System.Drawing.Point(144, 24);
			this.btnApply.Name = "btnApply";
			this.btnApply.TabIndex = 3;
			this.btnApply.Text = "Apply";
			this.btnApply.Click += new System.EventHandler(this.btnApply_Click);
			// 
			// EditBatchesForm
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(6, 15);
			this.ClientSize = new System.Drawing.Size(1088, 527);
			this.Controls.Add(this.panel2);
			this.Controls.Add(this.splitter1);
			this.Controls.Add(this.panel1);
			this.MaximizeBox = false;
			this.MinimizeBox = false;
			this.Name = "EditBatchesForm";
			this.ShowInTaskbar = false;
			this.Text = "EditBatchesForm";
			this.Load += new System.EventHandler(this.EditBatchesForm_Load);
			this.Closed += new System.EventHandler(this.EditBatchesForm_Closed);
			this.panel1.ResumeLayout(false);
			this.panel2.ResumeLayout(false);
			((System.ComponentModel.ISupportInitialize)(this.dataGridBats)).EndInit();
			((System.ComponentModel.ISupportInitialize)(this.dataGridCats)).EndInit();
			((System.ComponentModel.ISupportInitialize)(this.dataGridLink)).EndInit();
			this.ResumeLayout(false);

		}
		#endregion


		//Handle column changing events for the Category Table
		// We want to validate the input for the AutoTime
		private void Category_ColumnChanging(object sender, System.Data.DataColumnChangeEventArgs e) 
		{
			if (e.Column.ColumnName.Equals("AutoTime")) 
			{
				string pattern = @"^\d{2}:\d{2}$";
				if (!Regex.Match( e.ProposedValue.ToString(), pattern).Success) 
				{
					e.ProposedValue = "14:10";
					Form1.ShowError("The AutoTime must be in the format shown (exactly). 4 Digits separated by a colon.");
					return;
				}

				// Validate the first 2 digits
				MatchCollection mts;
				mts = Regex.Matches(e.ProposedValue.ToString(), @"^\d+");
				int firstDigits = System.Int32.Parse(mts[0].ToString());
				if(firstDigits > 23)
				{
					e.ProposedValue = "00:00";
					Form1.ShowError("You can't go over 24 hours.");
					return;
				}

				// Validate the second pair of digits for (minutes)
				mts = Regex.Matches(e.ProposedValue.ToString(), @"\d+$");
				int secondDigits = System.Int32.Parse(mts[0].ToString());
				//if((secondDigits > 59) || (secondDigits != 10 && secondDigits != 20 && secondDigits != 30 && secondDigits != 40 && secondDigits != 50 && secondDigits != 00))
				if((secondDigits > 59) || (secondDigits % 10 != 0))	
				{
					e.ProposedValue = firstDigits.ToString() + ":00";
					Form1.ShowError("The minutes must be in increments of 10 mintues.");
					return;
				}

				//Form1.ShowError(firstDigits.ToString());


			}
		}

		private void SaveData()
		{
			this.UpdateDBfromGrids("update");
			this.UpdateDBfromGrids("insert");
			this.UpdateDBfromGrids("delete");
			this.DataSets_All.AcceptChanges();

		}

		private void refreshParentForm()
		{
			Form1 parentFrm = (Form1) this.Owner;
			
			parentFrm.ReBuildProductTabs();
			parentFrm.RefreshGridBatches();
			parentFrm.RefreshBatchCounts("");
			
		}

		private void button1_Click(object sender, System.EventArgs e)
		{
			this.SaveData();

			this.refreshParentForm();

			this.Close();
		}

		private void EditBatchesForm_Load(object sender, System.EventArgs e)
		{

		}

		private void cancelBtn_Click(object sender, System.EventArgs e)
		{
			this.Close();
		}

		private void EditBatchesForm_Closed(object sender, System.EventArgs e)
		{
			this.Dispose();
		}

		private void btnApply_Click(object sender, System.EventArgs e)
		{
			this.SaveData();

			this.refreshParentForm();
		}
	}
}
