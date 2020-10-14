<?php

// A file of backend functions for the application
// Where required, JSON formatted text should be returned

//
// https://community.wd.com/t/how-can-i-restart-apache/241579/4
// It is the apc module that is caching the php responses: https://www.php.net/manual/en/apc.configuration.php````
//
// Make the following change to /etc/php/php.ini:
//
// apc.enabled = 0
//
// And then restart apache with the following command:
//
// /usr/local/modules/script/apache restart web
//
// SJT - Solution
// .htaccess file in the directory of the php files
// Contents:
//   php_flag apc.cache_by_default 0
//


// Database Creation

class MyDB extends SQLite3 {
  function __construct() {
     $this->open('app.sqlite.db');
  }
}


function db_create_schema() {
  $db = new MyDB();
  $arr = array();

  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {

    $sql =<<<EOF
      CREATE TABLE IF NOT EXISTS profiles (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        profilename                   CHAR(250) NOT NULL
      );

      CREATE TABLE IF NOT EXISTS profilesettings (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        profileid                     INTEGER NOT NULL,
        profilekey                    CHAR(50) NOT NULL,
        profilevalue                  CHAR(250) NOT NULL
      );

      CREATE TABLE IF NOT EXISTS profileinclexcl (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        profileid                     INTEGER NOT NULL,
        profilekey                    CHAR(10) NOT NULL,
        profiletype                   CHAR(2) NOT NULL,
        profilevalue                  CHAR(500) NOT NULL
      );

      CREATE TABLE IF NOT EXISTS snapshots (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        profileid                     INTEGER NOT NULL,
        snaptime                      TEXT    NOT NULL,
        snapdesc                      TEXT            ,
        snapstatus                    INTEGER NOT NULL
      );

      CREATE TABLE IF NOT EXISTS snapshotpaths (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        snapshotid                    INTEGER NOT NULL,
        snapshotpath                  TEXT    NOT NULL
      );

      CREATE TABLE IF NOT EXISTS codelist (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        codetype                      CHAR(20) NOT NULL,
        codename                      CHAR(20) NOT NULL,
        codedesc                      CHAR(50) NOT NULL
      );

      -- Insert statuses
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'proc', 'Processing snapshot' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'proc');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'comp', 'Snapshot completed' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'comp');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'comperr', 'Snapshot completed with errors' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'comperr');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'fail', 'Snapshot failed' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'fail');

      CREATE TABLE IF NOT EXISTS appversion (
        appversion                    CHAR(50)  NOT NULL
      );

      CREATE TEMP TABLE IF NOT EXISTS variables (
        varname                       CHAR(50)  NOT NULL,
        varvalue                      CHAR(250) NOT NULL
      );

EOF;

    $ret = $db->exec($sql);
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "DB Init success";
    }
    $db->close();
  }

  return $arr;
} // db_create_schema


function dbSelectProfileIdForProfileName($ProfileName) {

  $rtn = array();
  $Exists = 0;
  $id = -1;

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["id"] = $id;
    $rtn["message"] = $db->lastErrorMsg();
  } else {
    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profiles WHERE profilename = '".$ProfileName."';");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["id"] = $id;
      $rtn["message"] = $db->lastErrorMsg();
    } else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];
    }

    if ($Exists > 0) {
      $rows = $db->query("SELECT id AS id FROM profiles WHERE profilename = '".$ProfileName."';");
      if(!$rows){
        $rtn["result"] = "ko";
        $rtn["id"] = $id;
        $rtn["message"] = $db->lastErrorMsg();
      }
      else {
        $row = $rows->fetchArray(SQLITE3_ASSOC);
        $rtn["result"] = "ok";
        $rtn["id"] = $row["id"];
        $rtn["message"] = "ID found";
      }
    }

    $db->close();
  }

  return $rtn;
}


// Select all profiles from the Profiles list
function dbSelectProfilesList() {
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT id, profilename FROM profiles ORDER BY ProfileName;");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
    }
    $db->close();
  }
  return $rtn;
}


