// CBase64.h: interface for the CBase64 class.
//
//////////////////////////////////////////////////////////////////////

#if !defined(AFX_CBase64_H__B2E45717_0625_11D2_A80A_00C04FB6794C__INCLUDED_)
#define AFX_CBase64_H__B2E45717_0625_11D2_A80A_00C04FB6794C__INCLUDED_

#if _MSC_VER >= 1000
#pragma once
#endif // _MSC_VER >= 1000

#pragma comment(lib,"wsock32.lib")

#include <atlbase.h>
#include <winsock.h>
#include <string>

#pragma warning(disable : 4786)
#include <iostream>

#include <map>
#include <Windns.h>		// DNS definitions and DNS API.
#pragma comment(lib, "Dnsapi.lib")

/*
 
  Dnsapi.lib and Windns.h are included in the Windows SDK

  Installation here:

  http://www.microsoft.com/downloads/details.aspx?FamilyId=A55B6B43-E24F-4EA3-A93E-40C0EC4F68E5&displaylang=en

  In the Directories (Extras->Optionen-Verzeichnisse)

  Include: C:\PROGRAM FILES\MICROSOFT SDKS\WINDOWS\V6.1\INCLUDE
  Include: C:\PROGRAM FILES\MICROSOFT VISUAL STUDIO 9.0\VC\INCLUDE
  Lib    : C:\PROGRAM FILES\MICROSOFT SDKS\WINDOWS\V6.1\LIB	

  If you get the "__in" error:
  Add in StdAfx.h after :#define VC_EXTRALEAN
 
  #include "Specstrings.h"

*/

/*
%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
	
	CLASS		:	GetSMTPHostName
	DESCRIPTION	:	A Cached MX Record lookup class

	NOTES		:	

%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
*/
class CGetSMTPHostName
{
	// Some typedef's
	typedef std::map<CString, CString>		HostMap;		// map holding DomainName and SMTPHostName (in that order)
	typedef HostMap::value_type				HostMapValue;
	typedef HostMap::iterator				HostMapIterator;
public:
						CGetSMTPHostName();
						~CGetSMTPHostName();
	BOOL				GetSmtpHostName(CString _EmailAddress, CString& _HostName);
	void				ResetHostMap();
	void				Dump()	// Just for testing this class... you probably do not need it
	{
		CString domain, smtphost;
		for(m_SMTPHostIterator = m_SMTPHost.begin(); m_SMTPHostIterator != m_SMTPHost.end(); m_SMTPHostIterator++)
		{
			domain		= (*m_SMTPHostIterator).first;
			smtphost	=(*m_SMTPHostIterator).second;
			std::cout << domain.GetBuffer(0) << " - " << smtphost.GetBuffer(0) << std::endl;
		}
	}
private:
	HostMap				m_SMTPHost;			// std::map holding the DOMAINS and SMTP HOST NAMES
	HostMapIterator		m_SMTPHostIterator; // typedef'ed std::map::iteratortor for m_SMTPHost
};


class CBase64  
{
	// Internal bucket class.
	class TempBucket
	{
	public:
		BYTE		nData[4];
		BYTE		nSize;
		void		Clear() { ::ZeroMemory(nData, 4); nSize = 0; };
	};

	PBYTE					m_pDBuffer;
	PBYTE					m_pEBuffer;
	DWORD					m_nDBufLen;
	DWORD					m_nEBufLen;
	DWORD					m_nDDataLen;
	DWORD					m_nEDataLen;

public:
	CBase64();
	virtual ~CBase64();

public:

	DWORD GetDecodedSize();


	virtual void		Encode(const PBYTE, DWORD);
	virtual void		Decode(const PBYTE, DWORD);
	virtual void		Encode(LPCSTR sMessage);
	virtual void		Decode(LPCSTR sMessage);

	virtual LPCSTR	DecodedMessage() const;
	virtual LPCSTR	EncodedMessage() const;

	char * DecodedMessage2() const; 

	virtual void		AllocEncode(DWORD);
	virtual void		AllocDecode(DWORD);
	virtual void		SetEncodeBuffer(const PBYTE pBuffer, DWORD nBufLen);
	virtual void		SetDecodeBuffer(const PBYTE pBuffer, DWORD nBufLen);

protected:
	virtual void		_EncodeToBuffer(const TempBucket &Decode, PBYTE pBuffer);
	virtual ULONG		_DecodeToBuffer(const TempBucket &Decode, PBYTE pBuffer);
	virtual void		_EncodeRaw(TempBucket &, const TempBucket &);
	virtual void		_DecodeRaw(TempBucket &, const TempBucket &);
	virtual BOOL		_IsBadMimeChar(BYTE);

