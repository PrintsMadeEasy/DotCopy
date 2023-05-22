using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;

namespace PME_Queue
{
	/// <summary>
	/// Summary description for Form2.
	/// </summary>
	public class ConfirmPrinting : System.Windows.Forms.Form
	{
		private System.Windows.Forms.Label label1;
		private System.Windows.Forms.TextBox textBox1;
		private System.Windows.Forms.Label label2;
		private System.Windows.Forms.Button button1;
		private System.Windows.Forms.Button button2;
		private string printerName;
		private string confirmationNumber;
		private string passwordOverride;
		private string jobIDref;
		private System.Windows.Forms.TextBox textBox2;
		private System.Windows.Forms.Label label3;
		private System.Windows.Forms.Label label4;
		private System.Windows.Forms.TextBox textBox3;

		/// <summary>
		/// Required designer variable.
		/// </summary>
		private System.ComponentModel.Container components = null;

		public ConfirmPrinting()
		{
			//
			// Required for Windows Form Designer support
			//
			InitializeComponent();

			//
			// TODO: Add any constructor code after InitializeComponent call
			//
			this.printerName = "";
			this.confirmationNumber = "";
			this.passwordOverride = "";
			this.jobIDref = "";
		}

		public void DisplayPrintCount(string pgCnt)
		{

			this.label1.Text = "Confirm that " + pgCnt + " pages were printed successfully.";
			this.textBox1.Text = this.printerName;
			this.textBox2.Text = "";
			this.textBox3.Text = "";
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
			this.label2 = new System.Windows.Forms.Label();
			this.button1 = new System.Windows.Forms.Button();
			this.button2 = new System.Windows.Forms.Button();
			this.textBox2 = new System.Windows.Forms.TextBox();
			this.label3 = new System.Windows.Forms.Label();
			this.label4 = new System.Windows.Forms.Label();
			this.textBox3 = new System.Windows.Forms.TextBox();
			this.SuspendLayout();
			// 
			// label1
			// 
			this.label1.Font = new System.Drawing.Font("Microsoft Sans Serif", 7.8F, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, ((System.Byte)(0)));
			this.label1.Location = new System.Drawing.Point(16, 16);
			this.label1.Name = "label1";
			this.label1.Size = new System.Drawing.Size(344, 24);
			this.label1.TabIndex = 0;
			this.label1.Text = "Confirm Message";
			// 
			// textBox1
			// 
			this.textBox1.Location = new System.Drawing.Point(128, 48);
			this.textBox1.Name = "textBox1";
			this.textBox1.Size = new System.Drawing.Size(144, 22);
			this.textBox1.TabIndex = 1;
			this.textBox1.Text = "textBox1";
			this.textBox1.TextChanged += new System.EventHandler(this.textBox1_TextChanged);
			this.textBox1.KeyUp += new System.Windows.Forms.KeyEventHandler(this.textBox1_KeyUp);
			// 
			// label2
			// 
			this.label2.Location = new System.Drawing.Point(24, 48);
			this.label2.Name = "label2";
			this.label2.Size = new System.Drawing.Size(96, 24);
			this.label2.TabIndex = 2;
			this.label2.Text = "Your Name   >";
			// 
			// button1
			// 
			this.button1.Location = new System.Drawing.Point(88, 152);
			this.button1.Name = "button1";
			this.button1.Size = new System.Drawing.Size(56, 32);
			this.button1.TabIndex = 4;
			this.button1.Text = "OK";
			this.button1.Click += new System.EventHandler(this.button1_Click);
			// 
			// button2
			// 
			this.button2.Location = new System.Drawing.Point(176, 152);
			this.button2.Name = "button2";
			this.button2.Size = new System.Drawing.Size(88, 32);
			this.button2.TabIndex = 5;
			this.button2.Text = "Cancel";
			this.button2.Click += new System.EventHandler(this.button2_Click);
			// 
			// textBox2
			// 
			this.textBox2.Location = new System.Drawing.Point(128, 80);
			this.textBox2.Name = "textBox2";
			this.textBox2.Size = new System.Drawing.Size(80, 22);
			this.textBox2.TabIndex = 2;
			this.textBox2.Text = "textBox2";
			this.textBox2.KeyUp += new System.Windows.Forms.KeyEventHandler(this.textBox2_KeyUp);
			// 
			// label3
			// 
			this.label3.Location = new System.Drawing.Point(24, 80);
			this.label3.Name = "label3";
			this.label3.Size = new System.Drawing.Size(96, 24);
			this.label3.TabIndex = 6;
			this.label3.Text = "Confirmation >";
			// 
			// label4
			// 
			this.label4.Location = new System.Drawing.Point(16, 112);
			this.label4.Name = "label4";
			this.label4.Size = new System.Drawing.Size(96, 24);
			this.label4.TabIndex = 7;
			this.label4.Text = "Override >";
			this.label4.TextAlign = System.Drawing.ContentAlignment.TopRight;
			this.label4.Click += new System.EventHandler(this.label4_Click);
			// 
			// textBox3
			// 
			this.textBox3.Location = new System.Drawing.Point(128, 112);
			this.textBox3.Name = "textBox3";
			this.textBox3.PasswordChar = '*';
			this.textBox3.Size = new System.Drawing.Size(80, 22);
			this.textBox3.TabIndex = 3;
			this.textBox3.Text = "";
			this.textBox3.KeyUp += new System.Windows.Forms.KeyEventHandler(this.textBox3_KeyUp);
			// 
			// ConfirmPrinting
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(6, 15);
			this.ClientSize = new System.Drawing.Size(304, 199);
			this.Controls.Add(this.textBox3);
			this.Controls.Add(this.label4);
			this.Controls.Add(this.label3);
			this.Controls.Add(this.textBox2);
			this.Controls.Add(this.button2);
			this.Controls.Add(this.button1);
			this.Controls.Add(this.label2);
			this.Controls.Add(this.textBox1);
			this.Controls.Add(this.label1);
			this.MaximizeBox = false;
			this.MinimizeBox = false;
			this.Name = "ConfirmPrinting";
			this.Text = "Confirm Printing";
			this.Load += new System.EventHandler(this.ConfirmPrinting_Load);
			this.ResumeLayout(false);

		}
		#endregion