// Select all profiles from the Profiles list
function dbSelectProfileForId($ProfileId) {
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT id, profilename FROM profiles WHERE id = ".$ProfileId.";");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
    }
    $db->close();
  }
  return $rtn;
}


function dbDeleteProfileRecordSet($ProfileId) {

  $arr = array();

  // Never delete the 'default' profile (although it's settings can be changed)
  $ProfileIdArr = dbSelectProfileIdForProfileName($ProfileName);
  $defaultId = $ProfileIdArr["id"];

  if ($ProfileId == $defaultId) {
    $arr["result"] = "ko";
    $arr["message"] = "You cannot delete the Default Profile";
  }
  else {
    // First, delete the profile settings
    $arr["deletesettings"] = dbDelProfileSettings($ProfileId);
    if ($arr["deletesettings"]["result"] == "ok") {
      $arr["deleteinclexcl"] = dbDelProfileInclExcl($ProfileId);
      // Then, if a success, delete the profile
      if ($arr["deleteinclexcl"]["result"] == "ok") {
        $arr["deleteprofile"] = dbDelProfile($ProfileId);
        $arr["result"] = $arr["deleteprofile"]["result"];
        if ($arr["result"] == "ok") {
          $arr["message"] = "Profile deleted";
        }
        else {
          $arr["result"] == "ko";
          $arr["message"] = "Error deleting profile";
        }
      }
      else {
        $arr["result"] = "ko";
        $arr["message"] = "Error deleting inclexcl for profile id: ".$ProfileId;
      }
    }
    else {
      $arr["result"] = "ko";
      $arr["message"] = "Error deleting settings for profile id: ".$ProfileId;
    }
  }

  return $arr;
}


function dbSelectProfileSettingsId($ProfileId, $ProfileKey) {
  // If there is no profile, create one to default to
  $Exists = "0";
  $rtn = array();
  $id = -1;

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["id"] = $id;
    $rtn["message"] = $db->lastErrorMsg();
  } else {

    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["id"] = $id;
      $rtn["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];
    }

    if ($Exists > 0) {
      $rows = $db->query("SELECT id FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
      if(!$rows){
        $rtn["result"] = "ko";
        $rtn["id"] = $id;
        $rtn["message"] = $db->lastErrorMsg();
      }
      else {
        $row = $rows->fetchArray();
        $rtn["result"] = "ok";
        $rtn["id"] = $row["id"];
        $rtn["message"] = "Found";
      }
    }
    else {
      $rtn["result"] = "ok";
      $rtn["id"] = $id;
      $rtn["message"] = "Not found";
    }

    $db->close();
  }

  return $rtn;
}


function dbSelectProfileSettings($ProfileId){
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
    $arr["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT profilekey setkey, profilevalue setval FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey != 'include' AND profilekey != 'exclude';");
    if (!$rows) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
      $arr["items"] = array();
    }
    else {
      $pos = 0;
      while($row = $rows->fetchArray(SQLITE3_ASSOC)){
        $arr["items"][$pos] = $row;
        $pos = $pos + 1;
      }
      $arr["result"] = "ok";
      $arr["message"] = "Fetched Items";
    }
    $db->close();
  }
  return $arr;
}


// Deletes a Profile Setting key/value pair
function dbDeleteProfileSetting($SettingId) {

  $Exists = "-1";
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profilesettings WHERE id = ".$SettingId.";");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];

      if ($Exists > 0) {
        $ret = $db->exec("DELETE FROM profilesettings WHERE id = ".$SettingId.";");
        if(!$ret){
          $arr["result"] = "ko";
          $arr["message"] = $db->lastErrorMsg();
        }
        else {
          $arr["result"] = "ok";
          $arr["message"] = "Record deleted";
        }
      }
      else {
        $arr["result"] = "ko";
        $arr["message"] = "No such id: ".$SettingId;
      }
    }
    $db->close();
  }

  return $arr;
}


