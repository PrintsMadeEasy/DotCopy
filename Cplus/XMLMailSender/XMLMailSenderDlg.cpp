// XMLMailSenderDlg.cpp : Implementierungsdatei
//

#include "stdafx.h"
#include "XMLMailSender.h"
#include "XMLMailSenderDlg.h"

#include "EmailSender.h"
#include "Pop3Thread.h"
#include "config.inc"
#include "SMTPSErverThread.h"

#include "SMTPMine.h"
#include "DKIM.h"


#include <stdio.h>
#include <string.h>

#ifdef _DEBUG
#define new DEBUG_NEW
#undef THIS_FILE
static char THIS_FILE[] = __FILE__;
#endif

extern int nSmtpSplitCount;
extern int nPop3SplitCount;
extern int n1SplitCount;
extern int n2SplitCount;

/////////////////////////////////////////////////////////////////////////////
// CAboutDlg-Dialogfeld für Anwendungsbefehl "Info"

class CAboutDlg : public CDialog
{
public:
	CAboutDlg();

// Dialogfelddaten
	//{{AFX_DATA(CAboutDlg)
	enum { IDD = IDD_ABOUTBOX };
	//}}AFX_DATA

	// Vom Klassenassistenten generierte Überladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CAboutDlg)
	protected:
	virtual void DoDataExchange(CDataExchange* pDX);    // DDX/DDV-Unterstützung
	//}}AFX_VIRTUAL

// Implementierung
protected:
	//{{AFX_MSG(CAboutDlg)
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};

CAboutDlg::CAboutDlg() : CDialog(CAboutDlg::IDD)
{
	//{{AFX_DATA_INIT(CAboutDlg)
	//}}AFX_DATA_INIT
}

void CAboutDlg::DoDataExchange(CDataExchange* pDX)
{
	CDialog::DoDataExchange(pDX);
	//{{AFX_DATA_MAP(CAboutDlg)
	//}}AFX_DATA_MAP
}

BEGIN_MESSAGE_MAP(CAboutDlg, CDialog)
	//{{AFX_MSG_MAP(CAboutDlg)
		// Keine Nachrichten-Handler
	//}}AFX_MSG_MAP
END_MESSAGE_MAP()

/////////////////////////////////////////////////////////////////////////////
// CXMLMailSenderDlg Dialogfeld

CXMLMailSenderDlg::CXMLMailSenderDlg(CWnd* pParent /*=NULL*/)
	: CDialog(CXMLMailSenderDlg::IDD, pParent)
{
	//{{AFX_DATA_INIT(CXMLMailSenderDlg)
	m_sEmail = _T("christian@printsmadeeasy.com");
	m_sSubject = _T("Hello this is a Test");
	m_sBody = _T("Hi, this is Test Email ...");
	//}}AFX_DATA_INIT
	// Beachten Sie, dass LoadIcon unter Win32 keinen nachfolgenden DestroyIcon-Aufruf benötigt
	m_hIcon = AfxGetApp()->LoadIcon(IDR_MAINFRAME);
}

void CXMLMailSenderDlg::DoDataExchange(CDataExchange* pDX)
{
	CDialog::DoDataExchange(pDX);
	//{{AFX_DATA_MAP(CXMLMailSenderDlg)
	DDX_Text(pDX, IDC_EMAIL, m_sEmail);
	DDX_Text(pDX, IDC_SUBJECT, m_sSubject);
	DDX_Text(pDX, IDC_BODY, m_sBody);
	//}}AFX_DATA_MAP
}

BEGIN_MESSAGE_MAP(CXMLMailSenderDlg, CDialog)
	//{{AFX_MSG_MAP(CXMLMailSenderDlg)
	ON_WM_SYSCOMMAND()
	ON_WM_PAINT()
	ON_WM_QUERYDRAGICON()
	ON_BN_CLICKED(IDC_BUTTON1, OnButton1)
	ON_BN_CLICKED(IDC_BUTTON2, OnButton2)
	ON_BN_CLICKED(IDC_SENDSINGLEEMAIL, OnSendsingleemail)
	ON_BN_CLICKED(IDC_BUTTON4, OnButton4)
	ON_WM_TIMER()
	ON_BN_CLICKED(IDC_BUTTON3, OnButton3)
	ON_BN_CLICKED(IDC_BUTTON5, OnButton5)
	//}}AFX_MSG_MAP
END_MESSAGE_MAP()

/////////////////////////////////////////////////////////////////////////////
// CXMLMailSenderDlg Nachrichten-Handler


void CXMLMailSenderDlg::OnSysCommand(UINT nID, LPARAM lParam)
{
	if ((nID & 0xFFF0) == IDM_ABOUTBOX)
	{
		CAboutDlg dlgAbout;
		dlgAbout.DoModal();
	}
	else
	{
		CDialog::OnSysCommand(nID, lParam);
	}
}