		private void button2_Click(object sender, System.EventArgs e)
		{
			this.Close();
		}

		private void button1_Click(object sender, System.EventArgs e)
		{
			this.submitNameAndContinue();
		}

		private void textBox1_KeyUp(object sender, System.Windows.Forms.KeyEventArgs e)
		{
			if(e.KeyCode == Keys.Enter)
				this.submitNameAndContinue();
		}
	
		private void submitNameAndContinue()
		{

			if(this.jobIDref == "")
			{
				Form1.ShowError("You forgot to set the Job ID before calling this method");
				return;
			}
			if(this.textBox1.Text == "")
			{
				Form1.ShowError("Please enter your name or intitials before continuing.");
				return;
			}
			this.printerName = this.textBox1.Text;
			this.confirmationNumber = this.textBox2.Text;
			this.passwordOverride = this.textBox3.Text;

			Form1 frm1Ref = (Form1) this.Owner;

			frm1Ref.PrintingConfirmed(this.jobIDref, this.printerName, this.confirmationNumber, this.passwordOverride);

			this.Close();
		}

		private void ConfirmPrinting_Load(object sender, System.EventArgs e)
		{

		}

		private void textBox1_TextChanged(object sender, System.EventArgs e)
		{
		
		}

		private void textBox2_KeyUp(object sender, System.Windows.Forms.KeyEventArgs e)
		{
			if(e.KeyCode == Keys.Enter)
				this.submitNameAndContinue();
		}

		private void label4_Click(object sender, System.EventArgs e)
		{
		
		}

		private void textBox3_KeyUp(object sender, System.Windows.Forms.KeyEventArgs e)
		{
			if(e.KeyCode == Keys.Enter)
				this.submitNameAndContinue();
		}

		public string jobID
		{
			set
			{
				this.jobIDref = value;
			}
		}
	}
}
