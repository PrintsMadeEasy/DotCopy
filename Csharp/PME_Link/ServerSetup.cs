using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;
using System.Collections.Specialized;
using System.Management;
using System.IO;


namespace PME_Link
{
	/// <summary>
	/// Summary description for APIsetup.
	/// </summary>
	public class ServerSetup : System.Windows.Forms.Form
	{
		private System.Windows.Forms.Label label1;
		private System.Windows.Forms.TextBox passwd;
		private System.Windows.Forms.TextBox userName;
		private System.Windows.Forms.Label label2;
		private System.Windows.Forms.Label label3;
		private System.Windows.Forms.GroupBox groupBox1;
		private System.Windows.Forms.TextBox urlAPI;
		private System.Windows.Forms.GroupBox groupBox2;
		private System.Windows.Forms.Label label4;
		private System.Windows.Forms.Label label5;
		private System.Windows.Forms.TextBox orderURL;
		private System.Windows.Forms.TextBox projectURL;
		private System.Windows.Forms.Button Save;
		private System.Windows.Forms.Button Cancel;
		
		public ApplicationSettings appSetting;
		private System.Windows.Forms.GroupBox groupBox3;
		private System.Windows.Forms.Label label6;
		private System.Windows.Forms.Label label7;
		private System.Windows.Forms.ComboBox invoicePrinter;
		private System.Windows.Forms.ComboBox labelPrinter;
		private System.Windows.Forms.ComboBox shippingPrinter;
		private System.Windows.Forms.Label label8;
		private System.Windows.Forms.GroupBox groupBox4;
		private System.Windows.Forms.CheckBox checkInvoiceBack;
		private System.Windows.Forms.Label label9;
		private System.Windows.Forms.Label label10;
		private System.Windows.Forms.Label label11;
		private System.Windows.Forms.TextBox brcdDPI;
		private System.Windows.Forms.TextBox brcdWidth;
		private System.Windows.Forms.TextBox brcdHeight;
		private System.Windows.Forms.GroupBox PromoPrinterGrp;
		private System.Windows.Forms.Label label12;
		private System.Windows.Forms.Label label13;
		private System.Windows.Forms.Label label14;
		private System.Windows.Forms.Label label15;
		private System.Windows.Forms.TextBox promo1Command;
		private System.Windows.Forms.TextBox promo1ComTranslate;
		private System.Windows.Forms.ComboBox promo1Printer;
		private System.Windows.Forms.ComboBox promo1ComPort;
		private System.Windows.Forms.CheckBox promo1Active;
	

		/// <summary>
		/// Required designer variable.
		/// </summary>
		private System.ComponentModel.Container components = null;

