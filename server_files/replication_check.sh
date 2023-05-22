#-- The following vars are set within the setup_vars.sh script --#
#-- BACKUP_DIR - DB_USERNAME - DB_PASSWORD - DB_DATABASE - SERVER_TYPE
#-- The dot means to run the script in the same enviroment shell so that variables are in scope
. setup_vars.sh

#-- We are basically looking for the max ID of a table within the DB
mysql --skip-column-names -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < rep_check.sql > replicationcheck.temp
MAXID=`cat replicationcheck.temp`
rm replicationcheck.temp


lynx -dump "http://s10.PrintsMadeEasy.com/server_check_replication.php?command=${SERVER_TYPE}\&id=${MAXID}"
