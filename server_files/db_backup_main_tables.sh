#!/bin/bash

#-- The following vars are set within the setup_vars.sh script --#
#-- BACKUP_DIR - DB_USERNAME - DB_PASSWORD - DB_DATABASE - SERVER_TYPE
#-- The dot means to run the script in the same enviroment shell so that variables are in scope
. setup_vars.sh

MAIN_FILENAME="pme_main_tables.dump.gz"
TABLE_LISTFILE="backuplist_MainTables"
BUGS_FILENAME="bugs_tables.dump.gz"

CURRENT_FILEDATE=`set \`date\`; echo ${2}"_"${3}"_"${6}`

MAINTABLES_FILE_NAME=${BACKUP_DIR}${MAIN_FILENAME}"."${CURRENT_FILEDATE}
BUGSTABLES_FILE_NAME=${BACKUP_DIR}${BUGS_FILENAME}"."${CURRENT_FILEDATE}

TABLE_LIST=""

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
       	RESULT=`expr ${i} : '\([0-9a-zA-Z_]*[0-9a-zA-Z_]\)'`

	TABLE_LIST=${TABLE_LIST}" "${RESULT}

done


#Dump the database to the backup directory
mysqldump --opt -u ${DB_USERNAME} -p${DB_PASSWORD} --max_allowed_packet=100M --tables ${DB_DATABASE} ${TABLE_LIST} | gzip > $MAINTABLES_FILE_NAME
#mysqldump --opt -u bugspme_bugs -pbugs bugspme_bugs | gzip > $BUGSTABLES_FILE_NAME


lynx -dump "http://www.PrintsMadeEasy.com/server_notification.php?subject=MainTables+Backup+Completed"