		public ServerSetup()
		{
			//
			// Required for Windows Form Designer support
			//
			InitializeComponent();


			this.appSetting = new ApplicationSettings();


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
			this.urlAPI = new System.Windows.Forms.TextBox();
			this.label1 = new System.Windows.Forms.Label();
			this.passwd = new System.Windows.Forms.TextBox();
			this.userName = new System.Windows.Forms.TextBox();
			this.label2 = new System.Windows.Forms.Label();
			this.label3 = new System.Windows.Forms.Label();
			this.groupBox1 = new System.Windows.Forms.GroupBox();
			this.groupBox2 = new System.Windows.Forms.GroupBox();
			this.orderURL = new System.Windows.Forms.TextBox();
			this.projectURL = new System.Windows.Forms.TextBox();
			this.label4 = new System.Windows.Forms.Label();
			this.label5 = new System.Windows.Forms.Label();
			this.Save = new System.Windows.Forms.Button();
			this.Cancel = new System.Windows.Forms.Button();
			this.groupBox3 = new System.Windows.Forms.GroupBox();
			this.shippingPrinter = new System.Windows.Forms.ComboBox();
			this.label8 = new System.Windows.Forms.Label();
			this.labelPrinter = new System.Windows.Forms.ComboBox();
			this.invoicePrinter = new System.Windows.Forms.ComboBox();
			this.label7 = new System.Windows.Forms.Label();
			this.label6 = new System.Windows.Forms.Label();
			this.groupBox4 = new System.Windows.Forms.GroupBox();
			this.label11 = new System.Windows.Forms.Label();
			this.label10 = new System.Windows.Forms.Label();
			this.label9 = new System.Windows.Forms.Label();
			this.brcdHeight = new System.Windows.Forms.TextBox();
			this.brcdWidth = new System.Windows.Forms.TextBox();
			this.brcdDPI = new System.Windows.Forms.TextBox();
			this.checkInvoiceBack = new System.Windows.Forms.CheckBox();
			this.PromoPrinterGrp = new System.Windows.Forms.GroupBox();
			this.label15 = new System.Windows.Forms.Label();
			this.label14 = new System.Windows.Forms.Label();
			this.promo1ComPort = new System.Windows.Forms.ComboBox();
			this.label13 = new System.Windows.Forms.Label();
			this.label12 = new System.Windows.Forms.Label();
			this.promo1Printer = new System.Windows.Forms.ComboBox();
			this.promo1ComTranslate = new System.Windows.Forms.TextBox();
			this.promo1Command = new System.Windows.Forms.TextBox();
			this.promo1Active = new System.Windows.Forms.CheckBox();
			this.groupBox1.SuspendLayout();
			this.groupBox2.SuspendLayout();
			this.groupBox3.SuspendLayout();
			this.groupBox4.SuspendLayout();
			this.PromoPrinterGrp.SuspendLayout();
			this.SuspendLayout();
			// 
			// urlAPI
			// 
			this.urlAPI.Location = new System.Drawing.Point(13, 42);
			this.urlAPI.Name = "urlAPI";
			this.urlAPI.Size = new System.Drawing.Size(194, 20);
			this.urlAPI.TabIndex = 0;
			this.urlAPI.Text = "API URL";
			// 
			// label1
			// 
			this.label1.Location = new System.Drawing.Point(13, 21);
			this.label1.Name = "label1";
			this.label1.Size = new System.Drawing.Size(187, 21);
			this.label1.TabIndex = 2;
			this.label1.Text = "URL to API";
			// 
			// passwd
			// 
			this.passwd.Location = new System.Drawing.Point(120, 90);
			this.passwd.Name = "passwd";
			this.passwd.PasswordChar = '*';
			this.passwd.Size = new System.Drawing.Size(87, 20);
			this.passwd.TabIndex = 3;
			this.passwd.Text = "textBox2";
			// 
			// userName
			// 
			this.userName.Location = new System.Drawing.Point(13, 90);
			this.userName.Name = "userName";
			this.userName.Size = new System.Drawing.Size(87, 20);
			this.userName.TabIndex = 4;
			this.userName.Text = "User Name";
			// 
			// label2
			// 
			this.label2.Location = new System.Drawing.Point(13, 69);
			this.label2.Name = "label2";
			this.label2.Size = new System.Drawing.Size(80, 21);
			this.label2.TabIndex = 5;
			this.label2.Text = "User Name";
			// 
			// label3
			// 
			this.label3.Location = new System.Drawing.Point(120, 69);
			this.label3.Name = "label3";
			this.label3.Size = new System.Drawing.Size(80, 21);
			this.label3.TabIndex = 6;
			this.label3.Text = "Password";
			// 
			// groupBox1
			// 
			this.groupBox1.Controls.Add(this.label1);
			this.groupBox1.Controls.Add(this.urlAPI);
			this.groupBox1.Controls.Add(this.userName);
			this.groupBox1.Controls.Add(this.passwd);
			this.groupBox1.Controls.Add(this.label3);
			this.groupBox1.Controls.Add(this.label2);
			this.groupBox1.Location = new System.Drawing.Point(7, 7);
			this.groupBox1.Name = "groupBox1";
			this.groupBox1.Size = new System.Drawing.Size(226, 125);
			this.groupBox1.TabIndex = 7;
			this.groupBox1.TabStop = false;
			this.groupBox1.Text = "API Setup";
			// 
			// groupBox2
			// 
			this.groupBox2.Controls.Add(this.orderURL);
			this.groupBox2.Controls.Add(this.projectURL);
			this.groupBox2.Controls.Add(this.label4);
			this.groupBox2.Controls.Add(this.label5);
			this.groupBox2.Location = new System.Drawing.Point(247, 7);
			this.groupBox2.Name = "groupBox2";
			this.groupBox2.Size = new System.Drawing.Size(286, 125);
			this.groupBox2.TabIndex = 8;
			this.groupBox2.TabStop = false;
			this.groupBox2.Text = "Links";
			// 
			// orderURL
			// 
			this.orderURL.Location = new System.Drawing.Point(13, 42);
			this.orderURL.Name = "orderURL";
			this.orderURL.Size = new System.Drawing.Size(260, 20);
			this.orderURL.TabIndex = 9;
			this.orderURL.Text = "Order Screen URL";
			// 
			// projectURL
			// 
			this.projectURL.Location = new System.Drawing.Point(13, 90);
			this.projectURL.Name = "projectURL";
			this.projectURL.Size = new System.Drawing.Size(260, 20);
			this.projectURL.TabIndex = 9;
			this.projectURL.Text = "Project Screen URL";
			// 
			// label4
			// 
			this.label4.Location = new System.Drawing.Point(13, 21);
			this.label4.Name = "label4";
			this.label4.Size = new System.Drawing.Size(187, 21);
			this.label4.TabIndex = 9;
			this.label4.Text = "Order Screen";
			// 
			// label5
			// 
			this.label5.Location = new System.Drawing.Point(13, 69);
			this.label5.Name = "label5";
			this.label5.Size = new System.Drawing.Size(187, 21);
			this.label5.TabIndex = 10;
			this.label5.Text = "Project Screen";
			// 
			// Save
			// 
			this.Save.Location = new System.Drawing.Point(152, 408);
			this.Save.Name = "Save";
			this.Save.Size = new System.Drawing.Size(80, 28);
			this.Save.TabIndex = 11;
			this.Save.Text = "Save";
			this.Save.Click += new System.EventHandler(this.Save_Click);
			// 
			// Cancel
			// 
			this.Cancel.Location = new System.Drawing.Point(296, 408);
			this.Cancel.Name = "Cancel";
			this.Cancel.Size = new System.Drawing.Size(80, 28);
			this.Cancel.TabIndex = 12;
			this.Cancel.Text = "Cancel";
			this.Cancel.Click += new System.EventHandler(this.Cancel_Click);
			// 
			// groupBox3
			// 
			this.groupBox3.Controls.Add(this.shippingPrinter);
			this.groupBox3.Controls.Add(this.label8);
			this.groupBox3.Controls.Add(this.labelPrinter);
			this.groupBox3.Controls.Add(this.invoicePrinter);
			this.groupBox3.Controls.Add(this.label7);
			this.groupBox3.Controls.Add(this.label6);
			this.groupBox3.Location = new System.Drawing.Point(7, 153);
			this.groupBox3.Name = "groupBox3";
			this.groupBox3.Size = new System.Drawing.Size(286, 110);
			this.groupBox3.TabIndex = 13;
			this.groupBox3.TabStop = false;
			this.groupBox3.Text = "Printer Setup";
			// 
			// shippingPrinter
			// 
			this.shippingPrinter.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList;
			this.shippingPrinter.Location = new System.Drawing.Point(100, 76);
			this.shippingPrinter.Name = "shippingPrinter";
			this.shippingPrinter.Size = new System.Drawing.Size(173, 21);
			this.shippingPrinter.TabIndex = 17;
			// 
			// label8
			// 
			this.label8.Location = new System.Drawing.Point(7, 76);
			this.label8.Name = "label8";
			this.label8.Size = new System.Drawing.Size(100, 21);
			this.label8.TabIndex = 18;
			this.label8.Text = "Shipping Printer:";
			// 
			// labelPrinter
			// 
			this.labelPrinter.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList;
			this.labelPrinter.Location = new System.Drawing.Point(100, 16);
			this.labelPrinter.Name = "labelPrinter";
			this.labelPrinter.Size = new System.Drawing.Size(173, 21);
			this.labelPrinter.TabIndex = 16;
			// 
			// invoicePrinter
			// 
			this.invoicePrinter.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList;
			this.invoicePrinter.Location = new System.Drawing.Point(100, 49);
			this.invoicePrinter.Name = "invoicePrinter";
			this.invoicePrinter.Size = new System.Drawing.Size(173, 21);
			this.invoicePrinter.TabIndex = 15;
			// 
			// label7
			// 
			this.label7.Location = new System.Drawing.Point(7, 49);
			this.label7.Name = "label7";
			this.label7.Size = new System.Drawing.Size(100, 20);
			this.label7.TabIndex = 1;
			this.label7.Text = "Invoice Printer:";
			// 
			// label6
			// 
			this.label6.Location = new System.Drawing.Point(7, 21);
			this.label6.Name = "label6";
			this.label6.Size = new System.Drawing.Size(120, 14);
			this.label6.TabIndex = 0;
			this.label6.Text = "Project Printer:";
			// 
			// groupBox4
			// 
			this.groupBox4.Controls.Add(this.label11);
			this.groupBox4.Controls.Add(this.label10);
			this.groupBox4.Controls.Add(this.label9);
			this.groupBox4.Controls.Add(this.brcdHeight);
			this.groupBox4.Controls.Add(this.brcdWidth);
			this.groupBox4.Controls.Add(this.brcdDPI);
			this.groupBox4.Controls.Add(this.checkInvoiceBack);
			this.groupBox4.Location = new System.Drawing.Point(307, 153);
			this.groupBox4.Name = "groupBox4";
			this.groupBox4.Size = new System.Drawing.Size(226, 110);
			this.groupBox4.TabIndex = 14;
			this.groupBox4.TabStop = false;
			this.groupBox4.Text = "Configuration";
			// 
			// label11
			// 
			this.label11.Location = new System.Drawing.Point(160, 55);
			this.label11.Name = "label11";
			this.label11.Size = new System.Drawing.Size(60, 14);
			this.label11.TabIndex = 6;
			this.label11.Text = "Bar Height";
			// 
			// label10
			// 
			this.label10.Location = new System.Drawing.Point(87, 55);
			this.label10.Name = "label10";
			this.label10.Size = new System.Drawing.Size(73, 14);
			this.label10.TabIndex = 5;
			this.label10.Text = "Bar Width";
			// 
			// label9
			// 
			this.label9.Location = new System.Drawing.Point(7, 55);
			this.label9.Name = "label9";
			this.label9.Size = new System.Drawing.Size(73, 14);
			this.label9.TabIndex = 4;
			this.label9.Text = "Barcode DPI";
			// 
			// brcdHeight
			// 
			this.brcdHeight.Location = new System.Drawing.Point(160, 76);
			this.brcdHeight.Name = "brcdHeight";
			this.brcdHeight.Size = new System.Drawing.Size(60, 20);
			this.brcdHeight.TabIndex = 3;
			this.brcdHeight.Text = "textBox3";
			// 
			// brcdWidth
			// 
			this.brcdWidth.Location = new System.Drawing.Point(87, 76);
			this.brcdWidth.Name = "brcdWidth";
			this.brcdWidth.Size = new System.Drawing.Size(60, 20);
			this.brcdWidth.TabIndex = 2;
			this.brcdWidth.Text = "textBox2";
			// 
			// brcdDPI
			// 
			this.brcdDPI.Location = new System.Drawing.Point(7, 76);
			this.brcdDPI.Name = "brcdDPI";
			this.brcdDPI.Size = new System.Drawing.Size(60, 20);
			this.brcdDPI.TabIndex = 1;
			this.brcdDPI.Text = "barCodeDPI";
			// 
			// checkInvoiceBack
			// 
			this.checkInvoiceBack.Location = new System.Drawing.Point(13, 21);
			this.checkInvoiceBack.Name = "checkInvoiceBack";
			this.checkInvoiceBack.Size = new System.Drawing.Size(174, 21);
			this.checkInvoiceBack.TabIndex = 0;
			this.checkInvoiceBack.Text = "Show Invoice Background";
			// 
			// PromoPrinterGrp
			// 
			this.PromoPrinterGrp.BackColor = System.Drawing.SystemColors.Control;
			this.PromoPrinterGrp.Controls.Add(this.promo1Active);
			this.PromoPrinterGrp.Controls.Add(this.label15);
			this.PromoPrinterGrp.Controls.Add(this.label14);
			this.PromoPrinterGrp.Controls.Add(this.promo1ComPort);
			this.PromoPrinterGrp.Controls.Add(this.label13);
			this.PromoPrinterGrp.Controls.Add(this.label12);
			this.PromoPrinterGrp.Controls.Add(this.promo1Printer);
			this.PromoPrinterGrp.Controls.Add(this.promo1ComTranslate);
			this.PromoPrinterGrp.Controls.Add(this.promo1Command);
			this.PromoPrinterGrp.Location = new System.Drawing.Point(16, 288);
			this.PromoPrinterGrp.Name = "PromoPrinterGrp";
			this.PromoPrinterGrp.Size = new System.Drawing.Size(552, 96);
			this.PromoPrinterGrp.TabIndex = 15;
			this.PromoPrinterGrp.TabStop = false;
			this.PromoPrinterGrp.Text = "Promotional Printing Setup";
			// 
			// label15
			// 
			this.label15.ForeColor = System.Drawing.SystemColors.Desktop;
			this.label15.Location = new System.Drawing.Point(368, 40);
			this.label15.Name = "label15";
			this.label15.Size = new System.Drawing.Size(176, 16);
			this.label15.TabIndex = 8;
			this.label15.Text = "Com Port Message Translations";
			// 
			// label14
			// 
			this.label14.ForeColor = System.Drawing.SystemColors.Desktop;
			this.label14.Location = new System.Drawing.Point(280, 40);
			this.label14.Name = "label14";
			this.label14.Size = new System.Drawing.Size(56, 16);
			this.label14.TabIndex = 7;
			this.label14.Text = "Com Port";
			// 
			// promo1ComPort
			// 
			this.promo1ComPort.Location = new System.Drawing.Point(280, 56);
			this.promo1ComPort.Name = "promo1ComPort";
			this.promo1ComPort.Size = new System.Drawing.Size(72, 21);
			this.promo1ComPort.TabIndex = 6;
			this.promo1ComPort.Text = "comboBox2";
			// 
			// label13
			// 
			this.label13.ForeColor = System.Drawing.SystemColors.Desktop;
			this.label13.Location = new System.Drawing.Point(184, 40);
			this.label13.Name = "label13";
			this.label13.Size = new System.Drawing.Size(72, 16);
			this.label13.TabIndex = 5;
			this.label13.Text = "Command";
			// 
			// label12
			// 
			this.label12.ForeColor = System.Drawing.SystemColors.Desktop;
			this.label12.Location = new System.Drawing.Point(8, 40);
			this.label12.Name = "label12";
			this.label12.Size = new System.Drawing.Size(152, 16);
			this.label12.TabIndex = 4;
			this.label12.Text = "Printer";
			// 
			// promo1Printer
			// 
			this.promo1Printer.Location = new System.Drawing.Point(8, 56);
			this.promo1Printer.Name = "promo1Printer";
			this.promo1Printer.Size = new System.Drawing.Size(160, 21);
			this.promo1Printer.TabIndex = 3;
			this.promo1Printer.Text = "comboBox1";
			// 
			// promo1ComTranslate
			// 
			this.promo1ComTranslate.Location = new System.Drawing.Point(368, 56);
			this.promo1ComTranslate.Name = "promo1ComTranslate";
			this.promo1ComTranslate.Size = new System.Drawing.Size(176, 20);
			this.promo1ComTranslate.TabIndex = 1;
			this.promo1ComTranslate.Text = "Com Port Translations";
			// 
			// promo1Command
			// 
			this.promo1Command.Location = new System.Drawing.Point(184, 56);
			this.promo1Command.Name = "promo1Command";
			this.promo1Command.Size = new System.Drawing.Size(80, 20);
			this.promo1Command.TabIndex = 0;
			this.promo1Command.Text = "textBox1";
			// 
			// promo1Active
			// 
			this.promo1Active.Location = new System.Drawing.Point(248, 16);
			this.promo1Active.Name = "promo1Active";
			this.promo1Active.TabIndex = 10;
			this.promo1Active.Text = "Active";
			// 
			// ServerSetup
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(5, 13);
			this.ClientSize = new System.Drawing.Size(579, 448);
			this.Controls.Add(this.PromoPrinterGrp);
			this.Controls.Add(this.groupBox4);
			this.Controls.Add(this.groupBox3);
			this.Controls.Add(this.Cancel);
			this.Controls.Add(this.Save);
			this.Controls.Add(this.groupBox1);
			this.Controls.Add(this.groupBox2);
			this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedToolWindow;
			this.MaximizeBox = false;
			this.MinimizeBox = false;
			this.Name = "ServerSetup";
			this.ShowInTaskbar = false;
			this.StartPosition = System.Windows.Forms.FormStartPosition.CenterScreen;
			this.Text = "Setup";
			this.Load += new System.EventHandler(this.APIsetup_Load);
			this.groupBox1.ResumeLayout(false);
			this.groupBox2.ResumeLayout(false);
			this.groupBox3.ResumeLayout(false);
			this.groupBox4.ResumeLayout(false);
			this.PromoPrinterGrp.ResumeLayout(false);
			this.ResumeLayout(false);

		}
		#endregion