	static  char		m_DecodeTable[256];
	static  BOOL		m_Init;
	void				_Init();
};

// Some ATL string conversion enhancements
// ATL's string conversions allocate memory on the stack, which can
// be undesirable if converting huge strings.  These enhancements
// provide for a pre-allocated memory block to be used as the 
// destination for the string conversion.
#define _W2A(dst,src) AtlW2AHelper(dst,src,lstrlenW(src)+1)
#define _A2W(dst,src) AtlA2WHelper(dst,src,lstrlenA(src)+1)

typedef std::wstring StringW;
typedef std::string  StringA;

#ifdef _UNICODE
typedef StringW String;
#define _W2T(dst,src) lstrcpyW(dst,src)
#define _T2W(dst,src) lstrcpyW(dst,src)
#define _T2A(dst,src) _W2A(dst,src)
#define _A2T(dst,src) _A2W(dst,src)
#else
typedef StringA String;
#define _W2T(dst,src) _W2A(dst,src)
#define _T2W(dst,src) _A2W(dst,src)
#define _T2A(dst,src) lstrcpyA(dst,src)
#define _A2T(dst,src) lstrcpyA(dst,src)
#endif

// When the SMTP server responds to a command, this is the
// maximum size of a response I expect back.
#ifndef CMD_RESPONSE_SIZE
#define CMD_RESPONSE_SIZE 1024
#endif

// The CSmtp::SendCmd() function will send blocks no larger than this value
// Any outgoing data larger than this value will trigger an SmtpProgress()
// event for all blocks sent.
#ifndef CMD_BLOCK_SIZE
#define CMD_BLOCK_SIZE  1024
#endif

// Default mime version is 1.0 of course
#ifndef MIME_VERSION
#define MIME_VERSION _T("1.0")
#endif

// This is the message that would appear in an e-mail client that doesn't support
// multipart messages
#ifndef MULTIPART_MESSAGE
#define MULTIPART_MESSAGE _T("This is a multipart message in MIME format")
#endif

// Default message body encoding
#ifndef MESSAGE_ENCODING
#define MESSAGE_ENCODING _T("text/plain")

#endif

// Default character set
#ifndef MESSAGE_CHARSET
#define MESSAGE_CHARSET _T("us-ascii")
#endif

// Some forward declares
class CSmtp;
class CSmtpAddress;
class CSmtpMessage;
class CSmtpAttachment;
class CSmtpMessageBody;
class CSmtpMimePart;

// These are the only 4 encoding methods currently supported
typedef enum EncodingEnum
{
  encodeGuess,
  encode7Bit,
  encode8Bit,
  encodeQuotedPrintable,
  encodeBase64
};

// This code supports three types of mime-types, and can optionally guess a mime type
// based on message content.
typedef enum MimeTypeEnum
{
  mimeGuess,
  mimeMixed,
  mimeAlternative,
  mimeRelated
};

// Attachments and message bodies inherit from this class
// It allows each part of a multipart MIME message to have its own attributes
class CSmtpMimePart
{
public:
  String Encoding;  // Content encoding.  Leave blank to let the system discover it
  String Charset;   // Character set for text attachments
  String ContentId; // Unique content ID, leave blank to let the system handle it
  EncodingEnum TransferEncoding; // How to encode for transferring to the server
};

// This class represents a user's text name and corresponding email address
class CSmtpAddress
{
public: // Constructors
  CSmtpAddress(LPCTSTR pszAddress = NULL, LPCTSTR pszName = NULL);

public: // Operators
  const CSmtpAddress& operator=(LPCTSTR pszAddress);
  const CSmtpAddress& operator=(const String& strAddress);

public: // Member Variables
  String Name;
  String Address;
};

// This class represents a file attachment
class CSmtpAttachment : public CSmtpMimePart
{
public: // Constructors
  CSmtpAttachment(LPCTSTR pszFilename = NULL, LPCTSTR pszAltName = NULL, BOOL bIsInline = FALSE, LPCTSTR pszEncoding = NULL, LPCTSTR pszCharset = MESSAGE_CHARSET, EncodingEnum encode = encodeGuess);

public: // Operators
  const CSmtpAttachment& operator=(LPCTSTR pszFilename);
  const CSmtpAttachment& operator=(const String& strFilename);

public: // Member Variables
  String FileName;  // Fully-qualified path and filename of this attachment
  String AltName;   // Optional, an alternate name for the file to use when sending
  BOOL   Inline;    // Is this an inline attachment?
};

