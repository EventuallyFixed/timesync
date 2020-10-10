#!/bin/sh
# init.sh: Will create necessary symbolic links of installed App before being executed

[ -f /tmp/debug_apkg ] && echo "APKG_DEBUG: $0 $@" >> /tmp/debug_apkg

path=$1
syml="/var/www/timesync"

echo "Link file from : "$path

# create symlink
if [ ! -f "$syml" ]; then
  ln -s "$path" "$syml"
fi
