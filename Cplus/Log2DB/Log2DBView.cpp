// Log2DBView.cpp : Implementierung der Klasse CLog2DBView
//

#include "stdafx.h"
#include "Log2DB.h"

#include "MySQLDB.h"

#include "Log2DBDoc.h"
#include "Log2DBView.h"

#ifdef _DEBUG
#define new DEBUG_NEW
#undef THIS_FILE
static char THIS_FILE[] = __FILE__;
#endif

/////////////////////////////////////////////////////////////////////////////
// CLog2DBView

IMPLEMENT_DYNCREATE(CLog2DBView, CFormView)

BEGIN_MESSAGE_MAP(CLog2DBView, CFormView)
	//{{AFX_MSG_MAP(CLog2DBView)
	ON_BN_CLICKED(IDC_BUTTON1, OnButton1)
	//}}AFX_MSG_MAP
	// Standard-Druckbefehle
	ON_COMMAND(ID_FILE_PRINT, CFormView::OnFilePrint)
	ON_COMMAND(ID_FILE_PRINT_DIRECT, CFormView::OnFilePrint)
	ON_COMMAND(ID_FILE_PRINT_PREVIEW, CFormView::OnFilePrintPreview)
END_MESSAGE_MAP()

/////////////////////////////////////////////////////////////////////////////
// CLog2DBView Konstruktion/Destruktion

CLog2DBView::CLog2DBView()
	: CFormView(CLog2DBView::IDD)
{
	//{{AFX_DATA_INIT(CLog2DBView)
		// HINWEIS: Der Klassenassistent fügt hier Member-Initialisierung ein
	//}}AFX_DATA_INIT
	// ZU ERLEDIGEN: Hier Code zur Konstruktion einfügen,

}

CLog2DBView::~CLog2DBView()
{
}

void CLog2DBView::DoDataExchange(CDataExchange* pDX)
{
	CFormView::DoDataExchange(pDX);
	//{{AFX_DATA_MAP(CLog2DBView)
		// HINWEIS: Der Klassenassistent fügt an dieser Stelle DDX- und DDV-Aufrufe ein
	//}}AFX_DATA_MAP
}

BOOL CLog2DBView::PreCreateWindow(CREATESTRUCT& cs)
{
	// ZU ERLEDIGEN: Ändern Sie hier die Fensterklasse oder das Erscheinungsbild, indem Sie
	//  CREATESTRUCT cs modifizieren.

	return CFormView::PreCreateWindow(cs);
}

void CLog2DBView::OnInitialUpdate()
{
	CFormView::OnInitialUpdate();
	GetParentFrame()->RecalcLayout();
	ResizeParentToFit();

}

/////////////////////////////////////////////////////////////////////////////
// CLog2DBView Drucken

BOOL CLog2DBView::OnPreparePrinting(CPrintInfo* pInfo)
{
	// Standardvorbereitung
	return DoPreparePrinting(pInfo);
}

void CLog2DBView::OnBeginPrinting(CDC* /*pDC*/, CPrintInfo* /*pInfo*/)
{
	// ZU ERLEDIGEN: Zusätzliche Initialisierung vor dem Drucken hier einfügen
}

void CLog2DBView::OnEndPrinting(CDC* /*pDC*/, CPrintInfo* /*pInfo*/)
{
	// ZU ERLEDIGEN: Hier Bereinigungsarbeiten nach dem Drucken einfügen
}

void CLog2DBView::OnPrint(CDC* pDC, CPrintInfo* /*pInfo*/)
{
	// ZU ERLEDIGEN: Benutzerdefinierten Code zum Ausdrucken hier einfügen
}

/////////////////////////////////////////////////////////////////////////////
// CLog2DBView Diagnose

#ifdef _DEBUG
void CLog2DBView::AssertValid() const
{
	CFormView::AssertValid();
}

void CLog2DBView::Dump(CDumpContext& dc) const
{
	CFormView::Dump(dc);
}

CLog2DBDoc* CLog2DBView::GetDocument() // Die endgültige (nicht zur Fehlersuche kompilierte) Version ist Inline
{
	ASSERT(m_pDocument->IsKindOf(RUNTIME_CLASS(CLog2DBDoc)));
	return (CLog2DBDoc*)m_pDocument;
}
#endif //_DEBUG

/////////////////////////////////////////////////////////////////////////////
// CLog2DBView Nachrichten-Handler


