// MySQLDB.cpp: Implementierung der Klasse CMySQLDB.
//
//////////////////////////////////////////////////////////////////////

#include "stdafx.h"
#include "MySQLDB.h"

#ifdef _DEBUG
#undef THIS_FILE
static char THIS_FILE[]=__FILE__;
#define new DEBUG_NEW
#endif

int nOpenConnections=0;


//////////////////////////////////////////////////////////////////////
// Konstruktion/Destruktion
//////////////////////////////////////////////////////////////////////

CMySQLDB::CMySQLDB()
{
	bDatabaseConnected = false;
	bDatabaseSelected  = false;
}


CMySQLDB::~CMySQLDB()
{
	if(bDatabaseConnected)
	{
		if(bDatabaseSelected)
		{
			mysql_free_result(result);
		}
		mysql_close(&mysql);
	}
}


char * CMySQLDB::GetField(char * fieldname)
{
	if(bDatabaseConnected)
	{
		if(bDatabaseSelected)
		{
			MYSQL_FIELD * field;
			char * ptrRow = NULL;

			int nRow=-1;
			for(unsigned int nr=0; nr<mysql_num_fields(result); nr++)
			{
				field = mysql_fetch_field_direct(result,nr);
				if(strcmp(field->name,fieldname)==0) 
				{
					nRow=nr;
					break;
				} 
			}

			if(nRow>-1)
			{
				ptrRow = row[nRow];	// Test ob NULL, dann wenn NULL nicht übergeben

				if(ptrRow==NULL)
				{
					return "";
				}
				else
				{
					return row[nRow];
				}			
			}
			else
			{
				return ""; 
			}
		}
		else
		{
			return ""; 
		}
	}
	 else
	{
		return ""; 
	} 
}


bool CMySQLDB::Connect(char * sLoginstring, char * sHost)
{
try
{
	char loginstring[100], host[100], user[100],password[100],database[100], ip[100], port[7]; int start=0; 	bDatabaseConnected=false; 

	strcpy(loginstring,sLoginstring);
	strcpy(host,sHost);

	for(unsigned int x=0; x<strlen(loginstring); x++)
	{
		if(loginstring[x]=='/')
		{
			memcpy(user,loginstring,x);
			user[x]=0; start=x+1;
		}

		if(loginstring[x]=='@')
		{
			memcpy(password,loginstring+start,x-start);
			password[x-start]=0; 
			memcpy(database,loginstring+x+1,strlen(loginstring)-x-1);
			database[strlen(loginstring)-x-1]=0;
		}
	}

	start=0;
	{
		for(unsigned int x=0; x<strlen(host); x++)
		{
			if(host[x]==':')
			{
				memcpy(ip,host,x);
				ip[x]=0; 
				memcpy(port,host+x+1,strlen(host)-x-1);
				port[strlen(host)-x-1]=0;
			}
		}
	}

	mysql_init(&mysql);
	connection=mysql_real_connect(&mysql,ip,user,password,database,atoi(port),0,0);
	if(connection!=NULL) 
	{

		bDatabaseConnected=true;
		nOpenConnections++;
	}
	else
	{

#ifdef LOGMYSQL


		char cError[1000];
		strcpy(cError,mysql_error(&mysql));
		if(strcmp(cError,"")!=0) 
		{
			FILE * pFile;
			if(pFile = fopen ("sql_connect_error.txt","a"))
			{
				fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+cError+"\n",pFile);
				fclose (pFile);
			}
		}

#endif

		int nVersuch = 0;

		for(int t=0; t<20; t++)
		{
			nVersuch++;

			Sleep(100+(t*300));

			connection=mysql_real_connect(&mysql,ip,user,password,database,atoi(port),0,0);

			if(connection!=NULL) 
			{
				bDatabaseConnected=true;
				nOpenConnections++;
				t=100;
			}


#ifdef LOGMYSQL

			if(strcmp(cError,"")!=0) 
			{
				FILE * pFile;
				if(pFile = fopen ("sql_re_connect_error.txt","a"))
				{
					CString sV; sV.Format("%d",nVersuch);

					fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+sV+"  "+cError+"\n",pFile);
					fclose (pFile);
				}
			}
#endif

		}
	}

	bResult=false;

} catch(...) { TRACE("ERROR IN MYSQL CONNECT\n");}
return bDatabaseConnected;
}


