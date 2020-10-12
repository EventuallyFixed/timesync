# TimeSync
An incremental backup system for [Western Digital Personal Cloud](https://shop.westerndigital.com/en-gb/c/nas-and-cloud-storage) devices.

# Why
Because the only backup options for these devices are either one-time copies, or online, paid-for services.

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

# ToDo
- Allow upload and save of SSH private keys.
  - Save keys in file system: timesync/profiles/profileId/KeyFile.
  - Check for existence of key before upload (one key per profile? Overwrite option?).
- Include & Exclude:
  - Issue with selection of symlinks pointing to files/folders.
    - Perhaps these should be selectable as the linked-to type?
      - How will rsync deal with the symlinks?
- Revamp the Snapshots screen layout, to be more mobile friendly.
- Translate settings into rsync commands:
  - Execute the rsync commands!
- Logic to build script to do Smart Remove.
- Translate schedules into cron job XMLs (See posts in WD Community: [Post 1](https://community.wd.com/t/crontab-on-mycloud-ex2/98653/21); [Post 2](https://community.wd.com/t/nas-to-usb-automatic-incremental-backup/193625)):
  - Be able to reliably add/delete the cron entries using php's XML tools.
- Wrap the application in the WD application wrapper (See: [WD Developer SDK](https://developer.westerndigital.com/develop/wd/sdk.html#intro)).

# Completed
- Screens - Initial design prototypes.
- Database - Initialise, Load, & Save.
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
