#if !defined(AFX_XMLMAILSENDER_H__5C0AB9FC_FE97_456A_8540_9796616FDE04__INCLUDED_)
#define AFX_XMLMAILSENDER_H__5C0AB9FC_FE97_456A_8540_9796616FDE04__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

#ifndef __AFXWIN_H__
	#error include 'stdafx.h' before including this file for PCH
#endif

#include "resource.h"		// Hauptsymbole

class CXMLMailSenderApp : public CWinApp
{
public:
	CXMLMailSenderApp();

// Überladungen
	// Vom Klassenassistenten generierte Überladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CXMLMailSenderApp)
	public:
	virtual BOOL InitInstance();
	//}}AFX_VIRTUAL

// Implementierung

	//{{AFX_MSG(CXMLMailSenderApp)
		// HINWEIS - An dieser Stelle werden Member-Funktionen vom Klassen-Assistenten eingefügt und entfernt.
		//    Innerhalb dieser generierten Quelltextabschnitte NICHTS VERÄNDERN!
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};


//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ fügt unmittelbar vor der vorhergehenden Zeile zusätzliche Deklarationen ein.

#endif // !defined(AFX_XMLMAILSENDER_H__5C0AB9FC_FE97_456A_8540_9796616FDE04__INCLUDED_)
