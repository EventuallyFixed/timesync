#!/bin/sh
# start.sh: Will start App.

# Set TimeSync enviroment variables
export BACKUPSINC_DIR="/mnt/HD/HD_a2/Nas_Prog/timesync"
export TIMESYNC_PIDFILE="/var/run/timesync.pid"
export TMPDIR="$PLEX_DIR/tmp"
export LC_ALL="en_US.UTF-8"
export LANG="en_US.UTF-8"

# Set max stack size
ulimit -s 3000

# Set identification variables
export TIMESYNC_INFO_VENDOR="Western Digital"
export TIMESYNC_INFO_DEVICE="$(grep modelName /etc/system.conf | cut -d \" -f2)"
export TIMESYNC_INFO_MODEL="$(uname -m)"
export TIMESYNC_INFO_PLATFORM_VERSION="$(cat /etc/version)"

# Start
# $PLEX_DIR/binaries/Plex\ Media\ Server &
