// Log2DBDoc.h : Schnittstelle der Klasse CLog2DBDoc
//
/////////////////////////////////////////////////////////////////////////////

#if !defined(AFX_LOG2DBDOC_H__8E717441_4C9D_4FDF_9110_A651F2FD9DB2__INCLUDED_)
#define AFX_LOG2DBDOC_H__8E717441_4C9D_4FDF_9110_A651F2FD9DB2__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000


class CLog2DBDoc : public CDocument
{
protected: // Nur aus Serialisierung erzeugen
	CLog2DBDoc();
	DECLARE_DYNCREATE(CLog2DBDoc)

// Attribute
public:

// Operationen
public:

// Überladungen
	// Vom Klassenassistenten generierte Überladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CLog2DBDoc)
	public:
	virtual BOOL OnNewDocument();
	virtual void Serialize(CArchive& ar);
	//}}AFX_VIRTUAL

// Implementierung
public:
	virtual ~CLog2DBDoc();
#ifdef _DEBUG
	virtual void AssertValid() const;
	virtual void Dump(CDumpContext& dc) const;
#endif

protected:

// Generierte Message-Map-Funktionen
protected:
	//{{AFX_MSG(CLog2DBDoc)
		// HINWEIS - An dieser Stelle werden Member-Funktionen vom Klassen-Assistenten eingefügt und entfernt.
		//    Innerhalb dieser generierten Quelltextabschnitte NICHTS VERÄNDERN!
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};

/////////////////////////////////////////////////////////////////////////////

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ fügt unmittelbar vor der vorhergehenden Zeile zusätzliche Deklarationen ein.

#endif // !defined(AFX_LOG2DBDOC_H__8E717441_4C9D_4FDF_9110_A651F2FD9DB2__INCLUDED_)
