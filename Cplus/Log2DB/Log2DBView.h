// Log2DBView.h : Schnittstelle der Klasse CLog2DBView
//
/////////////////////////////////////////////////////////////////////////////

#if !defined(AFX_LOG2DBVIEW_H__38127DDF_D5BF_4E72_B0DD_A04D73B01F1E__INCLUDED_)
#define AFX_LOG2DBVIEW_H__38127DDF_D5BF_4E72_B0DD_A04D73B01F1E__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000


class CLog2DBView : public CFormView
{
protected: // Nur aus Serialisierung erzeugen
	CLog2DBView();
	DECLARE_DYNCREATE(CLog2DBView)

public:
	//{{AFX_DATA(CLog2DBView)
	enum{ IDD = IDD_LOG2DB_FORM };
		// HINWEIS: der Klassenassistent fügt an dieser Stelle Datenelemente (Members) ein
	//}}AFX_DATA

// Attribute
public:
	CLog2DBDoc* GetDocument();


	void ImportFile(CString sFilename, CString sTable, int nTruncateFlag);
	void AddAgentCode(CString sTable);
	void UANextSecs(CString sTable);
	
	void CompareLogs(int nGesuchtePageID, CString sTableSrc, CString sTableDst);


// Operationen
public:

// Überladungen
	// Vom Klassenassistenten generierte Überladungen virtueller Funktionen
	//{{AFX_VIRTUAL(CLog2DBView)
	public:
	virtual BOOL PreCreateWindow(CREATESTRUCT& cs);
	protected:
	virtual void DoDataExchange(CDataExchange* pDX);    // DDX/DDV-Unterstützung
	virtual void OnInitialUpdate(); // das erste mal nach der Konstruktion aufgerufen
	virtual BOOL OnPreparePrinting(CPrintInfo* pInfo);
	virtual void OnBeginPrinting(CDC* pDC, CPrintInfo* pInfo);
	virtual void OnEndPrinting(CDC* pDC, CPrintInfo* pInfo);
	virtual void OnPrint(CDC* pDC, CPrintInfo* pInfo);
	//}}AFX_VIRTUAL

// Implementierung
public:
	virtual ~CLog2DBView();
#ifdef _DEBUG
	virtual void AssertValid() const;
	virtual void Dump(CDumpContext& dc) const;
#endif

protected:

// Generierte Message-Map-Funktionen
protected:
	//{{AFX_MSG(CLog2DBView)
	afx_msg void OnButton1();
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};

#ifndef _DEBUG  // Testversion in Log2DBView.cpp
inline CLog2DBDoc* CLog2DBView::GetDocument()
   { return (CLog2DBDoc*)m_pDocument; }
#endif

/////////////////////////////////////////////////////////////////////////////

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ fügt unmittelbar vor der vorhergehenden Zeile zusätzliche Deklarationen ein.

#endif // !defined(AFX_LOG2DBVIEW_H__38127DDF_D5BF_4E72_B0DD_A04D73B01F1E__INCLUDED_)