// Select all Include or Exclude Settings for a Profile ID
function dbSelectProfileIncludeExclude($ProfileId, $InclExcl) {
  $arr = array();
  $db = new MyDB();
  // Sort in ascending order - this is default
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
    $arr["items"] = array();
  } else {
    $rows = $db->query("SELECT id setid, profilekey setkey, profiletype settype, profilevalue setval FROM profileinclexcl WHERE profileid = ".$ProfileId." AND profilekey = '".$InclExcl."';");
    if (!$rows) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
      $arr["items"] = array();
    }
    else {
      $arr["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)){
        array_push($arr["items"], $row);
      }
      $arr["result"] = "ok";
      $arr["message"] = "Fetched Items";
    }
    $db->close();
  }
  return $arr;
}


function dbDeleteProfileInclExcl($SettingId) {

  $Exists = "-1";

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profileinclexcl WHERE id = ".$SettingId.";");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];

      if ($Exists > 0) {
        $ret = $db->exec("DELETE FROM profileinclexcl WHERE id = ".$SettingId.";");
        if(!$ret){
          $arr["result"] = "ko";
          $arr["message"] = $db->lastErrorMsg();
        }
        else {
          $arr["result"] = "ok";
          $arr["message"] = "Record deleted";
        }
      }
      else {
        $arr["result"] = "ko";
        $arr["message"] = "No such id: ".$SettingId;
      }
    }
    $db->close();
  }

  return $arr;
}


function dbGetFileSpecId($ProfileId, $InclExcl, $Type, $Pattern) {

  $Exists = 0;
  $rtn = "-1";

  $db = new MyDB();
  if(!$db) {
    $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
  } else {

    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profileinclexcl WHERE profileid = '".$ProfileId."' AND profilekey = '".$InclExcl."' AND profiletype = '".$Type."' AND profilevalue = '".$Pattern."';");
    if (!$rows) {
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
    } else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];
    }


    if ($Exists > 0) {
      $rows = $db->query("SELECT id AS id FROM profileinclexcl WHERE profileid = '".$ProfileId."' AND profilekey = '".$InclExcl."' AND profiletype = '".$Type."' AND profilevalue = '".$Pattern."';");
      if(!$rows){
        $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
      }
      else {
        $row = $rows->fetchArray();
        $rtn = $row['id'];
      }
    }

    $db->close();
  }

  return $rtn;
}


function dbDelProfile($ProfileId) {
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    $ret = $db->exec("DELETE FROM profiles WHERE id = ".$ProfileId.";");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Profile Deleted";
    }
    $db->close();
  }
  return $arr;
}


function dbDelProfileSettings($ProfileId) {
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    $ret = $db->exec("DELETE FROM profilesettings WHERE profileid = ".$ProfileId.";");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Profile Settings Deleted";
    }
    $db->close();
  }
  return $arr;
}


function dbDelProfileInclExcl($ProfileId) {
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    $ret = $db->exec("DELETE FROM profileinclexcl WHERE profileid = ".$ProfileId.";");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Profile Includes/Excludes Deleted";
    }
    $db->close();
  }
  return $arr;
}


