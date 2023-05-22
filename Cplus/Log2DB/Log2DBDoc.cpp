// Log2DBDoc.cpp : Implementierung der Klasse CLog2DBDoc
//

#include "stdafx.h"
#include "Log2DB.h"
#include "Log2DBDoc.h"

#ifdef _DEBUG
#define new DEBUG_NEW
#undef THIS_FILE
static char THIS_FILE[] = __FILE__;
#endif

/////////////////////////////////////////////////////////////////////////////
// CLog2DBDoc

IMPLEMENT_DYNCREATE(CLog2DBDoc, CDocument)

BEGIN_MESSAGE_MAP(CLog2DBDoc, CDocument)
	//{{AFX_MSG_MAP(CLog2DBDoc)
		// HINWEIS - Hier werden Mapping-Makros vom Klassen-Assistenten eingefügt und entfernt.
		//    Innerhalb dieser generierten Quelltextabschnitte NICHTS VERÄNDERN!
	//}}AFX_MSG_MAP
END_MESSAGE_MAP()

/////////////////////////////////////////////////////////////////////////////
// CLog2DBDoc Konstruktion/Destruktion

CLog2DBDoc::CLog2DBDoc()
{
	// ZU ERLEDIGEN: Hier Code für One-Time-Konstruktion einfügen

}

CLog2DBDoc::~CLog2DBDoc()
{
}

BOOL CLog2DBDoc::OnNewDocument()
{
	if (!CDocument::OnNewDocument())
		return FALSE;

	// ZU ERLEDIGEN: Hier Code zur Reinitialisierung einfügen
	// (SDI-Dokumente verwenden dieses Dokument)

	return TRUE;
}



/////////////////////////////////////////////////////////////////////////////
// CLog2DBDoc Serialisierung

void CLog2DBDoc::Serialize(CArchive& ar)
{
	if (ar.IsStoring())
	{
		// ZU ERLEDIGEN: Hier Code zum Speichern einfügen
	}
	else
	{
		// ZU ERLEDIGEN: Hier Code zum Laden einfügen
	}
}

/////////////////////////////////////////////////////////////////////////////
// CLog2DBDoc Diagnose

#ifdef _DEBUG
void CLog2DBDoc::AssertValid() const
{
	CDocument::AssertValid();
}

void CLog2DBDoc::Dump(CDumpContext& dc) const
{
	CDocument::Dump(dc);
}
#endif //_DEBUG

/////////////////////////////////////////////////////////////////////////////
// CLog2DBDoc Befehle