CTime DateString2CTime(CString sDate)
{
	if( (sDate.GetLength()==19) && (atoi(sDate.Mid(0,4))>1970) )
	{
		CTime tDate(atoi(sDate.Mid(0,4)),atoi(sDate.Mid(5,2)),atoi(sDate.Mid(8,2)),atoi(sDate.Mid(11,2)),atoi(sDate.Mid(14,2)),atoi(sDate.Mid(17,2)));
		return tDate;
	}
	else
	{
		return CTime(1972,1,1,0,0,0);
	}

return 0;
}



void CLog2DBView::UANextSecs(CString sTable)
{ 
	CMySQLDB db,db2; CString sSQL;
	db.Connect(mysqllogin,mysqlserver);
	db2.Connect(mysqllogin,mysqlserver);

	CString sError = db.GetErrorDescription();

	sSQL.Format("Update %s set UANextSecs = 99999, PageID=0",sTable);	
	db.Exec(sSQL.GetBuffer(500));
	db.Exec("COMMIT");


	sSQL.Format("select Agentcode from %s order by AgentCode DESC LIMIT 1",sTable);
	db.Select(sSQL.GetBuffer(500));

	int nMaxAgentcode = atoi(db.GetField("Agentcode")) + 1;

	int nPageID = 0;

	for(int nAgentCodeGesucht=1; nAgentCodeGesucht<nMaxAgentcode; nAgentCodeGesucht++)
	{

		TRACE("%d\n",nAgentCodeGesucht);

		nPageID++;

		int nLastID = -1;
		CString sLastDate = "";

		sSQL.Format("select Date,ID from %s where Agentcode = %d Order by ID",sTable,nAgentCodeGesucht);
		db.Select(sSQL.GetBuffer(500));

		while(!db.IsEOS())
		{
			CString sDate = db.GetField("Date");
			int nID = atoi(db.GetField("ID"));

			if(nLastID>-1)
			{
				CTime t1 = DateString2CTime(sLastDate);
				CTime t2 = DateString2CTime(sDate);

				CTimeSpan tD = t2-t1;

				int nSecs = tD.GetTotalSeconds();

				sSQL.Format("update %s set UANextSecs = %d, PageID = %d where ID = %d",sTable,nSecs,nPageID,nLastID);
				db2.Exec(sSQL.GetBuffer(5000));
			
				if(nSecs>=8)
				{
					nPageID++;
				}
			}

			sLastDate = sDate;
			nLastID = nID;

			db.FetchNext();
		}
	}


	for(nAgentCodeGesucht=1; nAgentCodeGesucht<nMaxAgentcode; nAgentCodeGesucht++)
	{
		
		sSQL.Format("select ID from %s where Agentcode = %d AND UANextSecs = 99999",sTable,nAgentCodeGesucht);
		db.Select(sSQL.GetBuffer(500));

		int nLastID = atoi(db.GetField("ID"));

		sSQL.Format("select PageID from %s where Agentcode = %d order by PageID DESC Limit 1",sTable,nAgentCodeGesucht);
		db.Select(sSQL.GetBuffer(500));

		int nLastPageID = atoi(db.GetField("PageID"));

		sSQL.Format("update %s set PageID = %d where ID = %d",sTable,nLastPageID,nLastID);
		db.Exec(sSQL.GetBuffer(500));
	}


	db.Close();
	db2.Close();
}



void CLog2DBView::AddAgentCode(CString sTable)
{ 
	CMySQLDB db; CString sSQL;
	db.Connect(mysqllogin,mysqlserver); int nAgentCode = 0;

	CString sError = db.GetErrorDescription();

	sSQL.Format("Update %s set Agentcode = 0",sTable);	
	db.Exec(sSQL.GetBuffer(5000));

	db.Select("select distinct (Agent) from logfile2");
	CStringArray sAAgent;
	while(!db.IsEOS())
	{
		CString gg = db.GetField("Agent");
		sAAgent.Add(db.GetField("Agent"));
		db.FetchNext();
	}

	for(int x=0; x<sAAgent.GetSize(); x++)
	{
		nAgentCode++;
		sSQL.Format("update %s set Agentcode = %d where Agent='%s'",sTable,nAgentCode,sAAgent.ElementAt(x));		
		TRACE("%s\n",sSQL);
		db.Exec(sSQL.GetBuffer(5000));
		db.Exec("COMMIT");
	}

	db.Close();
}


#define TRUNCATE 1
#define APPEND 0




