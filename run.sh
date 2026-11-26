#!/bin/sh
set -eu
printf 'Choose an admin password: '
stty -echo
IFS= read -r GLOBALFIND_ADMIN_PASSWORD
stty echo
printf '\n'
export GLOBALFIND_ADMIN_PASSWORD
exec python3 "$(dirname "$0")/backend/server.py"
