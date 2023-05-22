// DKIM.cpp: Implementierung der Klasse CDKIM.
//
//////////////////////////////////////////////////////////////////////

#include "stdafx.h"
#include "DKIM.h"
#include "Sha1.h"

#ifdef _DEBUG
#undef THIS_FILE
static char THIS_FILE[]=__FILE__;
#define new DEBUG_NEW
#endif


//////////////////////////////////////////////////////////////////////
// Base64
//////////////////////////////////////////////////////////////////////

// Digits...
static char Base64Digits[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

BOOL CBase64DKIM::m_Init    = FALSE;
char CBase64DKIM::m_DecodeTable[256];

#ifndef PAGESIZE
#define PAGESIZE          4096
#endif

#ifndef ROUNDTOPAGE
#define ROUNDTOPAGE(a)      (((a/4096)+1)*4096)
#endif

CBase64DKIM::CBase64DKIM()
: m_pDBuffer(NULL),
m_pEBuffer(NULL),
m_nDBufLen(0),
m_nEBufLen(0)
{
  
}

CBase64DKIM::~CBase64DKIM()
{
  if(m_pDBuffer != NULL)
    delete [] m_pDBuffer;
  
  if(m_pEBuffer != NULL)
    delete [] m_pEBuffer;
}

LPCSTR CBase64DKIM::DecodedMessage() const 
{ 
  return (LPCSTR) m_pDBuffer;
}

char * CBase64DKIM::DecodedMessage2() const 
{ 
  return (char*) m_pDBuffer;
}

LPCSTR CBase64DKIM::EncodedMessage() const
{ 
  return (LPCSTR) m_pEBuffer;
}

void CBase64DKIM::AllocEncode(DWORD nSize)
{
  if(m_nEBufLen < nSize)
  {
    if(m_pEBuffer != NULL)
      delete [] m_pEBuffer;
    
    m_nEBufLen = ROUNDTOPAGE(nSize);
    m_pEBuffer = new BYTE[m_nEBufLen];
  }
  
  ::ZeroMemory(m_pEBuffer, m_nEBufLen);
  m_nEDataLen = 0;
}

void CBase64DKIM::AllocDecode(DWORD nSize)
{
  if(m_nDBufLen < nSize)
  {
    if(m_pDBuffer != NULL)
      delete [] m_pDBuffer;
    
    m_nDBufLen = ROUNDTOPAGE(nSize);
    m_pDBuffer = new BYTE[m_nDBufLen];
  }
  
  ::ZeroMemory(m_pDBuffer, m_nDBufLen);
  m_nDDataLen = 0;
}

void CBase64DKIM::SetEncodeBuffer(const PBYTE pBuffer, DWORD nBufLen)
{
  DWORD i = 0;
  
  AllocEncode(nBufLen);
  while(i < nBufLen)
  {
    if(!_IsBadMimeChar(pBuffer[i]))
    {
      m_pEBuffer[m_nEDataLen] = pBuffer[i];
      m_nEDataLen++;
    }
    
    i++;
  }
}

void CBase64DKIM::SetDecodeBuffer(const PBYTE pBuffer, DWORD nBufLen)
{
  AllocDecode(nBufLen);
  ::CopyMemory(m_pDBuffer, pBuffer, nBufLen);
  m_nDDataLen = nBufLen;
}

void CBase64DKIM::Encode(const PBYTE pBuffer, DWORD nBufLen)
{
  SetDecodeBuffer(pBuffer, nBufLen);
  AllocEncode(nBufLen * 2);
  
  TempBucket      Raw;
  DWORD         nIndex  = 0;
  
  while((nIndex + 3) <= nBufLen)
  {
    Raw.Clear();
    ::CopyMemory(&Raw, m_pDBuffer + nIndex, 3);
    Raw.nSize = 3;
    _EncodeToBuffer(Raw, m_pEBuffer + m_nEDataLen);
    nIndex    += 3;
    m_nEDataLen += 4;
  }
  
  if(nBufLen > nIndex)
  {
    Raw.Clear();
    Raw.nSize = (BYTE) (nBufLen - nIndex);
    ::CopyMemory(&Raw, m_pDBuffer + nIndex, nBufLen - nIndex);
    _EncodeToBuffer(Raw, m_pEBuffer + m_nEDataLen);
    m_nEDataLen += 4;
  }
}

void CBase64DKIM::Encode(LPCSTR szMessage)
{
  if(szMessage != NULL)
    CBase64DKIM::Encode((const PBYTE)szMessage, lstrlenA(szMessage));
}

void CBase64DKIM::Decode(const PBYTE pBuffer, DWORD dwBufLen)
{
  if(!CBase64DKIM::m_Init)
    _Init();
  
  SetEncodeBuffer(pBuffer, dwBufLen);
  
  AllocDecode(dwBufLen);
  
  TempBucket      Raw;
  
  DWORD   nIndex = 0;
  
  while((nIndex + 4) <= m_nEDataLen)
  {
    Raw.Clear();
    Raw.nData[0] = CBase64DKIM::m_DecodeTable[m_pEBuffer[nIndex]];
    Raw.nData[1] = CBase64DKIM::m_DecodeTable[m_pEBuffer[nIndex + 1]];
    Raw.nData[2] = CBase64DKIM::m_DecodeTable[m_pEBuffer[nIndex + 2]];
    Raw.nData[3] = CBase64DKIM::m_DecodeTable[m_pEBuffer[nIndex + 3]];
    
    if(Raw.nData[2] == 255)
      Raw.nData[2] = 0;
    if(Raw.nData[3] == 255)
      Raw.nData[3] = 0;
    
    Raw.nSize = 4;
    _DecodeToBuffer(Raw, m_pDBuffer + m_nDDataLen);
    nIndex += 4;
    m_nDDataLen += 3;
  }
  
  // If nIndex < m_nEDataLen, then we got a decode message without padding.
  // We may want to throw some kind of warning here, but we are still required
  // to handle the decoding as if it was properly padded.
  if(nIndex < m_nEDataLen)
  {
    Raw.Clear();
    for(DWORD i = nIndex; i < m_nEDataLen; i++)
    {
      Raw.nData[i - nIndex] = CBase64DKIM::m_DecodeTable[m_pEBuffer[i]];
      Raw.nSize++;
      if(Raw.nData[i - nIndex] == 255)
        Raw.nData[i - nIndex] = 0;
    }
    
    _DecodeToBuffer(Raw, m_pDBuffer + m_nDDataLen);
    m_nDDataLen += (m_nEDataLen - nIndex);
  }
}


DWORD CBase64DKIM::GetDecodedSize()
{
	return m_nDDataLen;
}

void CBase64DKIM::Decode(LPCSTR szMessage)
{
  if(szMessage != NULL)
    CBase64DKIM::Decode((const PBYTE)szMessage, lstrlenA(szMessage));
}

DWORD CBase64DKIM::_DecodeToBuffer(const TempBucket &Decode, PBYTE pBuffer)
{
  TempBucket  Data;
  DWORD     nCount = 0;
  
  _DecodeRaw(Data, Decode);
  
  for(int i = 0; i < 3; i++)
  {
    pBuffer[i] = Data.nData[i];
    if(pBuffer[i] != 255)
      nCount++;
  }
  
  return nCount;
}


void CBase64DKIM::_EncodeToBuffer(const TempBucket &Decode, PBYTE pBuffer)
{
  TempBucket  Data;
  
  _EncodeRaw(Data, Decode);
  
  for(int i = 0; i < 4; i++)
    pBuffer[i] = Base64Digits[Data.nData[i]];
  
  switch(Decode.nSize)
  {
  case 1:
    pBuffer[2] = '=';
  case 2:
    pBuffer[3] = '=';
  }
}

void CBase64DKIM::_DecodeRaw(TempBucket &Data, const TempBucket &Decode)
{
  BYTE    nTemp;
  
  Data.nData[0] = Decode.nData[0];
  Data.nData[0] <<= 2;
  
  nTemp = Decode.nData[1];
  nTemp >>= 4;
  nTemp &= 0x03;
  Data.nData[0] |= nTemp;
  
  Data.nData[1] = Decode.nData[1];
  Data.nData[1] <<= 4;
  
  nTemp = Decode.nData[2];
  nTemp >>= 2;
  nTemp &= 0x0F;
  Data.nData[1] |= nTemp;
  
  Data.nData[2] = Decode.nData[2];
  Data.nData[2] <<= 6;
  nTemp = Decode.nData[3];
  nTemp &= 0x3F;
  Data.nData[2] |= nTemp;
}

void CBase64DKIM::_EncodeRaw(TempBucket &Data, const TempBucket &Decode)
{
  BYTE    nTemp;
  
  Data.nData[0] = Decode.nData[0];
  Data.nData[0] >>= 2;
  
  Data.nData[1] = Decode.nData[0];
  Data.nData[1] <<= 4;
  nTemp = Decode.nData[1];
  nTemp >>= 4;
  Data.nData[1] |= nTemp;
  Data.nData[1] &= 0x3F;
  
  Data.nData[2] = Decode.nData[1];
  Data.nData[2] <<= 2;
  
  nTemp = Decode.nData[2];
  nTemp >>= 6;
  
  Data.nData[2] |= nTemp;
  Data.nData[2] &= 0x3F;
  
  Data.nData[3] = Decode.nData[2];
  Data.nData[3] &= 0x3F;
}

BOOL CBase64DKIM::_IsBadMimeChar(BYTE nData)
{
  switch(nData)
  {
  case '\r': case '\n': case '\t': case ' ' :
  case '\b': case '\a': case '\f': case '\v':
    return TRUE;
  default:
    return FALSE;
  }
}

void CBase64DKIM::_Init()
{  // Initialize Decoding table.
  
  int i;
  
  for(i = 0; i < 256; i++)
    CBase64DKIM::m_DecodeTable[i] = -2;
  
  for(i = 0; i < 64; i++)
  {
    CBase64DKIM::m_DecodeTable[Base64Digits[i]]     = (CHAR)i;
    CBase64DKIM::m_DecodeTable[Base64Digits[i]|0x80]  = (CHAR)i;
  }
  
  CBase64DKIM::m_DecodeTable['=']       = -1;
  CBase64DKIM::m_DecodeTable['='|0x80]    = -1;
  
  CBase64DKIM::m_Init = TRUE;
}



//////////////////////////////////////////////////////////////////////
// DKIM
//////////////////////////////////////////////////////////////////////

CDKIM::CDKIM()
{

}

CDKIM::~CDKIM()
{

}


CString CDKIM::SignHeader(CString sSelector, CString sToBeSigned)
{
	CString dest="/test/vc-dkim-sign.php";
	CString HostName="www.asynx-planetarium.com";

	CString Base64Response = "";

	CBase64DKIM base64; 	
	base64.Encode(sToBeSigned);	CString sDKIM_ToBeSigned64	= base64.EncodedMessage();
	base64.Encode(sSelector);	CString sDKIM_SelectorBase64 = base64.EncodedMessage();

	CString Data="s="+sDKIM_SelectorBase64+"&tobesigned="+sDKIM_ToBeSigned64;

	char cContentLength[100];
	
	CInternetSession iSession("DKIM-OUTSOURCED-2");
	CString verb="POST";
	CString retHeader;

	CString header;
	header="Content-Type: application/x-www-form-urlencoded\r\n";
	sprintf(cContentLength,"Content-Length: %d \r\n",Data.GetLength()); header+=cContentLength;
	header+="Host: ";
	header+=HostName;
	header+="\r\n";

	CHttpConnection *hSession=NULL; CHttpFile *hFile=NULL;

	hSession=iSession.GetHttpConnection(HostName, 0, 80, 0, 0);

	if (hSession)
	{
		hFile=hSession->OpenRequest(verb, dest, 0, 1, 0, "HTTP/1.1", INTERNET_FLAG_RELOAD|INTERNET_FLAG_NO_CACHE_WRITE );
		if (hFile)
		{
			hFile->AddRequestHeaders(header);
			if (Data.GetLength()!=0)
			{
				hFile->SendRequestEx(Data.GetLength());
				hFile->WriteString(Data);
				hFile->EndRequest();
			}
			else
			{
				hFile->SendRequest();
			}

			hFile->QueryInfo(HTTP_QUERY_STATUS_CODE , retHeader, 0);
			
			if (retHeader=="200")
			{
				CString buff;

				while (hFile->ReadString(buff))
				{
					Base64Response+=buff;
					Base64Response+="\n";
					buff.Empty();
				}
			}
		}
	}
	if (hFile)
	{
		hFile->Close();
		delete(hFile);
	}
	if (hSession)
	{
		hSession->Close();
		delete (hSession);
	}

	base64.Decode(Base64Response);
	return base64.DecodedMessage();
}


CString CDKIM::QuotedPrintable(CString txt) 
{
	CString line=""; char cOrd[5];

	// Nüesch geht nicht ü

	for (int i=0;i<txt.GetLength();i++) 
	{
		int nOrd = (int)txt[i];

	    if ( ((0x21 <= nOrd) && (nOrd <= 0x3A)) || (nOrd==0x3C) || ((0x3E <= nOrd) && (nOrd<= 0x7E)) )
		{
	        line += txt[i];
		}
	    else
		{
			sprintf(cOrd,"%02X",nOrd);
	        line +="="+CString(cOrd);
		}
	}

	return line;
}

	

CString CDKIM::SimpleHeaderCanonicalization(CString s) 
{
	return s ;
}


CString CDKIM::RelaxedHeaderCanonicalization(CString s) 
{

/*

Input:

From: Christian Nuesch <christian@asynx.com>
To: christian_nuesch@yahoo.com
Subject: Hello this is a Test
DKIM-Signature: v=1; a=rsa-sha1; q=dns/txt; l=35; s=b24;
	t=1292429808; c=relaxed/simple;
	h=From:To:Subject;
	d=businesscards24.com; i=@businesscards24.com ;
	z=From:=20Christian=20Nuesch=20<christian@asynx.com>
	|To:=20christian_nuesch@yahoo.com
	|Subject:=20Hello=20this=20is=20a=20Test;
	bh=dfexsh4lH6O/p2upO1DEwjGCKZw=;
	b=


Output:

from:Christian Nuesch <christian@asynx.com>
to:christian_nuesch@yahoo.com
subject:Hello this is a Test
dkim-signature:v=1; a=rsa-sha1; q=dns/txt; l=35; s=b24; t=1292429808; c=relaxed/simple; h=From:To:Subject; d=businesscards24.com; i=@businesscards24.com ; z=From:=20Christian=20Nuesch=20<christian@asynx.com> |To:=20christian_nuesch@yahoo.com |Subject:=20Hello=20this=20is=20a=20Test; bh=dfexsh4lH6O/p2upO1DEwjGCKZw=; b=

*/

	bool bEnd = false; CString sOut="", sLine;
	int nStartPos = 0, nCRT=0;

	s += "\r\n";

	while(!bEnd)
	{
		int nEndPos = s.Find("\r\n",nStartPos);
		int nEndPosT = s.Find("\r\n\t",nStartPos);

		if(nEndPos==-1) 
		{
			bEnd = true; 
		}

		if(nEndPos==nEndPosT)
		{
			nCRT=3;
		} 
		else
		{
			nCRT=2;	
		}

		sLine = s.Mid(nStartPos, nEndPos - nStartPos + nCRT);

		if(sLine.Find("\r\n\t",0)>-1) 
		{
			sLine.Replace("\r\n\t"," ");
		}
		else
		{
			int nCPos = sLine.Find(":",0);
			if(nCPos>-1)
			{
				CString sKey = sLine.Mid(0,nCPos); CString sKeyLower=sKey; sKeyLower.MakeLower();
				sLine.Replace(sKey,sKeyLower);
				sLine.Replace(": ",":");
			}
		}

		nStartPos = nEndPos + nCRT;	
		sOut+=sLine;
	}

	// Fine tuning
	sOut.TrimRight("\r\n");
	sOut.Replace("DKIM-Signature:","dkim-signature:");
	sOut.Replace(": ",":"); 
	sOut.Replace("  "," "); 
	sOut.Replace("  "," "); 

	return sOut ;
}


CString CDKIM::SimpleBodyCanonicalization(CString body) 
{
	if(body.GetLength()>4)
	{
		CString sBodyLast = body.Mid(body.GetLength()-4,4);
		if(sBodyLast=="\r\n\r\n")
		{
			body = body.Mid(0,body.GetLength()-2);
		}
	}

	return body;	// \r\n ok in Windows	
}


CString CDKIM::GenerateSha1(CString sBody)
{
	CSha1 sha; unsigned char binSha1sum[25]; 

	int nBodyLen = sBody.GetLength();

	unsigned char * buf = NULL; 
	buf = new unsigned char[nBodyLen+25];
	memset(buf,0,nBodyLen+25);
	memcpy(buf,sBody,nBodyLen);

	sha.RunSha(buf,nBodyLen,binSha1sum);

	delete[] buf;

	int nLen = strlen((char*)binSha1sum);

	CBase64DKIM base64; base64.Encode((char*)binSha1sum); 

	CString sReturn = base64.EncodedMessage();

	if(nLen!=20) 
	{
		sReturn = "";
	}

	return sReturn;
}


CString CDKIM::AddDkimToHeaders(CString sSelector, CString sDomain, CString headers_line,CString body) 
{
	CString defaultIdentity  = "@" + sDomain; 
	CString algorithm		 = "rsa-sha1"; 
	CString canonicalization = "relaxed/simple"; 
	CString queryMethod		 = "dns/txt";
	CString timestamp; timestamp.Format("%d",CTime::GetCurrentTime()); 

	// Test
	// timestamp = "1292429808"; 

	CString from_header="",to_header="",subject_header="";

	int nPos = headers_line.Find("From: ",0);

	if(nPos>-1)
	{
		int nPos2 = headers_line.Find("\r\n",nPos);
		if(nPos2>-1)
		{
			from_header = headers_line.Mid(nPos, nPos2-nPos);
		}
	}
		
	nPos = headers_line.Find("To: ",0);
	if(nPos>-1)
	{
		int nPos2 = headers_line.Find("\r\n",nPos);
		if(nPos2>-1)
		{
			to_header = headers_line.Mid(nPos, nPos2-nPos);
		}
	}
		
	nPos = headers_line.Find("Subject: ",0);
	if(nPos>-1)
	{
		int nPos2 = headers_line.Find("\r\n",nPos);
		if(nPos2>-1)
		{
			subject_header = headers_line.Mid(nPos, nPos2-nPos);
		}
	}
		
	CString from	= QuotedPrintable(from_header);    from.Replace("|","=7C");
	CString to		= QuotedPrintable(to_header);      to.Replace("|","=7C");
	CString subject	= QuotedPrintable(subject_header); subject.Replace("|","=7C");
	
	body	= SimpleBodyCanonicalization(body) ;
	
/*
	CFileException e;
	CFile* pFileW = new CFile();
	if(pFileW->Open("C:\\body1.txt", CFile::modeWrite | CFile::modeCreate, &e)) 
	{
		pFileW->Write(body,body.GetLength());
		pFileW->Close();
	}   delete pFileW;
*/

	int bodyLength	= body.GetLength() ;
	CString sBodyLength; sBodyLength.Format("%d", bodyLength);

	CString binaryHash = GenerateSha1(body);  binaryHash.Replace("\n","");
		
	int nBinaryHashLen = binaryHash.GetLength();

	if(nBinaryHashLen==0)
	{
		return "";
	}
	else
	{
		CString identity = " i=" + defaultIdentity + " ;";
		
		CString dkim = "DKIM-Signature: v=1; a="+algorithm+"; q="+queryMethod+"; l="+sBodyLength+"; s="+sSelector+";\r\n"+
			"\tt="+timestamp+"; c="+canonicalization+";\r\n"+
			"\th=From:To:Subject;\r\n"+
			"\td="+sDomain+";"+identity+"\r\n"+
			"\tz="+from+"\r\n"+
			"\t|"+to+"\r\n"+
			"\t|"+subject+";\r\n"+
			"\tbh="+binaryHash+";\r\n"+
			"\tb=";
		
		CString to_be_signed = RelaxedHeaderCanonicalization(from_header+"\r\n"+to_header+"\r\n"+subject_header+"\r\n"+dkim) ;

		CString signedDkim   = SignHeader(sSelector,to_be_signed) ;
		
		return dkim + signedDkim;
	}
}



void CDKIM::test()
{
	CString sHeader = "Date: Thu, 16 Dec 2010 9:57:30 +0100\r\nSubject: Hello this is a Test\r\nTo: christian@pjupii.com\r\nFrom: Christian Nuesch <christian@asynx.com>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative;\r\n";
	CString sTest3 = AddDkimToHeaders("B24","businesscards24.com",sHeader,"Das ist der body"); 
}