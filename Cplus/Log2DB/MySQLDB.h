// MySQLDB.h: Schnittstelle für die Klasse CMySQLDB.
//
//////////////////////////////////////////////////////////////////////

#if !defined(AFX_MYSQLDB_H__18287962_FF54_42B1_B365_E11EFD3DCE29__INCLUDED_)
#define AFX_MYSQLDB_H__18287962_FF54_42B1_B365_E11EFD3DCE29__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

#include "C:\\mysql\\include\\mysql.h"

class CMySQLDB  
{
public:

	CMySQLDB();
	virtual ~CMySQLDB();
	
	__int64 GetLastInsertAutoIncID();

	bool Connect(char * sLoginstring, char * sHost);
	bool Select(char * sqlquery);
	int Exec(char * sqlquery);
	char * GetField(char * fieldname);
	bool IsEOS();
	void FetchNext();
	void Close();
	int GetSelectCount(char * cSelect);
	CString GetErrorDescription();

protected:

	MYSQL * connection, mysql;
	MYSQL_RES * result;
	MYSQL_ROW row;
	bool bDatabaseConnected, bDatabaseSelected, bResult;
	unsigned int nRowFetched;
};

#endif // !defined(AFX_MYSQLDB_H__18287962_FF54_42B1_B365_E11EFD3DCE29__INCLUDED_)
