#!/bin/sh
# stop.sh: Will stop App daemon.

# Stop TimeSync
if [ -f "/var/run/timesync.pid" ]; then
    kill -3 "$(cat /var/run/timesync.pid)"
    sleep 3
fi
