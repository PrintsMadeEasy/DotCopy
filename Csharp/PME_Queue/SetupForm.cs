using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;
using System.Text.RegularExpressions;

namespace PME_Queue
{
	/// <summary>
	/// Summary description for SetupForm.
	/// </summary>
	public class SetupForm : System.Windows.Forms.Form
	{
		private System.Windows.Forms.GroupBox groupBox1;
		private System.Windows.Forms.Label label1;
		private System.Windows.Forms.TextBox urlAPI;
		private System.Windows.Forms.TextBox userName;
		private System.Windows.Forms.TextBox passwd;
		private System.Windows.Forms.Label label3;
		private System.Windows.Forms.Label label2;
		private System.Windows.Forms.Button button1;
		private System.Windows.Forms.Button button2;
		/// <summary>
		/// Required designer variable.
		/// </summary>
		private System.ComponentModel.Container components = null;
		private System.Windows.Forms.GroupBox groupBox2;
		private System.Windows.Forms.TextBox projectCap;
		private System.Windows.Forms.Label label4;
		private System.Windows.Forms.Label label5;
		private System.Windows.Forms.TextBox pagesMax;
		private System.Windows.Forms.Label label6;
		public ApplicationSettings appSetting;

		public SetupForm()
		{
			//
			// Required for Windows Form Designer support
			//
			InitializeComponent();

			this.appSetting = new ApplicationSettings();

			//
			// TODO: Add any constructor code after InitializeComponent call
			//
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
			this.groupBox1 = new System.Windows.Forms.GroupBox();
			this.label1 = new System.Windows.Forms.Label();
			this.urlAPI = new System.Windows.Forms.TextBox();
			this.userName = new System.Windows.Forms.TextBox();
			this.passwd = new System.Windows.Forms.TextBox();
			this.label3 = new System.Windows.Forms.Label();
			this.label2 = new System.Windows.Forms.Label();
			this.button1 = new System.Windows.Forms.Button();
			this.button2 = new System.Windows.Forms.Button();
			this.groupBox2 = new System.Windows.Forms.GroupBox();
			this.label5 = new System.Windows.Forms.Label();
			this.pagesMax = new System.Windows.Forms.TextBox();
			this.label4 = new System.Windows.Forms.Label();
			this.projectCap = new System.Windows.Forms.TextBox();
			this.label6 = new System.Windows.Forms.Label();
			this.groupBox1.SuspendLayout();
			this.groupBox2.SuspendLayout();
			this.SuspendLayout();
			// 
			// groupBox1
			// 
			this.groupBox1.Controls.Add(this.label1);
			this.groupBox1.Controls.Add(this.urlAPI);
			this.groupBox1.Controls.Add(this.userName);
			this.groupBox1.Controls.Add(this.passwd);
			this.groupBox1.Controls.Add(this.label3);
			this.groupBox1.Controls.Add(this.label2);
			this.groupBox1.Location = new System.Drawing.Point(10, 16);
			this.groupBox1.Name = "groupBox1";
			this.groupBox1.Size = new System.Drawing.Size(272, 144);
			this.groupBox1.TabIndex = 8;
			this.groupBox1.TabStop = false;
			this.groupBox1.Text = "API Setup";
			// 
			// label1
			// 
			this.label1.Location = new System.Drawing.Point(16, 24);
			this.label1.Name = "label1";
			this.label1.Size = new System.Drawing.Size(224, 24);
			this.label1.TabIndex = 2;
			this.label1.Text = "URL to API";
			// 
			// urlAPI
			// 
			this.urlAPI.Location = new System.Drawing.Point(16, 48);
			this.urlAPI.Name = "urlAPI";
			this.urlAPI.Size = new System.Drawing.Size(232, 22);
			this.urlAPI.TabIndex = 0;
			this.urlAPI.Text = "API URL";
			// 
			// userName
			// 
			this.userName.Location = new System.Drawing.Point(16, 104);
			this.userName.Name = "userName";
			this.userName.Size = new System.Drawing.Size(104, 22);
			this.userName.TabIndex = 4;
			this.userName.Text = "User Name";
			// 
			// passwd
			// 
			this.passwd.Location = new System.Drawing.Point(144, 104);
			this.passwd.Name = "passwd";
			this.passwd.PasswordChar = '*';
			this.passwd.Size = new System.Drawing.Size(104, 22);
			this.passwd.TabIndex = 3;
			this.passwd.Text = "textBox2";
			// 
			// label3
			// 
			this.label3.Location = new System.Drawing.Point(144, 80);
			this.label3.Name = "label3";
			this.label3.Size = new System.Drawing.Size(96, 24);
			this.label3.TabIndex = 6;
			this.label3.Text = "Password";
			// 
			// label2
			// 
			this.label2.Location = new System.Drawing.Point(16, 80);
			this.label2.Name = "label2";
			this.label2.Size = new System.Drawing.Size(96, 24);
			this.label2.TabIndex = 5;
			this.label2.Text = "User Name";
			// 
			// button1
			// 
			this.button1.Location = new System.Drawing.Point(32, 344);
			this.button1.Name = "button1";
			this.button1.Size = new System.Drawing.Size(96, 32);
			this.button1.TabIndex = 10;
			this.button1.Text = "Save";
			this.button1.Click += new System.EventHandler(this.button1_Click);
			// 
			// button2
			// 
			this.button2.Location = new System.Drawing.Point(168, 344);
			this.button2.Name = "button2";
			this.button2.Size = new System.Drawing.Size(96, 32);
			this.button2.TabIndex = 11;
			this.button2.Text = "Cancel";
			this.button2.Click += new System.EventHandler(this.button2_Click);
			// 
			// groupBox2
			// 
			this.groupBox2.Controls.Add(this.label6);
			this.groupBox2.Controls.Add(this.label5);
			this.groupBox2.Controls.Add(this.pagesMax);
			this.groupBox2.Controls.Add(this.label4);
			this.groupBox2.Controls.Add(this.projectCap);
			this.groupBox2.Location = new System.Drawing.Point(8, 176);
			this.groupBox2.Name = "groupBox2";
			this.groupBox2.Size = new System.Drawing.Size(272, 152);
			this.groupBox2.TabIndex = 12;
			this.groupBox2.TabStop = false;
			this.groupBox2.Text = "Global Page Count Limits";
			// 
			// label5
			// 
			this.label5.Location = new System.Drawing.Point(176, 88);
			this.label5.Name = "label5";
			this.label5.Size = new System.Drawing.Size(80, 24);
			this.label5.TabIndex = 3;
			this.label5.Text = "Max Pages";
			this.label5.Click += new System.EventHandler(this.label5_Click);
			// 
			// pagesMax
			// 
			this.pagesMax.Location = new System.Drawing.Point(176, 112);
			this.pagesMax.Name = "pagesMax";
			this.pagesMax.Size = new System.Drawing.Size(64, 22);
			this.pagesMax.TabIndex = 2;
			this.pagesMax.Text = "textBox2";
			// 
			// label4
			// 
			this.label4.Location = new System.Drawing.Point(16, 88);
			this.label4.Name = "label4";
			this.label4.Size = new System.Drawing.Size(144, 24);
			this.label4.TabIndex = 1;
			this.label4.Text = "Project Cap (max 200)";
			// 
			// projectCap
			// 
			this.projectCap.Location = new System.Drawing.Point(16, 112);
			this.projectCap.Name = "projectCap";
			this.projectCap.Size = new System.Drawing.Size(64, 22);
			this.projectCap.TabIndex = 0;
			this.projectCap.Text = "textBox1";
			// 
			// label6
			// 
			this.label6.ForeColor = System.Drawing.SystemColors.Desktop;
			this.label6.Location = new System.Drawing.Point(8, 24);
			this.label6.Name = "label6";
			this.label6.Size = new System.Drawing.Size(256, 48);
			this.label6.TabIndex = 4;
			this.label6.Text = "These global settings for the \"Project Cap\" and \"Max Pages\" can be overridden wit" +
				"hin the individual Batch Settings.";
			this.label6.Click += new System.EventHandler(this.label6_Click);
			// 
			// SetupForm
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(6, 15);
			this.ClientSize = new System.Drawing.Size(296, 415);
			this.Controls.Add(this.groupBox2);
			this.Controls.Add(this.button2);
			this.Controls.Add(this.button1);
			this.Controls.Add(this.groupBox1);
			this.Name = "SetupForm";
			this.ShowInTaskbar = false;
			this.Text = "Setup";
			this.Load += new System.EventHandler(this.SetupForm_Load);
			this.groupBox1.ResumeLayout(false);
			this.groupBox2.ResumeLayout(false);
			this.ResumeLayout(false);

		}
		#endregion