		private void APIsetup_Load(object sender, System.EventArgs e)
		{

			this.urlAPI.Text = this.appSetting.APIurl;
			this.userName.Text = this.appSetting.UserName;
			this.passwd.Text = this.appSetting.Password;
			this.orderURL.Text = this.appSetting.OrderLink;
			this.projectURL.Text = this.appSetting.ProjectLink;
			this.brcdDPI.Text = this.appSetting.BarcodeDPI;
			this.brcdWidth.Text = this.appSetting.BarcodeWidth;
			this.brcdHeight.Text = this.appSetting.BarcodeHeight;
			this.promo1Command.Text = this.appSetting.promoCommand_1;
			this.promo1ComTranslate.Text = this.appSetting.promoComTranslations_1;

			// The checkbox for Show Background on the invoice
			if( this.appSetting.showInvoiceBackground )
				this.checkInvoiceBack.Checked = true;
			else
				this.checkInvoiceBack.Checked = false;


			// The checkbox that tells if Promo 1 is active or not.
			if( this.appSetting.promoActive_1 )
				this.promo1Active.Checked = true;
			else
				this.promo1Active.Checked = false;


			// Clear out the printing drop downs. we rebuild them every time the page loads
			this.labelPrinter.Items.Clear();
			this.invoicePrinter.Items.Clear();
			this.shippingPrinter.Items.Clear();
			this.promo1Printer.Items.Clear();
			this.promo1ComPort.Items.Clear();

			// If no printer has been recorded, then show "Select a printer" as the first choice in the drop down
			if( this.appSetting.labelPrinterName == "" )
				this.labelPrinter.Items.Add( "Select a Printer" );
			if( this.appSetting.invoicePrinterName == "" )
				this.invoicePrinter.Items.Add( "Select a Printer" );
			if( this.appSetting.shippingPrinterName == "" )
				this.shippingPrinter.Items.Add( "Select a Printer" );
			if( this.appSetting.promoPrinterName_1 == "" )
				this.promo1Printer.Items.Add( "Select a Printer" );

			StringCollection printerList = ServerSetup.GetPrintersCollection();
			int printerCounter = 0;
			foreach( string printerName in printerList )
			{
				this.labelPrinter.Items.Add( printerName );
				this.invoicePrinter.Items.Add( printerName );
				this.shippingPrinter.Items.Add( printerName );
				this.promo1Printer.Items.Add( printerName );

				// select the printer in the drop down that is stored in out application settings
				if( this.appSetting.labelPrinterName ==  printerName )
					this.labelPrinter.SelectedItem = printerName;
				if( this.appSetting.invoicePrinterName ==  printerName )
					this.invoicePrinter.SelectedItem = printerName;
				if( this.appSetting.shippingPrinterName ==  printerName )
					this.shippingPrinter.SelectedItem = printerName;
				if( this.appSetting.promoPrinterName_1 ==  printerName )
					this.promo1Printer.SelectedItem = printerName;

				printerCounter++;
			}


			// Build a list of all avaiable com ports in the system.
			StringCollection comPortList = ServerSetup.GetComPortsList();
			foreach( string comPortNum in comPortList )
			{
				this.promo1ComPort.Items.Add (comPortNum);

				if(this.appSetting.promoComPortNumber_1 == comPortNum)
						this.promo1ComPort.SelectedItem = comPortNum;
			}

			

			// If there are no printers selected yet, then select the first item
			if( this.labelPrinter.SelectedIndex == -1 )
				this.labelPrinter.SelectedIndex = 0;
			if( this.invoicePrinter.SelectedIndex == -1 )
				this.invoicePrinter.SelectedIndex = 0;
			if( this.shippingPrinter.SelectedIndex == -1 )
				this.shippingPrinter.SelectedIndex = 0;
			if( this.promo1Printer.SelectedIndex == -1 )
				this.promo1Printer.SelectedIndex = 0;

			// Select the first choice if no comp ports selected.
			if(this.promo1ComPort.SelectedIndex == -1)
				this.promo1ComPort.SelectedIndex = 0;
		}