void CLog2DBView::ImportFile(CString sFilename, CString sTable, int nTruncateFlag)
{ 
	CMySQLDB db; CString sSQL;
	db.Connect(mysqllogin,mysqlserver);

	CString sError = db.GetErrorDescription();


	if(nTruncateFlag==1)
	{
		sSQL.Format("Truncate table %s",sTable);	
		db.Exec(sSQL.GetBuffer(5000));
	}

	char * filebuffer = NULL; 
	{
		DWORD nFileSize = 0;
		CFileException er; CFile* pFileRead = new CFile();
		if(pFileRead->Open(sFilename, CFile::modeRead , &er)) 
		{
			DWORD nLen = pFileRead->GetLength();
			filebuffer = new char[nLen+10];
			nFileSize = pFileRead->Read(filebuffer,nLen);
			pFileRead->Close();
			filebuffer[nLen]=0;
		}   
		
		delete pFileRead;
	}	

	CString sNeuString = "";
	char sGF[2]; sGF[0]=34; sGF[1]=0;
	long int nLen = strlen(filebuffer);
	char line[50000];
	int nLineNr = 0;

	int nSavePos=-1; int nStringCount = 0;
	for(long int x=0; x<nLen; x++)	
	{
		if((filebuffer[x]==10) || (x==nLen-1) )
		{
			memcpy(line,filebuffer+nSavePos+1,x-nSavePos-1);
			line[x-nSavePos-2]=0;

			nLineNr++;

			CString sLine = line;

			if(sLine.GetLength()>60) 
			{
				int nIPPos = sLine.Find(" - - [",0);			
				CString sIP = sLine.Mid(0,nIPPos);

				int nDateStart = sLine.Find("[",nIPPos+3)+1;
				int nDateEnde = sLine.Find("]",nIPPos+3);

				CString sDate = sLine.Mid(nDateStart, nDateEnde-nDateStart);

				sDate.Replace("Jan","01");
				sDate.Replace("Feb","02");
				sDate.Replace("Mar","03");
				sDate.Replace("Apr","04");
				sDate.Replace("May","05");
				sDate.Replace("Jun","06");
				sDate.Replace("Jul","07");
				sDate.Replace("Aug","08");
				sDate.Replace("Sep","09");
				sDate.Replace("Oct","10");
				sDate.Replace("Nov","11");
				sDate.Replace("Dec","12");

				CString sDateSQL = sDate.Mid(6,4)+"-"+sDate.Mid(3,2)+"-"+sDate.Mid(0,2)+" "+sDate.Mid(11,2)+":"+sDate.Mid(14,2)+":"+sDate.Mid(17,2);

				int nFileStart = sLine.Find(CString(sGF),nDateEnde)+1;
				int nFileEnde = sLine.Find(" HTTP/",nFileStart);

				CString sFile = sLine.Mid(nFileStart, nFileEnde-nFileStart);

				sFile.Replace("'","");


				int nHTTPStatStart = sLine.Find(" ",nFileEnde+8)+1;
				int nHTTPStatEnde  = sLine.Find(" ",nHTTPStatStart+2);

				CString sHTTPStat = sLine.Mid(nHTTPStatStart, nHTTPStatEnde-nHTTPStatStart);
				
				int nSizeStart = sLine.Find(" ",nHTTPStatEnde)+1;
				int nSizeEnde  = sLine.Find(" ",nSizeStart);

				CString sSize = sLine.Mid(nSizeStart, nSizeEnde-nSizeStart);

				int nStrichStart = sLine.Find(sGF,nSizeEnde)+1;
				int nStrichEnde  = sLine.Find(sGF,nStrichStart+1);

				CString sStrich = sLine.Mid(nStrichStart, nStrichEnde-nStrichStart);

				int nUserAgentStart = sLine.Find(sGF,nStrichEnde+2)+1;
				int nUserAgentEnde  = sLine.Find(sGF,nUserAgentStart+1);

				CString sUserAgent = sLine.Mid(nUserAgentStart, nUserAgentEnde-nUserAgentStart);

				sUserAgent.Replace("'","");

				sLine="";

				sSQL.Format("insert into %s (IP,Date,FileName,Agent,HTTP,Size,Original,Referer) values ('%s','%s','%s','%s',%d,%d,'%s','%s')",sTable,sIP,sDateSQL,sFile,sUserAgent,atoi(sHTTPStat),atoi(sSize),sLine,sStrich);

				db.Exec(sSQL.GetBuffer(5000));

				int nID = db.GetLastInsertAutoIncID();
				
				if(int(nLineNr/1000)*1000==nLineNr)
				{
					TRACE("%8d  %8d %s\n", nID, nLineNr,sFilename);
				}

				CString sError = db.GetErrorDescription();

				if(sError!="SUCCESS")
				{
					TRACE("%d  %s\n",nLineNr,sError);
				}

				db.Exec("COMMIT");
			}

			nSavePos=x;
		}
	}

	delete [] filebuffer;

	db.Close();
}