		private void button2_Click(object sender, System.EventArgs e)
		{
			this.Close();
		}

		private void button1_Click(object sender, System.EventArgs e)
		{
			// Do some validation

			if (!Regex.Match( this.projectCap.Text, @"^\d+$").Success) 
			{
				Form1.ShowError("The project count must be a number.");
				return;
			}

			if(Convert.ToInt32(this.projectCap.Text) > 200 || Convert.ToInt32(this.projectCap.Text) < 1)
			{
				Form1.ShowError("The project count must be a number between 1 and 200.");
				return;
			}


			if (!Regex.Match( this.pagesMax.Text, @"^\d+$").Success) 
			{
				Form1.ShowError("The Max Pages must be a number.");
				return;
			}

			if(Convert.ToInt32(this.pagesMax.Text) > 1000000 || Convert.ToInt32(this.pagesMax.Text) < 1)
			{
				Form1.ShowError("The project count must be a number between 1 and 1,000,000.");
				return;
			}

			this.appSetting.APIurl = this.urlAPI.Text;
			this.appSetting.UserName = this.userName.Text;
			this.appSetting.Password = this.passwd.Text;
			this.appSetting.ProjectCap = this.projectCap.Text;
			this.appSetting.PagesMax = this.pagesMax.Text;

			// Write out to isolated storage
			this.appSetting.SaveData();

			this.appSetting.Refresh();

			this.Close();
		}