		private void Cancel_Click(object sender, System.EventArgs e)
		{
			this.Close();
		}

		private void Save_Click(object sender, System.EventArgs e)
		{
	
			this.appSetting.APIurl = this.urlAPI.Text;
			this.appSetting.UserName = this.userName.Text;
			this.appSetting.Password = this.passwd.Text;
			this.appSetting.OrderLink = this.orderURL.Text;
			this.appSetting.ProjectLink = this.projectURL.Text;


			this.appSetting.BarcodeDPI = this.brcdDPI.Text;
			this.appSetting.BarcodeWidth = this.brcdWidth.Text;
			this.appSetting.BarcodeHeight = this.brcdHeight.Text;

			this.appSetting.promoCommand_1 = this.promo1Command.Text;
			this.appSetting.promoComTranslations_1 = this.promo1ComTranslate.Text;

			// for the checkbox if we want to show the invoice background
			if( this.checkInvoiceBack.Checked )
				this.appSetting.showInvoiceBackground = true;
			else
				this.appSetting.showInvoiceBackground = false;


			// To Disable the promotional product.
			if( this.promo1Active.Checked )
				this.appSetting.promoActive_1 = true;
			else
				this.appSetting.promoActive_1 = false;



			// Get the printer names out of the drop down menu
			// Make sure we only record the settings if they have chosen something other than the default
			if( this.labelPrinter.SelectedItem.ToString() != "Select a Printer" )
				this.appSetting.labelPrinterName = this.labelPrinter.SelectedItem.ToString();

			if( this.invoicePrinter.SelectedItem.ToString() != "Select a Printer" )
				this.appSetting.invoicePrinterName = this.invoicePrinter.SelectedItem.ToString();

			if( this.shippingPrinter.SelectedItem.ToString() != "Select a Printer" )
				this.appSetting.shippingPrinterName = this.shippingPrinter.SelectedItem.ToString();

			if( this.promo1Printer.SelectedItem.ToString() != "Select a Printer" )
				this.appSetting.promoPrinterName_1 = this.promo1Printer.SelectedItem.ToString();

			
			// Save the Com Port choices... even if it on "None".
			this.appSetting.promoComPortNumber_1 = this.promo1ComPort.SelectedItem.ToString();


			// Write out to isolated storage
			this.appSetting.SaveData();

			this.appSetting.Refresh();


			this.Close();
		}

