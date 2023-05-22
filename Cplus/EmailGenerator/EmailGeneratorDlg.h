// EmailGeneratorDlg.h : Header-Datei
//

#if !defined(AFX_EMAILGENERATORDLG_H__61C6C31D_ADF0_4072_AFE9_7BEDCB59A9D0__INCLUDED_)
#define AFX_EMAILGENERATORDLG_H__61C6C31D_ADF0_4072_AFE9_7BEDCB59A9D0__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

/////////////////////////////////////////////////////////////////////////////
// CEmailGeneratorDlg Dialogfeld

class CEmailGeneratorDlg : public CDialog
{
// Konstruktion
public:
	CEmailGeneratorDlg(CWnd* pParent = NULL);	// Standard-Konstruktor

// Dialogfelddaten
	//{{AFX_DATA(CEmailGeneratorDlg)
	enum { IDD = IDD_EMAILGENERATOR_DIALOG };
		// HINWEIS: der Klassenassistent fügt an dieser Stelle Datenelemente (Members) ein
	//}}AFX_DATA

	// Vom Klassenassistenten generierte Überladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CEmailGeneratorDlg)
	protected:
	virtual void DoDataExchange(CDataExchange* pDX);	// DDX/DDV-Unterstützung
	//}}AFX_VIRTUAL

// Implementierung
protected:
	HICON m_hIcon;

	// Generierte Message-Map-Funktionen
	//{{AFX_MSG(CEmailGeneratorDlg)
	virtual BOOL OnInitDialog();
	afx_msg void OnSysCommand(UINT nID, LPARAM lParam);
	afx_msg void OnPaint();
	afx_msg HCURSOR OnQueryDragIcon();
	afx_msg void OnButton1();
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ fügt unmittelbar vor der vorhergehenden Zeile zusätzliche Deklarationen ein.

#endif // !defined(AFX_EMAILGENERATORDLG_H__61C6C31D_ADF0_4072_AFE9_7BEDCB59A9D0__INCLUDED_)