// Wollen Sie Ihrem Dialogfeld eine Schaltfläche "Minimieren" hinzufügen, benötigen Sie 
//  den nachstehenden Code, um das Symbol zu zeichnen. Für MFC-Anwendungen, die das 
//  Dokument/Ansicht-Modell verwenden, wird dies automatisch für Sie erledigt.

void CXMLMailSenderDlg::OnPaint() 
{
	if (IsIconic())
	{
		CPaintDC dc(this); // Gerätekontext für Zeichnen

		SendMessage(WM_ICONERASEBKGND, (WPARAM) dc.GetSafeHdc(), 0);

		// Symbol in Client-Rechteck zentrieren
		int cxIcon = GetSystemMetrics(SM_CXICON);
		int cyIcon = GetSystemMetrics(SM_CYICON);
		CRect rect;
		GetClientRect(&rect);
		int x = (rect.Width() - cxIcon + 1) / 2;
		int y = (rect.Height() - cyIcon + 1) / 2;

		// Symbol zeichnen
		dc.DrawIcon(x, y, m_hIcon);
	}
	else
	{
		CDialog::OnPaint();
	}
}

// Die Systemaufrufe fragen den Cursorform ab, die angezeigt werden soll, während der Benutzer
//  das zum Symbol verkleinerte Fenster mit der Maus zieht.
HCURSOR CXMLMailSenderDlg::OnQueryDragIcon()
{
	return (HCURSOR) m_hIcon;
}


BOOL CXMLMailSenderDlg::OnInitDialog()
{
	CDialog::OnInitDialog();
	ASSERT((IDM_ABOUTBOX & 0xFFF0) == IDM_ABOUTBOX);
	ASSERT(IDM_ABOUTBOX < 0xF000);
	CMenu* pSysMenu = GetSystemMenu(FALSE);
	if (pSysMenu != NULL)
	{
		CString strAboutMenu;
		strAboutMenu.LoadString(IDS_ABOUTBOX);
		if (!strAboutMenu.IsEmpty())
		{	
			pSysMenu->AppendMenu(MF_SEPARATOR);
			pSysMenu->AppendMenu(MF_STRING, IDM_ABOUTBOX, strAboutMenu);
		}
	}
	SetIcon(m_hIcon, TRUE);			// Großes Symbol verwenden
	SetIcon(m_hIcon, FALSE);		// Kleines Symbol verwenden

	LoadConfig();
	LoadDenyfile();

	nSmtpSplitCount = 0;
	nPop3SplitCount = 0;
	n1SplitCount = 0;
	n2SplitCount = 0;

	SetTimer(1312,100,NULL);

	SetTimer(2502,10000,NULL);

	SetTimer(2503,600100,NULL); // Try resend check all 10 Minutes


	return TRUE;  // Geben Sie TRUE zurück, außer ein Steuerelement soll den Fokus erhalten
}

void CXMLMailSenderDlg::OnButton1() 
{

}

void CXMLMailSenderDlg::OnButton2() 
{
	EmailSender es;
	es.ReadXML();
}

void CXMLMailSenderDlg::OnSendsingleemail() 
{
	UpdateData(TRUE);
	EmailSender es;
	CString sAnswer = es.SendSingleEmail(m_sEmail, m_sSubject, m_sBody);

	AfxMessageBox("Email sent");
}

void CXMLMailSenderDlg::OnButton4() 
{
	EmailSender es;
	es.DownloadXML();
	AfxMessageBox("XML Loaded");
}

void CXMLMailSenderDlg::OnTimer(UINT nIDEvent) 
{
	if(nIDEvent==2502) 
	{
		EmailSender es;
		es.StartSendingAllLocalEmails();
	}


	if(nIDEvent==2503) 
	{
		EmailSender es;
		es.StartSendUndeliverableLocalEmails();
	}


	if(nIDEvent==1312) 
	{
		KillTimer(1312);

		if(strlen(g_szConfigDomain)==0)
		{
			AfxMessageBox("config.txt is missing in executing directory !");
		}

		if(g_nConfigRunSMTPServer==1)
		{
			CSMTPThread smtpserver;
			smtpserver.StartSMTPServerThread();
		}

		if(g_nConfigRunPop3Server==1)
		{
			CPop3Thread pop3;
			pop3.StartPOP3Thread();
		}
	}
	
	CDialog::OnTimer(nIDEvent);
}

void CXMLMailSenderDlg::OnButton3() 
{
	EmailSender es;
//	CString sAnswer = es.SendSingleEmailTest();	


	CString sXML = es.DecryptBlowFish("WAIiMv5J8U/CvSKkK1L9soPnxoHqbP26");

	//es.DecodeBase64();

}


void CXMLMailSenderDlg::OnButton5() 
{
	EmailSender es;
//	es.StartSendUndeliverableLocalEmails();
	es.StartSendingAllLocalEmails();
}