function dbInsertProfile($ProfileName) {
  // Create a profile, and return the ID & description

  $arr = array();
  // Does it exist?
  $ProfileIdArr = dbSelectProfileIdForProfileName($ProfileName);
  $ProfileId = $ProfileIdArr["id"];
  // No profile on file, so create
  if ($ProfileId <= 0) {
    $db = new MyDB();
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    } else {
      $ret = $db->exec("INSERT INTO profiles (profilename) VALUES ('".$ProfileName."');");
      if(!$ret){
        $arr["result"] = "ko";
        $arr["message"] = $db->lastErrorMsg();
        $arr["id"] = "-1";
        $arr["defaults"] = array();
      }
      $db->close();
    }

    // Does it exist
    $ProfileIdArr = dbSelectProfileIdForProfileName($ProfileName);
    $ProfileId = $ProfileIdArr["id"];
    if ($ProfileId > 0) {
      // Set some default values
      $DefaultsArr = dbInsertDefaultProfileValues($ProfileId);
      $arr["result"] = "ok";
      $arr["message"] = "Profile '".$ProfileName."' defaults created for ID: ".$ProfileId;
      $arr["id"] = $ProfileId;
      $arr["defaults"] = $DefaultsArr;
    }
    else {
      $arr["result"] = "ko";
      $arr["message"] = "Profile '".$ProfileName."' defaults not created for ID: ".$ProfileId;
      $arr["id"] = $ProfileId;
      $arr["defaults"] = array();
    }
  }
  else {
    // Profile exists, do nothing
    $arr["result"] = "ok";
    $arr["message"] = "Profile '".$ProfileName."' already exists as ID: ".$ProfileId;
    $arr["id"] = $ProfileId;
    $arr["defaults"] = array();
  }
  return $arr;
}


function dbInsertDefaultProfileValues($ProfileId) {

  $arr = array();

  $arr["settingssaveto"] = dbInsertUpdateProfileSetting($ProfileId, "settingssaveto", "/shares/backup");
  $arr["selectmode"] = dbInsertUpdateProfileSetting($ProfileId, "selectmode", "modelocal");
  $arr["settingshost"] = dbInsertUpdateProfileSetting($ProfileId, "settingshost", "mycloud");
  $arr["settingsuser"] = dbInsertUpdateProfileSetting($ProfileId, "settingsuser", "root");
  $arr["settingsprofile"] = dbInsertUpdateProfileSetting($ProfileId, "settingsprofile", "1");
  $arr["settingsdeletebackupolderthanperiod"] = dbInsertUpdateProfileSetting($ProfileId, "settingsdeletebackupolderthanperiod", "years");
  $arr["settingsdeletefreespacelessthanunit"] = dbInsertUpdateProfileSetting($ProfileId, "settingsdeletefreespacelessthanunit", "gib");
  $arr["selectschedule"] = dbInsertUpdateProfileSetting($ProfileId, "selectschedule", "manual");
  $arr["defaultexcludes"] = dbInsertDefaultExcludes($ProfileId);
  return $arr;
}

function dbInsertUpdateProfileSetting($ProfileId, $ProfileKey, $ProfileValue) {

  $SettingsArr = dbSelectProfileSettingsId($ProfileId, $ProfileKey);
  $SettingsId = $SettingsArr["id"];

  $arr = array();

  if (!$SettingsId || $SettingsId <= 0) {
    $db = new MyDB();

    // Insert a new record
    $ret = $db->exec("INSERT INTO profilesettings (profileid, profilekey, profilevalue) VALUES ('".$ProfileId."', '".$ProfileKey."','".$ProfileValue."');");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Profile Setting '".$ProfileKey."' saved as: '".$ProfileValue."'";
    }
    $db->close();
  }
  else {
    $db = new MyDB();

    // Update an existing record
    $ret = $db->exec("UPDATE profilesettings SET profilevalue = '".$ProfileValue."' WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Profile Setting '".$ProfileKey."' updated to: '".$ProfileValue."'";
    }
    $db->close();
  }

  return $arr;
}

function dbInsertDefaultExcludes($ProfileId) {

  $arr = array();
  $rtn = array();

  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", "*.backup*");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", "*~");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".Private");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".cache/*");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".dropbox*");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".gvfs");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".thumbnails*");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".[Tt]rash*");
  array_push($rtn, $arr);
  $arr = dbInsertIncludeExcludeValue($ProfileId, "exclude", "p", ".lost+found/*");
  array_push($rtn, $arr);
   return $rtn;
}

