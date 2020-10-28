#!/bin/bash
#
declare -a myArray
myArray=("$@") 
#for arg in "${myArray[@]}"; do
# echo "$arg" >> /mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/scripts/takesnap_array_args.log
#done

# echo "${myArray[@]}" > /mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/scripts/takesnap_parms.log

# Run in the background, return the PID
exec /usr/sbin/rsync --log-file=/mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/scripts/rsync.log -aPv "${myArray[@]}" > /mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/scripts/takesnap.log & echo "$!"