void CLog2DBView::CompareLogs(int nGesuchtePageID, CString sTableSrc, CString sTableDst)
{ 
	CMySQLDB db; CString sSQL;
	db.Connect(mysqllogin,mysqlserver); int nAgentCode = 0;

	CString sError = db.GetErrorDescription();

//	db.Exec(CString("update "+sTableSrc+" set Other_DiffSecs = 0, Other_ID = 0, Other_IP = '', Other_Date = '', Other_PageID = 0").GetBuffer(500));
//	db.Exec(CString("update "+sTableDst+" set Other_DiffSecs = 0, Other_ID = 0, Other_IP = '', Other_Date = '', Other_PageID = 0").GetBuffer(500));
//	db.Exec("Truncate Table matchlog");
//	db.Exec("COMMIT");
	
	int nDeltaTimeGuess	 = 13;
	int nSecsRange	     = 20;

	// First Rec
	sSQL.Format("select * from %s where PageID = %d Order By ID Limit 1",sTableSrc,nGesuchtePageID);
	db.Select(sSQL.GetBuffer(500));

	int nSrcPageID = atoi(db.GetField("PageID"));
	
	if(nSrcPageID == nGesuchtePageID)
	{	
		int nSrcID			 = atoi(db.GetField("ID"));
		CString sSrcAgent	 = db.GetField("Agent");
		CString sSrcFilename = db.GetField("FileName");
		CString sSrcDate	 = db.GetField("Date");
	
		CTimeSpan tGuess = CTimeSpan(0,0,0,nDeltaTimeGuess);

		CTime tSrcDate = DateString2CTime(sSrcDate) + tGuess;
					
		CTimeSpan tRange = CTimeSpan(0,0,0,nSecsRange);

		CTime tDate1 = tSrcDate - tRange;
		CTime tDate2 = tSrcDate + tRange;

		CString sDate1 = tDate1.Format("20%y-%m-%d %H:%M:%S");
		CString sDate2 = tDate2.Format("20%y-%m-%d %H:%M:%S");;

		sSQL.Format("select * from %s where (Date > '%s') and  (Date < '%s' ) and Agent = '%s' and Filename = '%s'",sTableDst,sDate1,sDate2,sSrcAgent,sSrcFilename);
		db.Select(sSQL.GetBuffer(500));

		CDWordArray wAPageID, wAID;
		while(!db.IsEOS())
		{
			TRACE("%d %d \n",atoi(db.GetField("ID")),atoi(db.GetField("PageID")));
			
			wAPageID.Add(atoi(db.GetField("PageID")));
			wAID.Add(atoi(db.GetField("ID")));
			db.FetchNext();
		}

		if(wAPageID.GetSize()==1)
		{	
			int nDstPageID = wAPageID.ElementAt(0);

			CStringArray sASrcFileName,sADstFileName, sASrcDate,sADstDate,sASrcIP,sADstIP;
			CDWordArray wASrcID, wADstID, wASrcMatch, wADstMatch;
	
			sSQL.Format("select FileName,Date,IP,ID from %s where PageID = %d order by ID",sTableSrc,nSrcPageID);
			db.Select(sSQL.GetBuffer(500));

			while(!db.IsEOS())
			{
				sASrcFileName.Add(db.GetField("FileName"));
				sASrcDate.Add(db.GetField("Date"));
				sASrcIP.Add(db.GetField("IP"));
				wASrcID.Add(atoi(db.GetField("ID")));
				wASrcMatch.Add(-1);
				db.FetchNext();
			}

			sSQL.Format("select FileName,Date,IP,ID from %s where PageID = %d order by ID",sTableDst,nDstPageID);
			db.Select(sSQL.GetBuffer(500));

			while(!db.IsEOS())
			{
				sADstFileName.Add(db.GetField("FileName"));
				sADstDate.Add(db.GetField("Date"));
				sADstIP.Add(db.GetField("IP"));
				wADstID.Add(atoi(db.GetField("ID")));
				wADstMatch.Add(-1);
				db.FetchNext();
			}

			int nSrcCount = wASrcID.GetSize();
			int nDstCount = wADstID.GetSize();

			int nDelaySecsTotal = 0;

			for(int x=0; x<nSrcCount; x++)
			{
				CString sSrcFileName = sASrcFileName.ElementAt(x);

				for(int y=0; y<nDstCount; y++)
				{
					if(sSrcFileName == sADstFileName.ElementAt(y) && (wADstMatch[y]==-1) ) // Works with doble entries !
					{
						wADstMatch[y] = x;
						wASrcMatch[x] = y;

						CTime tSrcDate = DateString2CTime(sASrcDate.ElementAt(x));
						CTime tDstDate = DateString2CTime(sADstDate.ElementAt(y));
	
						CTimeSpan tDiff = tDstDate - tSrcDate;
			
						int nDiffSecsSrc = tDiff.GetTotalSeconds();
						int nDiffSecsDst = nDiffSecsSrc*-1;

						nDelaySecsTotal = nDelaySecsTotal + nDiffSecsSrc;

						sSQL.Format("update %s set Other_DiffSecs = %d, Other_ID = %d, Other_IP = '%s', Other_Date = '%s', Other_PageID = %d where ID = %d",sTableSrc,nDiffSecsSrc,wADstID.ElementAt(y),sADstIP.ElementAt(y),sADstDate.ElementAt(y),nDstPageID,wASrcID.ElementAt(x));
						db.Exec(sSQL.GetBuffer(500));

						sSQL.Format("update %s set Other_DiffSecs = %d, Other_ID = %d, Other_IP = '%s', Other_Date = '%s', Other_PageID = %d where ID = %d",sTableDst,nDiffSecsDst,wASrcID.ElementAt(x),sASrcIP.ElementAt(x),sASrcDate.ElementAt(x),nSrcPageID,wADstID.ElementAt(y));
						db.Exec(sSQL.GetBuffer(500));

						y = nDstCount + 10;
					}
				}
			}

			int nAnzahlSrcMatch = 0;
			for(int m=0; m<wASrcMatch.GetSize(); m++)
			{
				if(int(wASrcMatch.ElementAt(m)) > -1)
				{
					nAnzahlSrcMatch++;
				}
			}

			int nAnzahlDstMatch = 0;
			for(m=0; m<wADstMatch.GetSize(); m++)
			{
				if(int(wADstMatch.ElementAt(m)) > -1)
				{
					nAnzahlDstMatch++;
				}
			}
			
			double nPercentageMatch = 0;

			if(nDstCount+nSrcCount!=0)
			{
				nPercentageMatch = double(nAnzahlSrcMatch+nAnzahlDstMatch) / double(nDstCount+nSrcCount) * 100.00;
			}
			
			if(nSrcCount!=nDstCount)
			{
				nPercentageMatch = 0;
			}

			int nDelaySecsAvg = -1;
			if(nAnzahlSrcMatch!=0)
			{
				nDelaySecsAvg = (nDelaySecsTotal / nAnzahlSrcMatch) + 0.5;
			}

			sSQL.Format("insert into matchlog (Date,PageID1,PageID2,Anzahl1,Anzahl2,DiffSecs,PercentageMatch) values ('%s',%d,%d,%d,%d,%d,%d)",sSrcDate,nSrcPageID,nDstPageID,nSrcCount,nDstCount,nDelaySecsAvg,int(nPercentageMatch));
			db.Exec(sSQL.GetBuffer(500));
		}
		else
		{
			if(wAPageID.GetSize()==0)
			{
				sSQL.Format("insert into matchlog (PageID1) values (%d)",nSrcPageID);
				db.Exec(sSQL.GetBuffer(500));
			}

			if(wAPageID.GetSize()>1)
			{
				sSQL.Format("insert into matchlog (PageID1,PercentageMatch) values (%d,%d)",nSrcPageID,wAPageID.GetSize());
				db.Exec(sSQL.GetBuffer(500));
			}
		}
	}

	db.Close();
}



