#!/bin/bash

FILENAME="cvs_pme.tar"
BACKUP_DIR="/home2/business/backups/"
CVS_DIR="/usr/local"
REPOSITORY_NAME="cvsroot"


CURRENT_FILEDATE=`set \`date\`; echo ${2}"_"${3}"_"${6}`
FILE_DEST=${BACKUP_DIR}${FILENAME}".gz."${CURRENT_FILEDATE}

cd ${CVS_DIR}
tar -cvf ${BACKUP_DIR}${FILENAME} ${REPOSITORY_NAME}
gzip ${BACKUP_DIR}${FILENAME}
mv ${BACKUP_DIR}${FILENAME}".gz" ${FILE_DEST}


#----------------- 


FILENAME="cvs_dot.tar"
BACKUP_DIR="/home2/business/backups/"
CVS_DIR="/usr/local"
REPOSITORY_NAME="cvs"



CURRENT_FILEDATE=`set \`date\`; echo ${2}"_"${3}"_"${6}`
FILE_DEST=${BACKUP_DIR}${FILENAME}".gz."${CURRENT_FILEDATE}

cd ${CVS_DIR}
tar -cvf ${BACKUP_DIR}${FILENAME} ${REPOSITORY_NAME}
gzip ${BACKUP_DIR}${FILENAME}
mv ${BACKUP_DIR}${FILENAME}".gz" ${FILE_DEST}