function dbInsertIncludeExcludeValue($ProfileId, $InclExcl, $Type, $Pattern) {

  $arr = array();

  $ProfileSettingsId = dbGetFileSpecId($ProfileId, $InclExcl, $Type, $Pattern);
  if ($ProfileSettingsId <= 0) {

    $db = new MyDB();
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    } else {
      // Insert
      $ret = $db->exec("INSERT INTO profileinclexcl (profileid, profilekey, profiletype, profilevalue) VALUES ('".$ProfileId."', '".$InclExcl."', '".$Type."','".$Pattern."');");
      if(!$ret){
        $arr["result"] = "ko";
        $arr["message"] = $db->lastErrorMsg();
      }
      else {
        $arr["result"] = "ok";
        $arr["message"] = "Value stored";
      }

      $db->close();

    } // ProfileSetting does not exist
  }

  return $arr;
}


function dbSelectSnapshotsList($ProfileId) {

  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT id, profileid, snaptime, snapdesc FROM snapshots WHERE profileid = ".$ProfileId." ORDER BY id;");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
    }
    $db->close();
  }
  return $rtn;
}


function dbSelectSnapshotForId($SnapshotId) {

  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Get the snapshot
    $rows = $db->query("SELECT id, profileid, snaptime, snapdesc FROM snapshots WHERE id = ".$SnapshotId.";");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
      $rtn["paths"] = dbGetSnapshotPathsForSnapshotId($SnapshotId);
    }
    $db->close();
  }
  return $rtn;
}


function dbGetSnapshotPathsForSnapshotId($SnapshotId) {
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Get the snapshot
    $rows = $db->query("SELECT id, snapshotid snapid, snapshotpath snappath FROM snapshotpaths WHERE snapshotid = ".$SnapshotId.";");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
    }
    $db->close();
  }
  return $rtn;
}


function dbTakeSnapshot($ProfileId) {
  // FOR NOW, JSUT CREATE A NEW RECORD IN THE SNAPSHOTS TABLE
  $arr = array();

  if ($ProfileId > 0) {

    $db = new MyDB();
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    } else {
      // Insert a record for the snapshot.  Times created by sqlite are stored as UTC
      $ret = $db->exec("INSERT INTO snapshots (profileid, snaptime, snapdesc, snapstatus) VALUES ('".$ProfileId."', strftime('%Y-%m-%dT%H:%M:%f'), '', 'proc');");
      if(!$ret){
        $arr["result"] = "ko";
        $arr["message"] = $db->lastErrorMsg();
      }
      else {
        $arr["result"] = "ok";
        $arr["message"] = "Value stored";
      }
      $db->close();
    } // Snapshot exists
  }

  return $arr;
}


// Deletes a Snapshot plus paths records
function dbDeleteSnapshotPaths($SnapshotId) {

  $Exists = "-1";
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM snapshotpaths WHERE snapshotid = ".$SnapshotId.";");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];

      if ($Exists > 0) {
        $ret = $db->exec("DELETE FROM snapshotpaths WHERE snapshotid = ".$SnapshotId.";");
        if(!$ret){
          $arr["result"] = "ko";
          $arr["message"] = $db->lastErrorMsg();
        }
        else {
          $arr["result"] = "ok";
          $arr["message"] = "Records deleted";
        }
      }
      else {
        $arr["result"] = "ok";
        $arr["message"] = "No snapshot paths for id: ".$SnapshotId;
      }
    }
    $db->close();
  }
  return $arr;
}


// Deletes a Snapshot plus paths records
function dbDeleteSnapshot($SnapshotId) {

  $Exists = "-1";
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
    $arr["paths"] = array();
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM snapshots WHERE id = ".$SnapshotId.";");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
      $arr["paths"] = array();
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];

      if ($Exists > 0) {
        // Delete all snapshot paths too
        $delPathsArr = dbDeleteSnapshotPaths($SnapshotId);
        if ($delPathsArr["result"] == "ok") {
          $ret = $db->exec("DELETE FROM snapshots WHERE id = ".$SnapshotId.";");
          if(!$ret){
            $arr["result"] = "ko";
            $arr["message"] = $db->lastErrorMsg();
            $arr["paths"] = $delPathsArr;
          }
          else {
            $arr["result"] = "ok";
            $arr["message"] = "Record deleted";
            $arr["paths"] = $delPathsArr;
          }
        }
      }
      else {
        $arr["result"] = "ko";
        $arr["message"] = "No such id: ".$SnapshotId;
        $arr["paths"] = array();
      }
    }
    $db->close();
  }
  return $arr;
}

