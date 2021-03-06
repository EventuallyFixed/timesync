# TimeSync
An incremental backup system for [Western Digital Personal Cloud](https://shop.westerndigital.com/en-gb/c/nas-and-cloud-storage) devices.

# Why
Because the only bundled backup options for these devices are either one-time, whole-system copies, or online, paid-for services.
I want to use my own disks: One on site receiving incremental backups, and (at least) one securely stored off-site, in rotation.

# Aim
- To create a user-friendly, and incremental, backup system.
- To use only the software that is bundled with the device.
- To have a mobile friendly, modern, User Interface.

# Inspiration
The excellent [BackInTime](https://github.com/bit-team/backintime) project.

# Technologies
- HTML
- jQuery
- Bootstrap
- PHP
- SQLite
- rSync
- csh

# Development status
- Ongoing

# Doing
- After a backup, copy the database on to the backup media, so that if the database gets corrupted, there's another copy of it

# ToDo
- Check if a backup is ongoing. If so, 
  - Indicate in the Snapshots Menu.
  - Disable a snapshot from being taken.
  - Periodically poll to see whether or not the snapshot is ongoing (is the unix process id still active).
  - If completed, update the status & re-enable the take new backup button.
- Develop SSH backup type
  - Allow upload and save of SSH keys.
    - Check for existence of key before upload (one key per profile? Overwrite option?).
  - Translate settings into rsync commands
    - Execute the rsync commands on the remote machine, in the background, get the process id.
      - Need to be able to get status
      - Only one rsync call at a time
    - Move snapshot backup deletions to backend shell script.
    - Associate rsync log files with the correct snapshot directory instance.
      - Possibly somehow move the log file(s) to the correct snapshot directory, post-completion.
      - Could be tricky, as it's all being done asynchronously.
- Revise the main Snapshots screen layout, to be more mobile friendly.
  - Complete the implementation of functionality here.
- Restore Functionality
  - File Change History Window
  - Restore to disk (ensuring the path exists)
- Cron Jobs
  - Translate schedules into cron job XMLs (See posts in WD Community: [Post 1](https://community.wd.com/t/crontab-on-mycloud-ex2/98653/21); [Post 2](https://community.wd.com/t/nas-to-usb-automatic-incremental-backup/193625)):
  - Be able to reliably add/delete the cron entries using php's XML tools.
- Wrap the application in the WD application wrapper (See: [WD Developer SDK](https://developer.westerndigital.com/develop/wd/sdk.html#intro)).

# Completed
- Upload SSH private key file
- Logic of script to do Smart Remove, and call it after snapshot completion.
- Get rsync to work from backend script, and pass back process id, save pid in database.
- Screens - Initial design prototypes.
- Database - Initialise, Load, & Save, but still is a work in progress.
- Settings - Initialise, Load, & Save.
- Include & Exclude folders.
  - Initialise, Load, Save, Add, & Remove.
  - Show Icons for files and folders.
- Profiles Add, and Delete.

# Licence
Copyright (C) 2020 Steven Tierney

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