		private void SetupForm_Load(object sender, System.EventArgs e)
		{
			this.urlAPI.Text = this.appSetting.APIurl;
			this.userName.Text = this.appSetting.UserName;
			this.passwd.Text = this.appSetting.Password;
			this.projectCap.Text = this.appSetting.ProjectCap;
			this.pagesMax.Text = this.appSetting.PagesMax;
		}

		private void label5_Click(object sender, System.EventArgs e)
		{
		
		}

		private void label6_Click(object sender, System.EventArgs e)
		{
		
		}
	}

	
	/// <summary>
	/// Gets and sets all of the settings for this application to isolated storage
	/// </summary>
	public class ApplicationSettings
	{
		private CustomStorage.ApplicationStorage settings;

		public ApplicationSettings()
		{
			// Load data from the issolated storate
			settings = new CustomStorage.ApplicationStorage();
		}

		public void Refresh()
		{
			settings.ReLoad();
		}
		public void SaveData()
		{
			settings.Save();
		}

		public string APIurl
		{
			get
			{
				if( settings.ContainsKey( "APIurl" ))
					return settings["APIurl"].ToString();
				else
					return "";
			}
			set
			{
				settings["APIurl"] = value;
			}
		}
		public string UserName
		{
			get
			{
				if( settings.ContainsKey( "UserName" ))
					return settings["UserName"].ToString();
				else
					return "";
			}
			set
			{
				settings["UserName"] = value;
			}
		}
		public string Password
		{
			get
			{
				if( settings.ContainsKey( "Password" ))
					return settings["Password"].ToString();
				else
					return "";
			}
			set
			{
				settings["Password"] = value;
			}
		}
		public string ProjectCap
		{
			get
			{
				if( settings.ContainsKey( "ProjectCap" ))
					return settings["ProjectCap"].ToString();
				else
					return "200";
			}
			set
			{
				settings["ProjectCap"] = value;
			}
		}
		public string PagesMax
		{
			get
			{
				if( settings.ContainsKey( "PagesMax" ))
					return settings["PagesMax"].ToString();
				else
					return "2000";
			}
			set
			{
				settings["PagesMax"] = value;
			}
		}

	}

}