// Web Method Functions ===========================


function getDirectoryContentsFromShell() {

  $chk = array();
  $ls = array();
  $arr = array();
  $pwd = array();
  $int = 0;

  $filetype = SQLite3::escapeString($_POST["type"]);  // Choose 'file' or 'folder'
  $dir = SQLite3::escapeString($_POST["dir"]);        // Where to start browsing
  $sel = SQLite3::escapeString($_POST["sel"]);        // What the user clicked on

  echo "{ \"result\" : \"ok\" , \"items\" : ";

  if (empty($sel)) { $sel = $dir; }
  else {
    $dir = rtrim($dir, "/");
    $sel = rtrim($sel, "/");
    $sel = $dir."/".$sel;
  }

  // Verify that the item exists, and that it is a folder or a link
  $validChdir = 0;
  exec("ls -la \"".$sel."\"", $chk, $int);
  foreach ($chk as $chkline) {
    if (substr($chkline, 0, 1) == "l" || substr($chkline, 0, 1) == "d" ) {
      $validChdir = 1;
    }
  }

  if ($validChdir == 1) {
    // Execute a command, pass output to Array, success indicator
    exec("cd \"".$sel."\" && ls -la", $ls, $int);

    $id = 0;
    foreach ($ls as $dirline) {
      $lsinfo = array();
      // Column1 is the directory indicator
      $dirind = substr($dirline, 0, 1);
      $fname = substr($dirline, 57);
      // Include only directories and regular files
      if ($dirind == 'd' || $dirind == '-' || $dirind == 'l') {

        if (($filetype == "d" && ($dirind == 'd' || $dirind == 'l')) || $filetype != "d") {

          if ($dirind == 'l') {
            // Remove the SymLink symbol & everything to the right: ' ->'
            $fname = substr($fname,0,strpos($fname, " ->"));
          }

          // ********* NEED TO CHECK IF SYMLINKED RESOURCE IS A DIRECTORY, OR A FILE, AND FLAG AS SUCH *********

          if ($fname != '.') {
            $lsinfo["id"] = $id;
            $lsinfo["filetype"] = $dirind;
            $lsinfo["filename"] = $fname;

            array_push($arr, $lsinfo);
            $id = $id + 1;
          }
        }
      }
    }

    exec("cd \"".$sel."\" && pwd", $pwd, $int);
    foreach ($pwd as $pwdline) {
      $sel = $pwdline;
    }

  }
  else {
    $sel = $dir;
  }

  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  echo " , \"newdir\" : \"".$sel."\" }";

  return $arr;
}


function deleteProfile() {
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);

  $arr = dbDeleteProfileRecordSet($ProfileId);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


// Inserts default excludes: *.backup*, *~, .Private, .cache/*, .dropbox*, .gvfs, .thumbnails*, [Tt]rash*, lost+found/*
function addDefaultExcludes() {

  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $arr = array();

  $arr = dbInsertDefaultExcludes($ProfileId);

  $haserr = 0;
  $res = "ok";
  foreach ($arr as $en) {
    if ($en["result"] != "ok") {
      $haserr = 1;
      $res = "ko";
      break;
    }
  }

  echo "{ \"result\" : \"".$res."\" , \"message\" : ";
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  echo " }";
}