void CLog2DBView::OnButton1() 
{

//#define TRUNCATE 1
//#define APPEND 0

// Complete October
/*	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24-Logs-Texas\\BusinessCards24.com-ssl_log.1.001","logfile1",TRUNCATE);
	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24-Logs-Texas\\BusinessCards24.com-ssl_log.1.002","logfile1",APPEND);
	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24-Logs-Texas\\BusinessCards24.com-ssl_log.1.003","logfile1",APPEND);
	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24-Logs-Texas\\BusinessCards24.com-ssl_log.1.004","logfile1",APPEND);
	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24-Logs-Texas\\BusinessCards24.com-ssl_log.1.005","logfile1",APPEND);
*/

//  ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\businesscards24.com-access-3-30SEP-07OC_2010.log","logfile2",TRUNCATE);
// ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\businesscards24.com-access-07OCT-10OCT_2010.log","logfile2",APPEND);
// ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\cache-businesscards24.com-access-01SEP-05OCT_2010-CUT.log","logfile2",APPEND);





//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-19-07_37_07.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-20-22_10_20.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-21-13_49_58.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-22-09_24_30.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-22-23_48_50.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-23-23_45_47.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-25-05_01_20.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-26-22_25_09.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-28-07_37_36.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-28-07_40_19.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-28-07_57_21.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-28-17_01_11.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-28-18_32_36.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-10-31-00_36_00.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-access.2010-11-01-11_02_34.log","logfile2",APPEND);




//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-18-15_24_17.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-19-02_18_43.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-19-04_33_21.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-19-07_38_01.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-20-22_12_01.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-21-13_50_36.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-22-09_25_52.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-22-23_48_50.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-23-23_45_51.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-25-05_02_14.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-28-07_40_23.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-28-07_59_34.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-28-17_01_47.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-10-28-18_34_06.log","logfile2",APPEND);
//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\ZippedSSL\\businesscards24.com-access-ssl.2010-11-01-11_02_34.log","logfile2",APPEND);


//	ImportFile("E:\\BackupProxyServer\\All-until-2010-11-01\\BC24logs\\Zipped\\businesscards24.com-27097-Bis-1NOVaccess.log","logfile2",APPEND);


	


/*
	AddAgentCode("logfile1");
	UANextSecs("logfile1");
	AddAgentCode("logfile2");
	UANextSecs("logfile2");
*/



//	int x=58;
/*
	// Loop PageID's of source table
	for(int x=1; x<7000; x++)
	{
		TRACE("%d\n",x);
		
		CompareLogs(x,"logfile1", "logfile2");
	}
  
*/
}


