#-- The following vars are set within the setup_vars.sh script --#
#-- BACKUP_DIR - DB_USERNAME - DB_PASSWORD - DB_DATABASE - SERVER_TYPE
#-- The dot means to run the script in the same enviroment shell so that variables are in scope
. setup_vars.sh

mysql --skip-column-names -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < ${SCRIPT_PATH}/db_session_clean.sql
