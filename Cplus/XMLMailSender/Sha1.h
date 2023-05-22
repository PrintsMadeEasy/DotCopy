// Sha1.h: Schnittstelle für die Klasse CSha1.
//
//////////////////////////////////////////////////////////////////////

#if !defined(AFX_SHA1_H__95BE6C63_4C0A_41E7_84B8_139E742C7F7A__INCLUDED_)
#define AFX_SHA1_H__95BE6C63_4C0A_41E7_84B8_139E742C7F7A__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000


class CSha1 : public CObject  
{

	typedef struct
	{
		unsigned long total[2];     /*!< number of bytes processed  */
		unsigned long state[5];     /*!< intermediate digest state  */
		unsigned char buffer[64];   /*!< data block being processed */

		unsigned char ipad[64];     /*!< HMAC: inner padding        */
		unsigned char opad[64];     /*!< HMAC: outer padding        */
	}
	sha1_context;

public:
	CSha1();
	virtual ~CSha1();

	void RunSha(unsigned char * buf,int nBufLen, unsigned char * sha1sum);

	
	static void sha1_process( sha1_context *ctx, const unsigned char data[64] );
	void sha1_starts( sha1_context *ctx );
	void sha1_update( sha1_context *ctx, const unsigned char *input, int ilen );
	void sha1_finish( sha1_context *ctx, unsigned char output[20] );
	void sha1( const unsigned char *input, int ilen, unsigned char output[20] );
	int sha1_file( const char *path, unsigned char output[20] );
	void sha1_hmac_starts( sha1_context *ctx, const unsigned char *key, int keylen );
	void sha1_hmac_update( sha1_context *ctx, const unsigned char *input, int ilen );
	void sha1_hmac_finish( sha1_context *ctx, unsigned char output[20] );
	void sha1_hmac_reset( sha1_context *ctx );
	void sha1_hmac( const unsigned char *key, int keylen,const unsigned char *input, int ilen,unsigned char output[20] );

	


};


#endif // !defined(AFX_SHA1_H__95BE6C63_4C0A_41E7_84B8_139E742C7F7A__INCLUDED_)
