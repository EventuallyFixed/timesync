#!/bin/sh
# clean.sh: Will remove all links or files that created by init.sh.

[ -f /tmp/debug_apkg ] && echo "APKG_DEBUG: $0 $@" >> /tmp/debug_apkg

# Remove symlink
WEBPATH="/var/www/timesync"

rm -f $WEBPATH