/*

// Database to create: comparelogs

CREATE TABLE `matchlog` (
  `ID` int(11) NOT NULL auto_increment,
  `Date` datetime NOT NULL,
  `PageID1` int(11) NOT NULL,
  `PageID2` int(11) NOT NULL,
  `Anzahl1` int(11) NOT NULL,
  `Anzahl2` int(11) NOT NULL,
  `DiffSecs` int(11) NOT NULL,
  `PercentageMatch` int(11) NOT NULL,
  PRIMARY KEY  (`ID`)
);


CREATE TABLE `logfile1` (
  `ID` int(11) NOT NULL auto_increment,
  `AgentCode` int(11) NOT NULL,
  `IP` varchar(25) NOT NULL,
  `Referer` text NOT NULL,
  `UANextSecs` int(11) NOT NULL,
  `PageID` int(11) NOT NULL,
  `Original` text NOT NULL,
  `Date` datetime NOT NULL,
  `FileName` text NOT NULL,
  `Agent` varchar(255) NOT NULL,
  `HTTP` int(11) NOT NULL,
  `Size` int(11) NOT NULL,
  `Other_ID` int(11) NOT NULL,
  `Other_IP` varchar(25) NOT NULL,
  `Other_Date` datetime NOT NULL,
  `Other_DiffSecs` int(11) NOT NULL,
  `Other_PageID` int(11) NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `index1` (`Date`),
  KEY `index2` (`AgentCode`),
  KEY `index3` (`Agent`),
  KEY `index4` (`PageID`)
);


CREATE TABLE `logfile2` (
  `ID` int(11) NOT NULL auto_increment,
  `AgentCode` int(11) NOT NULL,
  `IP` varchar(25) NOT NULL,
  `Referer` text NOT NULL,
  `UANextSecs` int(11) NOT NULL,
  `PageID` int(11) NOT NULL,
  `Original` text NOT NULL,
  `Date` datetime NOT NULL,
  `FileName` text NOT NULL,
  `Agent` varchar(255) NOT NULL,
  `HTTP` int(11) NOT NULL,
  `Size` int(11) NOT NULL,
  `Other_ID` int(11) NOT NULL,
  `Other_IP` varchar(25) NOT NULL,
  `Other_Date` datetime NOT NULL,
  `Other_DiffSecs` int(11) NOT NULL,
  `Other_PageID` int(11) NOT NULL,
  PRIMARY KEY  (`ID`),
  KEY `index1` (`Date`),
  KEY `index2` (`AgentCode`),
  KEY `index3` (`Agent`),
  KEY `index4` (`PageID`)
);


*/