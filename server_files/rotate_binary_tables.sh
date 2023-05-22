#!/bin/bash

#-- The following vars are set within the setup_vars.sh script --#
#-- BACKUP_DIR - DB_USERNAME - DB_PASSWORD - DB_DATABASE - SERVER_TYPE
#-- The dot means to run the script in the same enviroment shell so that variables are in scope
. setup_vars.sh



#-- Find out if There is a Binary Table that needs to be backed up and protected
#-- Even if there is a chance that more than 1 table needs to be backed up (multiple rows in this table)
#-- we won't worry about looping in the script.  Just let the cron pick the next one up... the cron will always cycle faster than the amount of tables being backed up.

echo "SELECT TableName FROM tablerotationbackups LIMIT 1;" | mysql --skip-column-names -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < check_for_table_rotation.sql > rotationcheck.temp
TABLENAME=`cat rotationcheck.temp`
rm rotationcheck.temp




#--- If a table name was found... then get the MAX ID anddo the backup
if [ "${TABLENAME}" != "" ]; then

	echo "backing up: "${TABLENAME}
	echo ""
    
	#-- Get the MAX ID of the table.  We want to name the File name with the MAX ID inside as an easy way (in the future) to ensure that nothing else got added after the time we finalized it.
	echo "SELECT MAX(ID) FROM "${TABLENAME}";" | mysql --skip-column-names -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE}  > maxbinaryID.temp
	MAXBINID=`cat maxbinaryID.temp`
	rm maxbinaryID.temp
	
	FILEPREFIX="pme_bin_"

	CURRENT_FILEDATE=`set \`date\`; echo ${2}"_"${3}"_"${6}`

	echo "Dumping... "${TABLENAME}" -- "

	DUMP_FILENAME=${BACKUP_DIR}${FILEPREFIX}${TABLENAME}".dump."${CURRENT_FILEDATE}".MAXID-"${MAXBINID}

	mysqldump --opt -u ${DB_USERNAME} -p${DB_PASSWORD} --tables --max_allowed_packet=100M ${DB_DATABASE} ${TABLENAME} > ${DUMP_FILENAME}

	#-- Send a command to the live server letting it know that the backup was completed succesfully
	#-- The live server will delete the entry from the table "tablerotationbackups"
	#-- The Development server can only read the database, not write to it.  That is because of database replication.
	lynx -dump http://www.PrintsMadeEasy.com/server_finalize_binarytable.php?tablename=${TABLENAME}\&maxid=${MAXBINID}\&filename=${DUMP_FILENAME}

else
    echo "No backup needed at this time"
fi