		public static StringCollection GetPrintersCollection()
		{
			StringCollection printerNameCollection = new StringCollection();
			string searchQuery = "SELECT * FROM Win32_Printer";
			ManagementObjectSearcher searchPrinters = 
				new ManagementObjectSearcher(searchQuery);
			ManagementObjectCollection printerCollection = searchPrinters.Get();
			foreach(ManagementObject printer in printerCollection)
			{
				printerNameCollection.Add(printer.Properties["Name"].Value.ToString());
			}
			return printerNameCollection;
		}


		public static StringCollection GetComPortsList()
		{
			StringCollection comPortsCollection = new StringCollection();
			
			// The first choice for a com port is always None
			comPortsCollection.Add("None");

			byte[] port_list =  SerialNET.Port.List;	// Get an array of available ports

			// Init serial port dropdown
			for(int i = 0; i < port_list.Length; i++)
			{
				comPortsCollection.Add("COM" + port_list[i]);
			}
			return comPortsCollection;
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
		public string BarcodeWidth
		{
			get
			{
				if( settings.ContainsKey( "BarcodeWidth" ))
					return settings["BarcodeWidth"].ToString();
				else
					return "0.10";
			}
			set
			{
				settings["BarcodeWidth"] = value;
			}
		}
		public string BarcodeHeight
		{
			get
			{
				if( settings.ContainsKey( "BarcodeHeight" ))
					return settings["BarcodeHeight"].ToString();
				else
					return "1.5";
			}
			set
			{
				settings["BarcodeHeight"] = value;
			}
		}
		public string BarcodeDPI
		{
			get
			{
				if( settings.ContainsKey( "BarcodeDPI" ))
					return settings["BarcodeDPI"].ToString();
				else
					return "70";
			}
			set
			{
				settings["BarcodeDPI"] = value;
			}
		}
		public string OrderLink
		{
			get
			{
				if( settings.ContainsKey( "OrderLink" ))
					return settings["OrderLink"].ToString();
				else
					return "";
			}
			set
			{
				settings["OrderLink"] = value;
			}
		}
		public string ProjectLink
		{
			get
			{
				if( settings.ContainsKey( "ProjectLink" ))
					return settings["ProjectLink"].ToString();
				else
					return "";
			}
			set
			{
				settings["ProjectLink"] = value;
			}
		}

		// The isolated storage stores the result in a string "yes" or "no" .. this property converts to bool for us.
		public bool showInvoiceBackground
		{
			get
			{
				if( settings.ContainsKey( "ShowInvoiceBackground" ))
					if( settings["ShowInvoiceBackground"].ToString() == "no" )
						return false;
					else
						return true;
				else
					return true;
			}
			set
			{
				if( value )
					settings["ShowInvoiceBackground"] = "yes";
				else
					settings["ShowInvoiceBackground"] = "no";

			}
		}
		public string labelPrinterName
		{
			get
			{
				if( settings.ContainsKey( "LabelPrinter" ))
					return settings["LabelPrinter"].ToString();
				else
					return "";
			}
			set
			{
				settings["LabelPrinter"] = value;
			}
		}
		public string shippingPrinterName
		{
			get
			{
				if( settings.ContainsKey( "ShippingPrinter" ))
					return settings["ShippingPrinter"].ToString();
				else
					return "";
			}
			set
			{
				settings["ShippingPrinter"] = value;
			}
		}
		public string invoicePrinterName
		{
			get
			{
				if( settings.ContainsKey( "InvoicePrinter" ))
					return settings["InvoicePrinter"].ToString();
				else
					return "";
			}
			set
			{
				settings["InvoicePrinter"] = value;
			}
		}

		public string promoPrinterName_1
		{
			get
			{
				if( settings.ContainsKey( "PromoPrinter1" ))
					return settings["PromoPrinter1"].ToString();
				else
					return "";
			}
			set
			{
				settings["PromoPrinter1"] = value;
			}
		}
		public string promoCommand_1
		{
			get
			{
				if( settings.ContainsKey( "PromoCommand1" ))
					return settings["PromoCommand1"].ToString();
				else
					return "";
			}
			set
			{
				settings["PromoCommand1"] = value;
			}
		}
		public string promoComPortNumber_1
		{
			get
			{
				if( settings.ContainsKey( "PromoComPort1" ))
					return settings["PromoComPort1"].ToString();
				else
					// Return "None" if not selected yet... it also means we don't want to listen for anything.
					return "None";
			}
			set
			{
				settings["PromoComPort1"] = value;
			}
		}
		public string promoComTranslations_1
		{
			get
			{
				if( settings.ContainsKey( "PromoComTranslate1" ))
					return settings["PromoComTranslate1"].ToString();
				else
					return "";
			}
			set
			{
				settings["PromoComTranslate1"] = value;
			}
		}
		// The isolated storage stores the result in a string "yes" or "no" .. this property converts to bool for us.
		public bool promoActive_1
		{
			get
			{
				if( settings.ContainsKey( "PromoActive1" ))
					if( settings["PromoActive1"].ToString() == "no" )
						return false;
					else
						return true;
				else
					return true;
			}
			set
			{
				if( value )
					settings["PromoActive1"] = "yes";
				else
					settings["PromoActive1"] = "no";

			}
		}
	}

}
