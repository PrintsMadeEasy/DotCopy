#!/bin/bash

#-- The following vars are set within the setup_vars.sh script --#
#-- BACKUP_DIR - DB_USERNAME - DB_PASSWORD - DB_DATABASE - SERVER_TYPE
#-- The dot means to run the script in the same enviroment shell so that variables are in scope
. setup_vars.sh


TABLE_LISTFILE="backuplist_BinaryTables"
FILEPREFIX="pme_bin_"

CURRENT_FILEDATE=`set \`date\`; echo ${2}"_"${3}"_"${6}`

#Open a file off of disk, having a lit of all tables we want to backup
for i in `cat ${TABLE_LISTFILE}`
do

	#Skip blank lines
        if [ "$i" = "" ]
        then
                continue
       	fi
       	
       	#Skip Lines starting with a pound sign
        if [ "`echo ${i} | grep '^#'`" != "" ]
        then
                continue
       	fi

       	#-- Will strip off any blankspaces or newline characters
       	TABLENAME=`expr ${i} : '\([0-9a-zA-Z_]*[0-9a-zA-Z_]\)'`

	echo "Dumping... "${TABLENAME}" -- "

	DUMP_FILENAME=${BACKUP_DIR}${FILEPREFIX}${TABLENAME}".dump."${CURRENT_FILEDATE}

	mysqldump --opt -u ${DB_USERNAME} -p${DB_PASSWORD} --tables --max_allowed_packet=100M ${DB_DATABASE} ${TABLENAME} > ${DUMP_FILENAME}

done

lynx -dump "http://www.PrintsMadeEasy.com/server_notification.php?subject=Binary+Backup+Completed"
