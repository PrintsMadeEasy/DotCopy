// DKIM.h: Schnittstelle für die Klasse CDKIM.
//
//////////////////////////////////////////////////////////////////////

#if !defined(AFX_DKIM_H__9465D843_D60A_48EF_A2AF_DE6E7CE04E24__INCLUDED_)
#define AFX_DKIM_H__9465D843_D60A_48EF_A2AF_DE6E7CE04E24__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

class CDKIM : public CObject  
{
public:
	CDKIM();
	virtual ~CDKIM();

	CString GenerateSha1(CString sBody);
	CString SignHeader(CString sSelector, CString sToBeSigned);
	
	CString AddDkimToHeaders(CString sSelector, CString sDomain, CString headers_line,CString body);
	CString SimpleBodyCanonicalization(CString body);

	CString QuotedPrintable(CString txt);
	CString SimpleHeaderCanonicalization(CString s);
	CString RelaxedHeaderCanonicalization(CString s);
	void test();







};



class CBase64DKIM  
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
	CBase64DKIM();
	virtual ~CBase64DKIM();

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


#endif // !defined(AFX_DKIM_H__9465D843_D60A_48EF_A2AF_DE6E7CE04E24__INCLUDED_)