// Select all Include or Exclude Settings for a Profile ID
function selectIncludeExclude($InclExcl) {

  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $arr = dbSelectProfileIncludeExclude($ProfileId, $InclExcl);
  echo json_encode(array_change_key_case($arr), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

// Select all profiles from the Profiles list
function selectProfilesList() {
  $SelectedId = SQLite3::escapeString($_POST["selid"]);

  // If SelectedId not specified, resort to Default
  if (!$SelectedId) {
    $ProfileIdArr = dbSelectProfileIdForProfileName("Default");
    $SelectedId = $ProfileIdArr["id"];
  }

  // Get the list from the db
  $rtn = dbSelectProfilesList();

  $items = $rtn["items"];
  $pos = 0;
  foreach ($items as $item) {
    $Selected = "";
    $CanDelete = "0";
    if ($item["id"] == $SelectedId) $Selected = "selected";
    if ($item["profilename"] != "Default") $CanDelete = "1";

    $arr = array();
    $arr["selected"] = $Selected;
    $arr["candelete"] = $CanDelete;

    // Union the arrays
    $rtn["items"][$pos] = $rtn["items"][$pos] + $arr;

    $pos = $pos + 1;
  }

  echo json_encode(array_change_key_case($rtn), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


// Adds a profile to the Profiles list, if not already existing
function addProfile() {
  $ProfileName = SQLite3::escapeString($_POST["profilename"]);
  $arr = array();

  // Is the new Profile Name on file?
  $ProfileIdArr = dbSelectProfileIdForProfileName($ProfileName);
  $id = $ProfileIdArr["id"];

  if ($id > 0) {
    // Already exists
    $arr["result"] = "ok";
    $arr["message"] = "Profile already exists";
    $arr["id"] = $id;
    $arr["defaults"] = array();
  }
  else {
    // Add new profile & default values
    $arr = dbInsertProfile($ProfileName);
  }

  // Output
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


// Select all Settings for a Profile ID, except the file includes
function selectProfileSettings() {
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $arr = dbSelectProfileSettings($ProfileId);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


// Inserts or Updates a Profile Setting key/value pair
function updateProfileSetting() {

  $arr = array();
  $ProfileId    = SQLite3::escapeString($_POST["profileid"]);
  $ProfileKey   = SQLite3::escapeString($_POST["settingname"]);
  $ProfileValue = SQLite3::escapeString($_POST["settingvalue"]);

  $arr = dbInsertUpdateProfileSetting($ProfileId, $ProfileKey, $ProfileValue);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function deleteProfileSetting() {

  $SettingId = SQLite3::escapeString($_POST["settingid"]);

  $arr = dbDeleteProfileSetting($SettingId);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function deleteProfileInclExcl() {

  $SettingId = SQLite3::escapeString($_POST["settingid"]);

  $arr = dbDeleteProfileInclExcl($SettingId);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function insertIncludeFileFolder() {
  // Add the user's selected path
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $FilePath  = SQLite3::escapeString($_POST["filepath"]);
  $FileType  = SQLite3::escapeString($_POST["filetype"]);

  if ($FileType == "d" && substr($FilePath, strlen($FilePath) - 1, 1) != "/" ) {
    $FilePath = $FilePath."/";
  }

  echo "{ \"result\" : ";

  // Is the entry already on file, if not add
  $arr = dbInsertIncludeExcludeValue ($ProfileId, "include", $FileType, $FilePath);

  echo "\"".$arr["result"]."\" , \"message\" : ";
  echo json_encode($arr["message"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  echo " } ";
}


function insertExcludeFileFolder() {
  // Add the user's selected path
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $FilePath  = SQLite3::escapeString($_POST["filepath"]);
  $FileType  = SQLite3::escapeString($_POST["filetype"]);

  if ($FileType == "d" && substr($FilePath, strlen($FilePath) - 1, 1) != "/" ) {
    $FilePath = $FilePath."/";
  }

  echo "{ \"result\" : ";

  // Is the entry already on file, if not add
  $arr = dbInsertIncludeExcludeValue ($ProfileId, "exclude", $FileType, $FilePath);

  echo "\"".$arr["result"]."\" , \"message\" : ";
  echo json_encode($arr["message"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  echo " } ";
}


function updateSchedule() {

  // The separate JSON calls for this group lock the database, so resolve this here...
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $ScheduleOpts = json_decode(SQLite3::escapeString($_POST["scheduleopt"]), true);

  $arr = array();
  $res = array();

  $pos = 0;
  foreach($ScheduleOpts as $so) {
    $arr = dbInsertUpdateProfileSetting($ProfileId, $so["key"], $so["val"]);
    $res[$so["key"]] = $arr;
    $pos = $pos + 1;
  }

  echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function init() {
  $rtn = array();
  $rtn["dbinit"] = db_create_schema();
  $rtn["default"] = dbInsertProfile("Default");
  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function selectFullProfile($ProfileId) {
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  // Profile
  // ProfileSettings
  // ProfileInclExcl
  $AllProfile = array();

  $ProfileItems = dbSelectProfileForId($ProfileId);
  $AllProfile["profile"] = $ProfileItems[items];

  $ProfileSettings = dbSelectProfileSettings($ProfileId);
  $AllProfile["profilesettings"] = $ProfileSettings[items];

  $ProfileIncl = dbSelectProfileIncludeExclude($ProfileId, "include");
  $AllProfile["profileinclude"] = $ProfileIncl[items];

  $ProfileExcl = dbSelectProfileIncludeExclude($ProfileId, "exclude");
  $AllProfile["profileexclude"] = $ProfileExcl[items];

  $SnapList = dbSelectSnapshotsList($ProfileId);
  $AllProfile["snaplist"] = $SnapList[items];

  echo json_encode($AllProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function selectSnapshotsList(){
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);

  $arr = dbSelectSnapshotsList($ProfileId);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function takeSnapshot() {
  $ProfileId = SQLite3::escapeString($_POST["profileid"]);

  $arr = dbTakeSnapshot($ProfileId);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function deleteSnapshot() {
  $SnapshotId = SQLite3::escapeString($_POST["snapshotid"]);

  $rtn = array();

  // Does the snapshot record have a description?  If so, refuse to delete
  $arr = dbSelectSnapshotForId($SnapshotId);
  if ($arr["result"] == "ok") {
    if ( !empty($arr["items"][0]["snapdesc"]) ) {
      $rtn["result"] = "ko";
      $rtn["message"] = "Cannot delete a snapshot with a description.";
    }
    else {
      // Snapshot found and there is no description
      $arr = dbDeleteSnapshot($SnapshotId);
      $rtn = $arr;
    }
  }
  else {
    // No snapshot found
    $rtn = $arr;
  }

  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}


function writeErrorMsg() {
  echo "{ \"result\" : \"De nada deniro!\" }";
}

// ==============================================================================


// What shall we run?
$WhatToRun = $_POST["fn"];

switch ($WhatToRun) {
  case "init":
    init();
    break;
  case "selectprofileslist":
    selectProfilesList();
    break;
  case "selectfullprofile":
    selectFullProfile();
    break;
  case "selectprofilesettings":
    selectProfileSettings();
    break;
  case "updateprofilesetting":
    updateProfileSetting();
    break;
  case "deleteprofilesetting":
    deleteProfileSetting();
    break;
  case "getdirectorycontents":
    getDirectoryContentsFromShell();
    break;
  case "adddefaultexcludes":
    addDefaultExcludes();
    break;
  case "getincludepatterns":
    selectIncludeExclude("include");
    break;
  case "getexcludepatterns":
    selectIncludeExclude("exclude");
    break;
  case "insertincludefilefolder":
    insertIncludeFileFolder();
    break;
  case "insertexcludefilefolder":
    insertExcludeFileFolder();
    break;
  case "deleteprofileinclexcl":
    deleteProfileInclExcl();
    break;
  case "addprofile":
    addProfile();
    break;
  case "deleteprofile":
    deleteProfile();
    break;
  case "updateschedule":
    updateSchedule();
    break;
  case "selectsnapshotslist":
    selectSnapshotsList();
    break;
  case "takenewsnapshot":
    takeSnapshot();
    break;
  case "deletesnapshot":
    deleteSnapshot();
    break;
  default:
    writeErrorMsg();
}

?>