void CMySQLDB::Close()
{
	if(bDatabaseConnected)
	{
		if(bResult==true)
		{
			mysql_free_result(result); bResult=false;
		}

		mysql_close(&mysql);
		bDatabaseConnected=false;
		bDatabaseSelected=false;
		nOpenConnections--;
	}
}


bool CMySQLDB::Select(char * sqlquery)
{
int state=0;
try
{
	if(bDatabaseConnected)
	{
		bDatabaseSelected=false;
		state = mysql_query(connection,sqlquery);

#ifdef LOGMYSQL

		FILE * pFile;
		if(pFile = fopen ("sql_select.txt","a"))
		{

			CString sOC; sOC.Format("%2d",nOpenConnections);
			fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+sOC+"  "+sqlquery+"\n",pFile);
			fclose (pFile);
		}

		char cError[1000];
		strcpy(cError,mysql_error(&mysql));
		if(strcmp(cError,"")!=0) 
		{
			FILE * pFile;
			if(pFile = fopen ("sql_select_error.txt","a"))
			{
				fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+CString(cError)+"**"+CString(sqlquery)+"\n",pFile);
				fclose (pFile);
			}
		}

#endif
		if(state==0)
		{
			bDatabaseSelected=true;

			if(bResult==true)
			{
				mysql_free_result(result);
				bResult=false;
			}

			result = mysql_store_result(connection); bResult=true;
			row = mysql_fetch_row(result);

			if(row==NULL) {bDatabaseSelected=false;} 

			nRowFetched=0;
		}
	}
} catch(...) { TRACE("ERROR IN MYSQL SELECT\n");	}
return !(state+1);
}


int CMySQLDB::Exec(char * sqlquery)
{
	int state=0; __int64 nRowsEffected=-1;

	try
	{

	if(bResult==true)
	{
		mysql_free_result(result);
		bResult=false;
	}

	if(bDatabaseConnected)
	{
		bDatabaseSelected=false;

		state = mysql_query(connection,sqlquery);

#ifdef LOGMYSQL

		FILE * pFile;
		if(pFile = fopen ("sql_exec.txt","a"))
		{
			CString sOC; sOC.Format("%2d",nOpenConnections);
			fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+sOC+"  "+sqlquery+"\n",pFile);
			fclose (pFile);
		}

		char cError[1000];
		strcpy(cError,mysql_error(&mysql));
		if(strcmp(cError,"")!=0) 
		{
			FILE * pFile;
			if(pFile = fopen ("sql_exec_error.txt","a"))
			{
				fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+CString(cError)+"**"+CString(sqlquery)+"\n",pFile);
				fclose (pFile);
			}
		}
#endif
		nRowsEffected = mysql_affected_rows(&mysql);

		if(state==0)
		{
		
		}
	}

} catch(...) { TRACE("ERROR IN EXEC %s\n",CString(sqlquery).Mid(0,500)); }
return 	int(nRowsEffected);
}


__int64 CMySQLDB::GetLastInsertAutoIncID()
{
	return mysql_insert_id(&mysql);
}


void CMySQLDB::FetchNext()
{
	if(bDatabaseSelected)
	{
		row = mysql_fetch_row(result);

#ifdef LOGMYSQL

		char cError[1000];
		strcpy(cError,mysql_error(&mysql));
		if(strcmp(cError,"")!=0) 
		{
			FILE * pFile;
			if(pFile = fopen ("sql_fetch_error.txt","a"))
			{
				fputs (CTime::GetCurrentTime().Format("20%y-%m-%d %H:%M:%S")+"  "+CString(cError)+"\n",pFile);
				fclose (pFile);
			}
		}
#endif

		nRowFetched++;
	}
}


bool CMySQLDB::IsEOS()
{
	bool bIsEOS=false;

	if(bDatabaseSelected)
	{
		if(nRowFetched>=result->row_count)
		{
			bIsEOS=true;
		}
	}
	 else
	{
		bIsEOS=true;
	}
	return bIsEOS;
}


CString CMySQLDB::GetErrorDescription()
{	
	CString sError = mysql_error(&mysql);
	if(sError=="") {sError="SUCCESS";}
	return sError;
}


int CMySQLDB::GetSelectCount(char * cSelect)
{
	int state = mysql_query(connection,cSelect);

	if(bResult==true)
	{
		mysql_free_result(result);
		bResult=false;
	}

	result = mysql_store_result(connection); bResult=true;

	int nCount = (int)result->row_count;

	if(bResult==true)
	{
		mysql_free_result(result);
		bResult=false;
	}

	return nCount;
}