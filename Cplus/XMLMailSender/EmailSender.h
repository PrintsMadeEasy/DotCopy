#if !defined(AFX_EMAILSENDER_H__DCD4690F_DC02_4904_8ACA_E22661A68EFE__INCLUDED_)
#define AFX_EMAILSENDER_H__DCD4690F_DC02_4904_8ACA_E22661A68EFE__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

struct EmailMessage
{
	char trackid[20];
	char jobid[200];
	char senderemail[200];
	char sendername[200];
	char mailhost[200];
	char mailuser[200];
	char mailpw[200];
	char emailreceiver[200];
	char namereceiver[200];
	char subject[200];
	char textbody[500000];
	BOOL bPlain;
};   


struct EmailTextVar
{
	CString sMessageContents, sJobID, sSubject, sSenderName, sSenderEmail;
	CStringArray sATrackID, sAName,sACompany,sAIndustry,sAEmail,sAPhone,sAAddress,sACity,sAState,sAZip,sACountry,sASICCode,sATitle;
};



class EmailSender : public CObject  
{
public:

	CString GetDkimHeader(CString sHeader, CString sBody, CString sSubject, CString sDomain, CString sSelector);


	void GetRFCTime(TCHAR * szDateOut);

	void GetHeaderInfo(char * sender,  char * filebuffer, int nBufferLen, int &nDeliveryDateSendOut, CStringArray &sAReceiverEmail, CStringArray &sAReceiverName, CString &sReceiverEmail, CString &sReceiverName);

	void SendLocalEmails(char * cFilename);
	void StartSendingAllLocalEmails();
	int SendAllLocalEmails();

	void SendUndeliverableLocalEmails(char * cFilename);
	void StartSendUndeliverableLocalEmails();
	int ResendUndeliverableLocalEmails();



	void ErrorLog(char *cText);



	EmailSender();
	virtual ~EmailSender();

	CString DecryptBlowFish(CString sBase64Encrypted);

	void DecodeBase64Test();


	CString GenerateMessage(EmailTextVar &etv, int nPosition, int nStartPos);
	CString GenerateSubject(EmailTextVar &etv, int nPosition);

	void DownloadXML();
	void ReadXML();

	void SubmitMailError(CString sEmail, int nError);

	CString sFilePath, sXMLHost, sXMLFile;
	CString GetParseValue(CString sXML, CString sXMLUpper, int nStartPos, CString sField);	
	CString SendSingleEmail(CString sEmail, CString sSubject, CString sBody);

	CString SendSingleEmailTest();

};

#endif // !defined(AFX_EMAILSENDER_H__DCD4690F_DC02_4904_8ACA_E22661A68EFE__INCLUDED_)
