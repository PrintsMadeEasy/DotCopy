// EmailGenerator.h : Haupt-Header-Datei f�r die Anwendung EMAILGENERATOR
//

#if !defined(AFX_EMAILGENERATOR_H__987C5FCC_3CBC_4399_84E2_3D8AAC98422F__INCLUDED_)
#define AFX_EMAILGENERATOR_H__987C5FCC_3CBC_4399_84E2_3D8AAC98422F__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

#ifndef __AFXWIN_H__
	#error include 'stdafx.h' before including this file for PCH
#endif

#include "resource.h"		// Hauptsymbole

/////////////////////////////////////////////////////////////////////////////
// CEmailGeneratorApp:
// Siehe EmailGenerator.cpp f�r die Implementierung dieser Klasse
//

class CEmailGeneratorApp : public CWinApp
{
public:
	CEmailGeneratorApp();

// �berladungen
	// Vom Klassenassistenten generierte �berladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CEmailGeneratorApp)
	public:
	virtual BOOL InitInstance();
	//}}AFX_VIRTUAL

// Implementierung

	//{{AFX_MSG(CEmailGeneratorApp)
		// HINWEIS - An dieser Stelle werden Member-Funktionen vom Klassen-Assistenten eingef�gt und entfernt.
		//    Innerhalb dieser generierten Quelltextabschnitte NICHTS VER�NDERN!
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};


/////////////////////////////////////////////////////////////////////////////

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ f�gt unmittelbar vor der vorhergehenden Zeile zus�tzliche Deklarationen ein.

#endif // !defined(AFX_EMAILGENERATOR_H__987C5FCC_3CBC_4399_84E2_3D8AAC98422F__INCLUDED_)
