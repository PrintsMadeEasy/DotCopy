// Log2DB.h : Haupt-Header-Datei f�r die Anwendung LOG2DB
//

#if !defined(AFX_LOG2DB_H__917101C3_6BB9_4E7C_A24B_CC6042BDA0E2__INCLUDED_)
#define AFX_LOG2DB_H__917101C3_6BB9_4E7C_A24B_CC6042BDA0E2__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

#ifndef __AFXWIN_H__
	#error include 'stdafx.h' before including this file for PCH
#endif

#include "resource.h"       // Hauptsymbole

/////////////////////////////////////////////////////////////////////////////
// CLog2DBApp:
// Siehe Log2DB.cpp f�r die Implementierung dieser Klasse
//

class CLog2DBApp : public CWinApp
{
public:
	CLog2DBApp();

// �berladungen
	// Vom Klassenassistenten generierte �berladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CLog2DBApp)
	public:
	virtual BOOL InitInstance();
	//}}AFX_VIRTUAL

// Implementierung
	//{{AFX_MSG(CLog2DBApp)
	afx_msg void OnAppAbout();
		// HINWEIS - An dieser Stelle werden Member-Funktionen vom Klassen-Assistenten eingef�gt und entfernt.
		//    Innerhalb dieser generierten Quelltextabschnitte NICHTS VER�NDERN!
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};


/////////////////////////////////////////////////////////////////////////////

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ f�gt unmittelbar vor der vorhergehenden Zeile zus�tzliche Deklarationen ein.

#endif // !defined(AFX_LOG2DB_H__917101C3_6BB9_4E7C_A24B_CC6042BDA0E2__INCLUDED_)
