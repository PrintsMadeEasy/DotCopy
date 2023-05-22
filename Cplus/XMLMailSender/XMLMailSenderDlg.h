// XMLMailSenderDlg.h : Header-Datei
//

#if !defined(AFX_XMLMAILSENDERDLG_H__A4466F75_927E_46C9_A309_57A4989335B9__INCLUDED_)
#define AFX_XMLMAILSENDERDLG_H__A4466F75_927E_46C9_A309_57A4989335B9__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

/////////////////////////////////////////////////////////////////////////////
// CXMLMailSenderDlg Dialogfeld

class CXMLMailSenderDlg : public CDialog
{
// Konstruktion
public:
	CXMLMailSenderDlg(CWnd* pParent = NULL);	// Standard-Konstruktor

// Dialogfelddaten
	//{{AFX_DATA(CXMLMailSenderDlg)
	enum { IDD = IDD_XMLMAILSENDER_DIALOG };
	CString	m_sEmail;
	CString	m_sSubject;
	CString	m_sBody;
	//}}AFX_DATA

	// Vom Klassenassistenten generierte Überladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CXMLMailSenderDlg)
	protected:
	virtual void DoDataExchange(CDataExchange* pDX);	// DDX/DDV-Unterstützung
	//}}AFX_VIRTUAL

// Implementierung
protected:
	HICON m_hIcon;

	// Generierte Message-Map-Funktionen
	//{{AFX_MSG(CXMLMailSenderDlg)
	virtual BOOL OnInitDialog();
	afx_msg void OnSysCommand(UINT nID, LPARAM lParam);
	afx_msg void OnPaint();
	afx_msg HCURSOR OnQueryDragIcon();
	afx_msg void OnButton1();
	afx_msg void OnButton2();
	afx_msg void OnSendsingleemail();
	afx_msg void OnButton4();
	afx_msg void OnTimer(UINT nIDEvent);
	afx_msg void OnButton3();
	afx_msg void OnButton5();
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ fügt unmittelbar vor der vorhergehenden Zeile zusätzliche Deklarationen ein.

#endif // !defined(AFX_XMLMAILSENDERDLG_H__A4466F75_927E_46C9_A309_57A4989335B9__INCLUDED_)