// Multiple message body part support
class CSmtpMessageBody : public CSmtpMimePart
{
public: // Constructors
  CSmtpMessageBody(LPCTSTR pszBody = NULL, LPCTSTR pszEncoding = MESSAGE_ENCODING, LPCTSTR pszCharset = MESSAGE_CHARSET, EncodingEnum encode = encodeGuess);

public: // Operators
  const CSmtpMessageBody& operator=(LPCTSTR pszBody);
  const CSmtpMessageBody& operator=(const String& strBody);

public: // Member Variables
  String       Data;             // Message body;
};

// This class represents a single message that can be sent via CSmtp
class CSmtpMessage
{
public: // Constructors
  CSmtpMessage();

public: // Member Variables
  CSmtpAddress                   Sender;           // Who the message is from
  CSmtpAddress                   Recipient;        // The intended recipient
  String                         Subject;          // The message subject
  CSimpleArray<CSmtpMessageBody> Message;          // An array of message bodies
  CSimpleArray<CSmtpAddress>     CC;               // Carbon Copy recipients
  CSimpleArray<CSmtpAddress>     BCC;              // Blind Carbon Copy recipients
  CSimpleArray<CSmtpAttachment>  Attachments;      // An array of attachments
  CSimpleMap<String,String>      Headers;          // Optional headers to include in the message
  SYSTEMTIME                     Timestamp;        // Timestamp of the message
  MimeTypeEnum                   MimeType;         // Type of MIME message this is
  String                         MessageId;        // Optional message ID

private: // Private Member Variables
  int                            GMTOffset;        // GMT timezone offset value

public: // Public functions
  void Parse(String& strDest);
  void Nothing2Parse(String& strDest);

private: // Private functions to finalize the message headers & parse the message
	EncodingEnum GuessEncoding(LPBYTE pByte, DWORD dwLen);
	void EncodeMessage(EncodingEnum code, String& strMsg, String& strMethod, LPBYTE pByte = NULL, DWORD dwSize = 0);
	void Make7Bit(String& strDest, String& strSrc);
  void CommitHeaders();
	void BreakMessage(String& strDest, String& strSrc, int nLength = 76);
	void EncodeQuotedPrintable(String& strDest, String& strSrc);
};

// The main class for connecting to a SMTP server and sending mail.
class CSmtp  
{

public: // Constructors
	CSmtp();
	virtual ~CSmtp();

	int  SendDataRaw(char * cData, char * sender, char * receiver);


public: // Member Variables.  Feel free to modify these to change the system's behavior
  BOOL     m_bExtensions;     // Use ESMTP extensions (TRUE)
  DWORD    m_dwCmdTimeout;    // Timeout for issuing each command (30 seconds)
  WORD     m_wSmtpPort;       // Port to communicate via SMTP (25) 
  String   m_strUser;         // Username for authentication
  String   m_strPass;         // Password for authentication

private: // Private Member Variables
  SOCKET   m_hSocket;         // Socket being used to communicate to the SMTP server
  String   m_strResult;       // String result from a SendCmd()
  BOOL     m_bConnected;      // Connected to SMTP server
  BOOL     m_bUsingExtensions;// Whether this SMTP server uses ESMTP extensions

public: // These represent the primary public functionality of this class
	BOOL SMTPConnect(LPTSTR pszServer);
	int  SendMessage(CSmtpMessage& msg,int nType);
	int  SendMessage(CSmtpAddress& addrFrom, CSmtpAddress& addrTo, LPCTSTR pszSubject, LPTSTR pszMessage, LPVOID pvAttachments = NULL, DWORD dwAttachmentCount = 0);
	int  SendMessage(LPTSTR pszAddrFrom, LPTSTR pszAddrTo, LPTSTR pszSubject, LPTSTR pszMessage, LPVOID pvAttachments = NULL, DWORD dwAttachmentCount = 0);
	void Close();

public: // These represent the overridable methods for receiving events from this class
	virtual int  SmtpWarning(int nWarning, LPTSTR pszWarning);
    virtual int  SmtpError(int nCode, LPTSTR pszErr);
	virtual void SmtpCommandResponse(LPTSTR pszCmd, int nResponse, LPTSTR pszResponse);
	virtual BOOL SmtpProgress(LPSTR pszBuffer, DWORD dwSent, DWORD dwTotal);

private: // These functions are used privately to conduct a SMTP session
	int  SendCmd(LPTSTR pszCmd);

	int  SendAuthentication();
	int  SendHello();
	int  SendQuitCmd();
	int  SendFrom(LPTSTR pszFrom);
	int  SendTo(LPTSTR pszTo);
	int  SendData(CSmtpMessage &msg, int nType);
    int  RaiseWarning(int nWarning);
	int  RaiseError(int nError);
};

#endif // !defined(AFX_CBase64_H__B2E45717_0625_11D2_A80A_00C04FB6794C__INCLUDED_)
