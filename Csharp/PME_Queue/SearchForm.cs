using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;
using System.Text.RegularExpressions;
using System.Data;
using System.Data.OleDb;

namespace PME_Queue
{
	/// <summary>
	/// Summary description for Form2.
	/// </summary>
	public class SearchForm : System.Windows.Forms.Form
	{
		private System.Windows.Forms.Label label1;
		private System.Windows.Forms.TextBox textBox1;
		private System.Windows.Forms.Button button1;
		private System.Windows.Forms.TextBox results;
		private OleDbConnection aConnection;
		private OleDbDataAdapter adapter;
		/// <summary>
		/// Required designer variable.
		/// </summary>
		private System.ComponentModel.Container components = null;

		public SearchForm()
		{
			//
			// Required for Windows Form Designer support
			//
			InitializeComponent();

			//
			// TODO: Add any constructor code after InitializeComponent call
			//

			this.aConnection = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=PME_QueueDB.mdb");
			this.adapter = new OleDbDataAdapter();
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
			this.label1 = new System.Windows.Forms.Label();
			this.textBox1 = new System.Windows.Forms.TextBox();
			this.button1 = new System.Windows.Forms.Button();
			this.results = new System.Windows.Forms.TextBox();
			this.SuspendLayout();
			// 
			// label1
			// 
			this.label1.Location = new System.Drawing.Point(16, 16);
			this.label1.Name = "label1";
			this.label1.Size = new System.Drawing.Size(224, 32);
			this.label1.TabIndex = 0;
			this.label1.Text = "Search by Project #      Ex. P12345";
			// 
			// textBox1
			// 
			this.textBox1.Location = new System.Drawing.Point(16, 48);
			this.textBox1.Name = "textBox1";
			this.textBox1.Size = new System.Drawing.Size(128, 22);
			this.textBox1.TabIndex = 1;
			this.textBox1.Text = "";
			this.textBox1.KeyUp += new System.Windows.Forms.KeyEventHandler(this.textBox1_KeyUp);
			// 
			// button1
			// 
			this.button1.Location = new System.Drawing.Point(160, 48);
			this.button1.Name = "button1";
			this.button1.Size = new System.Drawing.Size(80, 24);
			this.button1.TabIndex = 2;
			this.button1.Text = "Search";
			this.button1.Click += new System.EventHandler(this.button1_Click);
			// 
			// results
			// 
			this.results.Location = new System.Drawing.Point(16, 96);
			this.results.Multiline = true;
			this.results.Name = "results";
			this.results.ReadOnly = true;
			this.results.ScrollBars = System.Windows.Forms.ScrollBars.Vertical;
			this.results.Size = new System.Drawing.Size(280, 152);
			this.results.TabIndex = 3;
			this.results.Text = "";
			// 
			// SearchForm
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(6, 15);
			this.ClientSize = new System.Drawing.Size(312, 267);
			this.Controls.Add(this.results);
			this.Controls.Add(this.button1);
			this.Controls.Add(this.textBox1);
			this.Controls.Add(this.label1);
			this.Name = "SearchForm";
			this.Text = "Search For Project";
			this.Load += new System.EventHandler(this.SearchForm_Load);
			this.ResumeLayout(false);

		}
		#endregion

		private void SearchForm_Load(object sender, System.EventArgs e)
		{
			this.results.Text = "Type in a P number and then hit Search.";
		}

		private void searchForProject()
		{
			if(this.textBox1.Text == "")
			{
				Form1.ShowError("Type in some information before hitting search.");
				return;
			}

			Regex r = new Regex("(P\\d+)|(p\\d+)"); // Split on pipe symbols.
			if(!r.IsMatch(this.textBox1.Text))
			{
				Form1.ShowError("The project number is not in the proper format. It should be something like \"P123343\".  Make sure that there are no spaces or other characters.");
				return;
			}

			this.results.Text = "";

			DataTable ts = new DataTable();

			// Get rid of the "P" in front of the project number.
			string projectNumberRaw = this.textBox1.Text.ToString().Substring( 1 );

			DataSet DSsearch = new DataSet();

			// Search within the Active Jobs.
			try
			{
				this.aConnection.Open();

				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT FileName FROM commands WHERE ProjectList LIKE \"%" + projectNumberRaw + "%\""
					, this.aConnection);

				this.adapter.Fill(DSsearch, "searchProjectNum");
		
				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				Form1.ShowError("Error: " + e.Errors[0].Message);
			}

			ts = DSsearch.Tables["searchProjectNum"];
		
			if(ts.Rows.Count == 0)
				this.results.Text += "No project ID's found within \"Active Jobs\".\r\n\r\n";
			else
				this.results.Text += "Active Jobs\r\n------------------------------------\r\n";

		
			foreach(DataRow rs in ts.Rows)
			{
				this.results.Text += rs[ts.Columns["FileName"]].ToString() + "\r\n";
			}



			// Search within the Completed Jobs.
			DSsearch = new DataSet();

			try
			{
				this.aConnection.Open();

				this.adapter.SelectCommand = new OleDbCommand(
					"SELECT FileName FROM history WHERE ProjectList LIKE \"%" + projectNumberRaw + "%\""
					, this.aConnection);

				this.adapter.Fill(DSsearch, "searchProjectNum");
		
				//close the connection Its important.
				this.aConnection.Close();
			}
			catch(OleDbException e)
			{
				Form1.ShowError("Error: " + e.Errors[0].Message);
			}

			ts = DSsearch.Tables["searchProjectNum"];
		
			if(ts.Rows.Count == 0)
				this.results.Text += "";
			else
				this.results.Text += "\r\nHistory (Completed)\r\n------------------------------------\r\n";

		
			foreach(DataRow rs in ts.Rows)
			{
				this.results.Text += rs[ts.Columns["FileName"]].ToString() + "\r\n";
			}


		}

		private void button1_Click(object sender, System.EventArgs e)
		{
			this.searchForProject();
		}

		private void textBox1_KeyUp(object sender, System.Windows.Forms.KeyEventArgs e)
		{
			if(e.KeyCode == Keys.Enter)
				this.searchForProject();
		}


	}
}
