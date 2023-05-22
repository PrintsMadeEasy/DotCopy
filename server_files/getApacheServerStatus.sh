#!/bin/bash

# Put this in a cron running every minute if you want to hunt for an HTTP process going wild.

BACKUP_DIR="/var/log/apache_status_snapshots/"
FILEPREFIX="ServStatus_"

CURRENT_FILEDATE_STAMP=`set \`date '+%m-%d-%y_%H-%M'\`; echo ${1}`

DUMP_FILENAME=${BACKUP_DIR}${FILEPREFIX}""${CURRENT_FILEDATE_STAMP}

lynx -dump http://www.PrintsMadeEasy.com/server-status > ${DUMP_FILENAME}".html"

top -b -n 1 > ${DUMP_FILENAME}".top";

mysql < /home/printsma/ShellScripts/innodbStatus.sql > ${DUMP_FILENAME}".InnoDbStatus.txt"

