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
// Real Solution:
// .htaccess file in the directory of the php files
// Contents:
//   php_flag apc.cache_by_default 0
//


// ==============================================================================
// Data Access Layer Functions
// ==============================================================================


class MyDB extends SQLite3 {
  function __construct() {
     $this->open('app.sqlite.db');
  }
}

// Database Creation
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
        inclexcl                      CHAR(10) NOT NULL,
        filetype                      CHAR(2) NOT NULL,
        filepath                      CHAR(500) NOT NULL
      );

      CREATE TABLE IF NOT EXISTS snapshots (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        profileid                     INTEGER NOT NULL,
        snaptime                      TEXT    NOT NULL,
        snapdesc                      TEXT            ,
        snapstatus                    INTEGER NOT NULL,
        snapbasepath                  TEXT    NOT NULL
      );

      CREATE TABLE IF NOT EXISTS snapshotprofiles (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        snapshotid                    INTEGER NOT NULL,
        profileid                     INTEGER NOT NULL
      );

      CREATE TABLE IF NOT EXISTS snapshotpaths (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        snapshotid                    INTEGER NOT NULL,
        profileid                     INTEGER NOT NULL,
        snapshotinclexcl              CHAR(10) NOT NULL,
        snapshotpathtype              CHAR(2) NOT NULL,
        snapshotpath                  TEXT    NOT NULL
      );

      CREATE TABLE IF NOT EXISTS snapshotpids (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        snapshotid                    INTEGER NOT NULL,
        snapshotpathid                INTEGER NOT NULL,
        snapshotpid                   INTEGER NOT NULL
      );

      CREATE TABLE IF NOT EXISTS codelist (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        codetype                      CHAR(20) NOT NULL,
        codename                      CHAR(20) NOT NULL,
        codedesc                      CHAR(50) NOT NULL
      );

      -- Insert statuses
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'system', 'appname', 'timesync' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'system' and codelist.codename = 'appname');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'rsyn', 'Taking rsync snapshot' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'rsyn');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'snap', 'Rsync Snapshot Completed' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'snap');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'psac', 'Post Snapshot Actions being called' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'psac');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'smrt', 'Processing Smart Delete' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'smrt');
      INSERT INTO codelist (codetype, codename, codedesc) SELECT 'snapstatus', 'comp', 'Process completed' WHERE NOT EXISTS (SELECT id FROM codelist WHERE codelist.codetype = 'snapstatus' and codelist.codename = 'comp');
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

function dbSelectCodelistRecord($CodeType, $CodeName) {
  // Returns a CodeList record for a ProfileId and ProfileKey
  $Exists = "0";
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["items"] = array();
    $rtn["message"] = $db->lastErrorMsg();
  } else {
    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM codelist WHERE codetype = '".$CodeType."' AND codename = '".$CodeName."';");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["items"] = array();
      $rtn["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray(SQLITE3_ASSOC);
      $Exists = $row["count"];
    }
    if ($Exists > 0) {
      $rows = $db->query("SELECT * FROM codelist WHERE codetype = '".$CodeType."' AND codename = '".$CodeName."';");
      if(!$rows){
        $rtn["result"] = "ko";
        $rtn["items"] = array();
        $rtn["message"] = $db->lastErrorMsg();
      }
      else {
        $row = $rows->fetchArray(SQLITE3_ASSOC);
        $rtn["items"] = array();
        $rtn["result"] = "ok";
        array_push($rtn["items"], $row);
        $rtn["message"] = "Found";
      }
    }
    else {
      $rtn["result"] = "ok";
      $rtn["items"] = array();
      $rtn["message"] = "Not found";
    }

    $db->close();
  }

  return $rtn;
}

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
      $row = $rows->fetchArray(SQLITE3_ASSOC);
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

// Select all enabled profiles from the Profiles list
function dbSelectProfilesEnabledList() {
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT id, profilename FROM profiles JOIN (SELECT profileid, profilekey setkey, profilevalue setval FROM profilesettings WHERE profilekey = 'settingsprofileactive' and profilevalue = 1) AS ps ON ps.profileid = profiles.id ORDER BY profilename;");
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
  // Returns a ProfileSettings ID ProfileId and ProfileKey
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
      $row = $rows->fetchArray(SQLITE3_ASSOC);
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
        $row = $rows->fetchArray(SQLITE3_ASSOC);
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
  // Selects all ProfileSettings for a ProfileId
  $rtn = array();
  $rtn["items"] = array();
  $rtn["result"] = "ko";
  $rtn["message"] = "General Error";

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT profilekey setkey, profilevalue setval FROM profilesettings WHERE profileid = ".$ProfileId.";");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      while($row = $rows->fetchArray(SQLITE3_ASSOC)){
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Fetched Items";
    }
    $db->close();
  }
  return $rtn;
}

function dbSelectProfileSettingsRecord($ProfileId, $ProfileKey) {
  // Returns a ProfileSettings record for a ProfileId and ProfileKey
  $Exists = "0";
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["items"] = array();
    $rtn["message"] = $db->lastErrorMsg();
  } else {
    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["items"] = array();
      $rtn["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray(SQLITE3_ASSOC);
      $Exists = $row["count"];
    }
    if ($Exists > 0) {
      $rows = $db->query("SELECT id, profileid, profilekey, profilevalue FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
      if(!$rows){
        $rtn["result"] = "ko";
        $rtn["items"] = array();
        $rtn["message"] = $db->lastErrorMsg();
      }
      else {
        $row = $rows->fetchArray(SQLITE3_ASSOC);
        $rtn["items"] = array();
        $rtn["result"] = "ok";
        array_push($rtn["items"], $row);
        $rtn["message"] = "Found";
      }
    }
    else {
      $rtn["result"] = "ok";
      $rtn["items"] = array();
      $rtn["message"] = "Not found";
    }

    $db->close();
  }

  return $rtn;
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
      $row = $rows->fetchArray(SQLITE3_ASSOC);
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
    $rows = $db->query("SELECT id setid, inclexcl setkey, filetype settype, filepath setval FROM profileinclexcl WHERE profileid = ".$ProfileId." AND inclexcl = '".$InclExcl."';");
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
      $row = $rows->fetchArray(SQLITE3_ASSOC);
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
    $rows = $db->query("SELECT COUNT(*) AS count FROM profileinclexcl WHERE profileid = '".$ProfileId."' AND inclexcl = '".$InclExcl."' AND filetype = '".$Type."' AND filepath = '".$Pattern."';");
    if (!$rows) {
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
    } else {
      $row = $rows->fetchArray(SQLITE3_ASSOC);
      $Exists = $row['count'];
    }


    if ($Exists > 0) {
      $rows = $db->query("SELECT id AS id FROM profileinclexcl WHERE profileid = '".$ProfileId."' AND inclexcl = '".$InclExcl."' AND filetype = '".$Type."' AND filepath = '".$Pattern."';");
      if(!$rows){
        $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
      }
      else {
        $row = $rows->fetchArray(SQLITE3_ASSOC);
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
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    } else {
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
  }
  else {
    $db = new MyDB();
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    } else {
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
      $ret = $db->exec("INSERT INTO profileinclexcl (profileid, inclexcl, filetype, filepath) VALUES ('".$ProfileId."', '".$InclExcl."', '".$Type."','".$Pattern."');");
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

function dbSelectSnapshotsList() {

  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query("SELECT snapshots.id, snapshots.profileid, snapshots.snaptime, snapshots.snapdesc, ifnull(ps.dontremovenamed,0) dontremovenamed, snapstatus FROM snapshots LEFT JOIN (SELECT profileid, profilevalue dontremovenamed FROM profilesettings WHERE profilekey = 'settingsdontremovenamed') ps ON ps.profileid = snapshots.profileid ORDER BY snaptime desc;");

    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        if ($row["dontremovenamed"] == 1 && !empty($row["snapdesc"])) $row["candel"] = 0;
        else $row["candel"] = 1;
        array_pop($row["dontremovenamed"]);  // remove the database field from the results
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
    }
    $db->close();
  }
  return $rtn;
}

function dbSelectSnapshotRecordForId($SnapshotId) {

  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Get the snapshot
//    $rows = $db->query("SELECT * FROM snapshots WHERE id = ".$SnapshotId.";");
    $rows = $db->query("SELECT snapshots.*, stat.statusdesc AS statusdesc FROM snapshots JOIN (SELECT codename, codedesc AS statusdesc FROM codelist WHERE codetype = 'snapstatus') stat WHERE snapshots.snapstatus = snapshots.snapstatus AND stat.codename = snapshots.snapstatus;");

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
      $rtn["message"] = "Records returned";
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
    $rows = $db->query("SELECT * FROM snapshots WHERE id = ".$SnapshotId.";");
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

function dbSelectSnapshotsForStatus($SnapshotStatusArr) {

  $rtn = array();
  $rtn["items"] = array();
  $rtn["result"] = "ko";
  $rtn["message"] = "Unknown error";

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Get the snapshot records for each status
    foreach($SnapshotStatusArr as $SnapshotStatus) {
      $rows = $db->query("SELECT snapshots.*, stat.statusdesc AS statusdesc FROM snapshots JOIN (SELECT codename, codedesc AS statusdesc FROM codelist WHERE codetype = 'snapstatus') stat WHERE snapshots.snapstatus = '".$SnapshotStatus."' AND stat.codename = snapshots.snapstatus;");
      if (!$rows) {
        $rtn["result"] = "ko";
        $rtn["message"] = $db->lastErrorMsg();
        break;
      }
      else {
        while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
          array_push($rtn["items"], $row);
        }
        $rtn["result"] = "ok";
        $rtn["message"] = "Items returned";
      }
    }
    $db->close();
  }

  return $rtn;
}

function dbSelectSnapshotsOverAge($ProfileId, $SnapshotAge) {

  // $SnapshotAge = '+2 years', '+1 day', etc)

  $rtn = array();

  // Take into account whether named snapshots can be deleted
  $DontDelNamed = '';
  // No record here = checkbox unset
  // Record exists and is set
  if (count($chk["items"]) > 0) {
    if ($chk["items"][0]["profilevalue"] == "1") {
      $DontDelNamed = " and ifnull(snapshots.snapdesc,'') = '' ";
    }
  }

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Get the snapshot list
    $rows = $db->query("SELECT * FROM snapshots WHERE profileid = ".$ProfileId." AND snaptime < date('now','".$SnapshotAge."')".$DontDelNamed.";");
    if (!$rows) {
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
      $rtn["items"] = array();
    }
    else {
      $rtn["items"] = array();
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        // Use the Snapshot ID to get the snapshot & paths records
        array_push($rtn["items"], $row);
      }
      $rtn["result"] = "ok";
      $rtn["message"] = "Items returned";
    }
    $db->close();
  }
  return $rtn;
  // DEBUG DEBUG DEBUG DEBUG
//  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
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
    $rows = $db->query("SELECT id, snapshotid snapid, snapshotpath snappath, snapshotinclexcl snapinclexcl, snapshotpathtype snaptype FROM snapshotpaths WHERE snapshotid = ".$SnapshotId.";");
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

function dbGetTimeStampDirStr() {

  $rtn = array();
  $rtn["result"] = "ko";
  $rtn["items"] = array();
  $rtn["message"] = "Unknown error";

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
  } else {
    // Get the timestamp to use
    $rows = $db->query("SELECT strftime('%Y-%m-%dT%H:%M:%f') ts;");
    if(!$rows){
      $rtn["result"] = "ko";
      $rtn["message"] = $db->lastErrorMsg();
    }
    else {
      $TimeStr = "";
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        $TimeStr = $row["ts"];
        break;
      }

      // Remove all of the punctuation from the timestamp
      $rtn["result"] = "ok";
      $rtn["items"][0] = $TimeStr;
      $rtn["message"] = "Delivered Timestamp";
    }
  }
  
  return $rtn;
}

function dbCreateSnapshotRecords($ProfileId, $TimeStr) {
  // Create a new record in the snapshots table, and copy the profile paths to the snapshotpaths table
  $arr = array();
  $arr["snapshot"] = array();
  $arr["result"] = "";
  $arr["message"] = "";
  $arr["snapshot"]["snapshotpaths"] = array();
  $SnapshotId = "-1";

  if ($ProfileId > 0) {
    
    // Get Snapshot Settings record, to get the full snapshot path
    $FullPathArr = dbSelectProfileSettingsRecord($ProfileId, "settingsfullsnapshotpath");
    $BackupBasePath = $FullPathArr["items"][0]["profilevalue"];
    $BackupTSPath = $BackupBasePath."/".removeTimestampPunct($TimeStr);

    $db = new MyDB();
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    } else {

      // Insert a record for the snapshot.  Times created by sqlite are stored as UTC
      $ret = $db->exec("INSERT INTO snapshots (profileid, snaptime, snapdesc, snapstatus, snapbasepath) VALUES ('".$ProfileId."', '".$TimeStr."', '', 'rsyn', '".$BackupTSPath."');");
      if(!$ret){
        $arr["result"] = "ko";
        $arr["message"] = $db->lastErrorMsg();
      }
      else {
        $arr["result"] = "ok";
        $arr["message"] = "Snapshot record created";

        // Find that snapshot id using the timestr
        $rows = $db->query("SELECT * FROM snapshots WHERE profileid = ".$ProfileId." AND snaptime = '".$TimeStr."';");
        if(!$rows){
          $arr["result"] = "ko";
          $arr["message"] = $db->lastErrorMsg();
        }
        else {
          while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
            array_push($arr["snapshot"], $row);
            $SnapshotId = $row["id"];
            break;
          }

          // Create the Snapshot Paths
          // As well as being a record of the snapshot paths,
          // it'll be used to take the snapshot
          $pathres = array();
          $pathres["items"] = array();
          $ret = $db->exec("INSERT INTO snapshotpaths (snapshotid, snapshotinclexcl, snapshotpathtype, snapshotpath) SELECT ".$SnapshotId.", inclexcl, filetype, filepath FROM profileinclexcl WHERE profileid = ".$ProfileId.";");
          if(!$ret){
            $pathres["result"] = "ko";
            $pathres["message"] = $db->lastErrorMsg();
          }
          else {
            $rows = $db->query("SELECT * FROM snapshotpaths WHERE snapshotid = ".$SnapshotId.";");
            if(!$rows){
              $arr["result"] = "ko";
              $arr["message"] = $db->lastErrorMsg();
            }
            else {              
              $pathres["result"] = "ok";
              $pathres["message"] = "Path records returned";
              $pathres["items"] = array();
              while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
                array_push($pathres["items"], $row);
              }
            }
          }
          // Add to the Snapshot array
          $arr["snapshot"]["snapshotpaths"] = $pathres;
        }
      }
      $db->close();
    } // Snapshot exists
  }

  return $arr;
}

// Deletes paths records for a Snapshot
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
      $row = $rows->fetchArray(SQLITE3_ASSOC);
      $Exists = $row["count"];

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

// Deletes all pid records for a Snapshot ID
function dbDeleteSnapshotPidsForSnapshotId($SnapshotId) {

  $Exists = "-1";
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM snapshotpids WHERE snapshotid = ".$SnapshotId.";");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray(SQLITE3_ASSOC);
      $Exists = $row["count"];

      if ($Exists > 0) {
        $ret = $db->exec("DELETE FROM snapshotpids WHERE snapshotid = ".$SnapshotId.";");
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
        $arr["message"] = "No snapshot pids for id: ".$SnapshotId;
      }
    }
    $db->close();
  }
  return $arr;
}

// Deletes a Snapshot plus paths records
function dbDeleteSnapshot($SnapshotId) {

  $Exists = "-1";
  $arr["result"] = "";
  $arr["message"] = "";
  $arr["paths"] = array();
  $arr["pids"] = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM snapshots WHERE id = ".$SnapshotId.";");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $row = $rows->fetchArray(SQLITE3_ASSOC);
      $Exists = $row["count"];
    }
    $db->close();
  }

  if ($Exists > 0) {
    // Get the Snapshots header
    $SnapshotRecs = dbSelectSnapshotForId($SnapshotId);

    // Get the snapshot profile id, use that to get the CanDeleteNamed flag
    $ProfileId = $SnapshotRecs["items"][0]["profileid"];
    $SnapshotName = $SnapshotRecs["items"][0]["snapdesc"];

    // Check for the "Don't Remove Named Snapshots" option being set
    $CanDelNamedSnap = dbGetCanDeleteNamedSnapshots($ProfileId);

    // If $CanDelNamedSnap = 0, check if there's no description
    $CanDelSnap = 0;
    if ($CanDelNamedSnap == 0) {
      if (empty($SnapshotName) ) {
        $CanDelSnap = 1;
      }
    }
    else {
      $CanDelSnap = 1;
    }
    
    if ($CanDelSnap == 1) {
      // Get the snapshot's base directory
      // Get the backup path from the database record
      $BackupTSPath = $SnapshotRecs["items"][0]["snapbasepath"];
      $BackupBasePath = substr($BackupTSPath, 0, strrpos($BackupTSPath, "/"));
      $BackupDir = substr($BackupTSPath, strrpos($BackupTSPath, "/") + 1);

      // Delete this snapshot path from disk
      exec("cd \"".$BackupBasePath."\" && rm -rf \"".$BackupDir."\"", $chk, $int);
      
      // Check for existence of the directory
      $validChdir = 0;
      exec("cd \"".$BackupTSPath."\" && pwd", $chk, $int);
      foreach ($chk as $chkline) {
        $validChdir = 1;
      }
      
      if ($validChdir == 0) {

        // Re-point the 'current' symlink, if required
        $arr["current"] = dbPointCurrentSymlink($BackupBasePath);

        // Delete all snapshot path records too
        $delPathsArr = dbDeleteSnapshotPaths($SnapshotId);

        // Delete all snapshot path records too
        $delPidsArr = dbDeleteSnapshotPidsForSnapshotId($SnapshotId);

        if ($delPathsArr["result"] == "ok") {
          $db = new MyDB();
          if(!$db) {
            $arr["result"] = "ko";
            $arr["message"] = $db->lastErrorMsg();
            $arr["paths"] = $delPathsArr;
            $arr["pids"] = $delPidsArr;
          } else {
            $ret = $db->exec("DELETE FROM snapshots WHERE id = ".$SnapshotId.";");
            if(!$ret){
              $arr["result"] = "ko";
              $arr["message"] = $db->lastErrorMsg();
              $arr["paths"] = $delPathsArr;
              $arr["pids"] = $delPidsArr;
            }
            else {
              $arr["result"] = "ok";
              $arr["message"] = "Snapshot record deleted";
              $arr["paths"] = $delPathsArr;
              $arr["pids"] = $delPidsArr;
            }
            $db->close();
          }
        }
      }  // directory was deleted
      else {
        $arr["result"] = "ko";
        $arr["message"] = "Could not delete snapshot directory: ".$BackupTSPath;
      }
    }
    else {
      $arr["result"] = "ko";
      $arr["message"] = "Cannot delete a named snapshot";
    }
  }
  else {
    $arr["result"] = "ko";
    $arr["message"] = "No such id: ".$SnapshotId;
  }

  return $arr;
}

function dbDeleteLastSnapshot($ProfileId) {

  // Delete the last snapshot for the profile id
  $rtn = array();

  // Take into account whether named snapshots can be deleted
  $DontDelNamed = '';
  // No record here = checkbox unset
  // Record exists and is set
  if (count($chk["items"]) > 0) {
    if ($chk["items"][0]["profilevalue"] == "1") {
      $DontDelNamed = " and ifnull(snapshots.snapdesc,'') = '' ";
    }
  }

  // Query to get the last valid snapshot to delete, ensuring that
  // there is always going to be one snapshot remaining for a profile
  $sql = "select min(id) id, (select count(profileid) cnt from snapshots md where md.profileid = snapshots.profileid) cnt from snapshots where profileid = ".$ProfileId." ".$DontDelNamed." and cnt > 1;";
  $res = dbExecQuery($sql);
  foreach($res["items"] as $item) {
    $rtn = dbDeleteSnapshot($item['id']);
  }

  return $rtn;
}

// Points the "current" symlink to the latest existing directory
function dbPointCurrentSymlink($BackupBasePath) {

  $rtn = array();

  // Ensures that the 'current' symlink points to something, or is not there
  $inObj = array();
  $inObj["filetype"] = "-";
  $inObj["hid"] = "";
  $inObj["dir"] = $BackupBasePath;
  $inObj["sel"] = "";
  $DirContents = dbGetDirectoryContentsFromShell($inObj);

  $ValidChdir = 0;
  $SymlinkFound = 0;
  foreach($DirContents["items"] as $item){
    if ($item["filetype"] == "l" && $item["filename"] == "current") {
      $SymlinkFound = 1;
      exec("cd \"".$item["symlink"]."\" && pwd", $chk, $int);
      foreach ($chk as $chkline) {
        $ValidChdir = 1;
      }     
    }
  }

  // If wherever the symlink points to does not exist, remove it
  if ($SymlinkFound == 1) {
    if ($ValidChdir == 0) {
      $cmd = "cd \"".$BackupBasePath."\" && rm current";
      exec($cmd, $chk, $int);
    }
  }

  // Where should 'current' point?  List directories only.
  $LastDir = "";
  exec("cd \"".$BackupBasePath."\" && ls -ltr | grep ^d", $dir, $int);
  if (count($dir) > 0) {
    $LastDir = substr($dir[count($dir) - 1], 57);
    $cmd = "cd \"".$BackupBasePath."\" && ln -s \"".$BackupBasePath."/".$LastDir."\" current";
    exec($cmd, $chk, $int);
  }

  return $rtn;
}

function dbUpdateSnapshotName($SnapshotId, $SnapshotName){
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Update an existing record
    $ret = $db->exec("UPDATE snapshots SET snapdesc = '".$SnapshotName."' WHERE id = ".$SnapshotId.";");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Snapshot Name updated.";
    }
    $db->close();
  }

  return $arr;
}

function dbUpdateSnapshotStatus($SnapshotId, $SnapshotStatus){
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Update an existing record
    $ret = $db->exec("UPDATE snapshots SET snapstatus = '".$SnapshotStatus."' WHERE id = ".$SnapshotId.";");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Snapshot Status updated.";
    }
    $db->close();
  }

  return $arr;
}

function dbCreateSnapshotPid($SnapshotId, $SnapshotPathId, $SnapshotPid){
  $arr = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Update an existing record
    $ret = $db->exec("INSERT INTO snapshotpids (snapshotid, snapshotpathid, snapshotpid) VALUES (".$SnapshotId.", ".$SnapshotPathId.", ".$SnapshotPid.");");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Snapshot Pid created";
    }
    $db->close();
  }

  return $arr;
}

function dbDeleteSnapshotPid($id){
  $arr = array();
  $arr["items"] = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
  } else {
    // Delete a record
    $ret = $db->exec("DELETE FROM snapshotpids WHERE id = ".$id.";");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Snapshot Pid deleted";
    }
    $db->close();
  }

  return $arr;
}

function dbSelectSnapshotPidForSnapshotId($SnapshotId) {

  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Get the snapshot
    $rows = $db->query("SELECT * FROM snapshotpids WHERE snapshotid = ".$SnapshotId.";");
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

function dbGetDirectoryContentsFromShell($in) {
  // Array elements for $in 
  // filetype - 'file' or 'folder'
  // dir      - Where to start browsing
  // sel      - What the user last clicked on
  // hid      - Show Hidden Files

  $chk = array();
  $ls = array();
  $arr = array();
  $pwd = array();
  $int = 0;
  $rtn = array();
  
  $filetype = $in["filetype"];
  $hid = $in["hid"];
  $dir = $in["dir"];
  $sel = $in["sel"];

  if (empty($sel)) { $sel = $dir; }
  else {
    $dir = rtrim($dir, "/");
    $sel = rtrim($sel, "/");
    $sel = $dir."/".$sel;
  }

  // If not asking to change up a directory ("..")
  // Verify that the directory exists
  $validChdir = 0;
  exec("cd \"".$sel."\" && pwd", $chk, $int);
  foreach ($chk as $chkline) {
    $validChdir = 1;
  }

  // Valid to change into this directory
  if ($validChdir == 1) {

    // If show hidden is checked, include the 'a' switch
    $cmd = "ls -le";
    if ($hid == 1) $cmd = $cmd."A";  // A = a, but doesn't show '.' or '..'

    // Get the contents of the clicked into directory
    // Append the 'awk' command to combine multiple spaces into a single one,
    // so that 'explode' can be reliably used on the output string
    // The output columns are space separated. E.g.
    //   drwxrwxrwx 4 user share 4096 Mon Oct 26 21:36:23 2015 A File Name
    $cmd = 'cd "'.$sel.'" && '.$cmd.' | awk '."'".' { gsub (/ [ ]+/," "); print }'."'";
    // Execute the command, pass output to $ls, success indicator to $int
    exec($cmd, $ls, $int);

    $id = 0;
    foreach ($ls as $dirline) {
      $lsinfo = array();

      // Explode the output to query the file info
      $dirArr = explode(" ", $dirline);
      $dirind = substr($dirArr[0], 0, 1);
      $fdate = strftime("%Y-%m-%d %H:%M:%S", strtotime($dirArr[9]."-".$dirArr[6]."-".$dirArr[7]." ".$dirArr[8]));
      $fsize = $dirArr[4];
      $slink = ""; // symlink destination

      $fname = '';
      $pos = 10;
      while ($pos < count($dirArr)) {
        if (!empty($fname)) $fname = $fname." ";
        $fname = $fname.$dirArr[$pos];
        $pos = $pos + 1;
      }

      // Include only directories and regular files
      if ($dirind == 'd' || $dirind == '-' || $dirind == 'l') {
        // What kind of object is symlinked to? d, -, neither
        $slinktype = "";
        if ($dirind == 'l') {
          // Get the SymLink destination to the right of the symbol
          $slink = substr($fname,strpos($fname, " -> ") + 4);
          // Now remove the SymLink symbol & everything to the right: ' ->'
          $fname = substr($fname,0,strpos($fname, " -> "));
          $fsize = 0;
          // Does the SymLink link to a file, to a folder, or to something else
          $symlcmd = "if [[ -d '".$sel."/".$fname."' ]]; then echo 'd'; elif [[ -f '".$sel."/".$fname."' ]]; then echo '-'; else echo ''; fi";
          // Execute the command, and get the result from the returned array
          exec($symlcmd, $slinktype, $int);
          $slinktype = $slinktype[0];

          // Replace the type of object seen in the frontend. This
          // means there will be no SymLinks seen, and SymLinks
          // can be chosen as include/exclude files/folders
          if ($dirind == 'l') $dirind = $slinktype;
        }

        if (($filetype == "d" && ($dirind == 'd' || $dirind == 'l')) || $filetype != "d") {

          // Set file size to zero
          if ($dirind == 'd') {
            $fsize = 0;
          }

          // Add to the returned object
          if ($fname != '.') {
            $lsinfo["id"] = $id;
            $lsinfo["filetype"] = $dirind;
            $lsinfo["filename"] = $fname;
            $lsinfo["filesize"] = $fsize;
            $lsinfo["filedate"] = $fdate;
            $lsinfo["symlink"]  = $slink;
            $lsinfo["symlinktype"]  = $slinktype;

            array_push($arr, $lsinfo);
            $id = $id + 1;
          }
        }
      }
    }

    // Get the directory just changed into, using pwd
    // Gets around the problem of it beng a symlink
    exec("cd \"".$sel."\" && pwd", $pwd, $int);
    foreach ($pwd as $pwdline) {
      $sel = $pwdline;
    }

  }
  else {
    $sel = $dir;
  }

  // Build return array
  $rtn["result"] = "ok";
  $rtn["newdir"] = $sel;
  $rtn["items"] = $arr;

  return $rtn;
}

function dbSelectSshOpts($SnapshotId) {
  // Build the SSH part of the rsync command
  // using the SSH options records
  $arr = array();

  // Is it an ssh type?
  $sshOpt = array();
  $chk = dbSelectProfileSettingsRecord($ProfileId, "selectmode");
  if (count($chk["items"]) > 0) {
    if ($chk["items"][0]["profilevalue"] == "modessh") {
      $sshdbopt = dbSelectProfileSettingsRecord($ProfileId, "settingssshhost");
      $sshOpt["host"] = $sshdbopt["items"][0]["profilevalue"];
      $sshdbopt = dbSelectProfileSettingsRecord($ProfileId, "settingssshport");
      $sshOpt["port"] = $sshdbopt["items"][0]["profilevalue"];
      $sshdbopt = dbSelectProfileSettingsRecord($ProfileId, "settingssshuser");
      $sshOpt["user"] = $sshdbopt["items"][0]["profilevalue"];
      $sshdbopt = dbSelectProfileSettingsRecord($ProfileId, "settingssshpath");
      $sshOpt["path"] = $sshdbopt["items"][0]["profilevalue"];
      $sshdbopt = dbSelectProfileSettingsRecord($ProfileId, "privatekey");
      $sshOpt["privatekey"] = $sshdbopt["items"][0]["profilevalue"];
      $sshdbopt = dbSelectProfileSettingsRecord($ProfileId, "settingssshprivatekeypassword");
      $sshOpt["privatekeypassword"] = $sshdbopt["items"][0]["profilevalue"];
      $arr = $sshOpt;
    }
  }

  return $arr;
}

function dbSelectRsyncExcludesIncludes($SnapshotId) {
  // Build the Exclude/Include part of the rsync command
  // using the snapshotpaths records
  $arr = array();
  $arr["result"] = "";
  $arr["message"] = "";
  $arr["items"] = array();

  $db = new MyDB();
  if(!$db) {
    $arr["result"] = "ko";
    $arr["message"] = $db->lastErrorMsg();
    $arr["cmd"] = "";
  } else {
    // Get the excludes and then the includes
    $rows = $db->query("SELECT sp.pathid snappathid, snapshots.snaptime snaptime, sp.snapshotinclexcl snapshotinclexcl, sp.snapshotpathtype snapshotpathtype, sp.snapshotpath snapshotpath FROM snapshots JOIN (SELECT id pathid, snapshotid, snapshotinclexcl, snapshotpathtype, snapshotpath FROM snapshotpaths) AS sp ON sp.snapshotid = snapshots.id WHERE snapshots.id = ".$SnapshotId." ORDER BY snapshotinclexcl, pathid;");
    if(!$rows){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
      $arr["cmd"] = "";
    }
    else {
      while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        array_push($arr["items"], $row);
      }
      $arr["result"] = "ok";
      $arr["message"] = "Command includes/excludes found";
    }
    $db->close();
  }
  return $arr;
}

function dbExecOSCommand($cmd) {
// Line 1675
  // Execute a command, pass output to Array, success indicator
  // https://serverfault.com/questions/205498/how-to-get-pid-of-just-started-process
  //   myCommand & echo $!
  //   sh -c 'echo $$; exec myCommand'
  //   $ myCommand ; pid=$!
  exec("sh -c 'echo $$; exec ".$cmd."'", $ls, $int);  
//  exec($cmd." 2>&1 > out.log & echo $!", $ls, $int);  
  return $ls;
}

function dbRemoveSnapshotsOverAge($ProfileId) {
  // For a profile, remove any snapshots older than X years
  // This is called as part of the Take Snapshot instruction

  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["message"] = "";
  $rtn["items"] = array();

  $DelOlderThanValue = dbGetSmartRemoveParameter($ProfileId, "settingsdeleteolderthan", "settingsdeleteolderthanage");

  // Is it checked & the age assigned a value?
  if ($DelOlderThanValue > 0) {
    // Get the units of that age
    $chk = dbSelectProfileSettingsRecord($ProfileId, "settingsdeletebackupolderthanperiod");
    // Possible periods: days, weeks, months, and years
    if (count($chk["items"]) > 0) {
      $DelOlderThanPeriod = $chk["items"][0]["profilevalue"];
    }

    // Get all snapshot records over that age
    $SnapshotList = dbSelectSnapshotsOverAge($ProfileId, "-".$DelOlderThanValue." ".$DelOlderThanPeriod);

    // Go through the snapshot list, and delete the snapshots
    $rtn["message"] = "No snapshots to delete";
    if (count($SnapshotList["items"]) > 0) {
      // Potentially there are snapshots to delete
      // Can we delete named snapshots
      foreach($SnapshotList["items"] as $snap) {
        array_push($rtn["items"], dbDeleteSnapshot($snap["id"]));
      }
      $rtn["message"] = "Deleted snapshots";
    }

  } // DelOlderThanChecked is checked
  else {
    $rtn["message"] = "'Delete Older Than' is unchecked or is not set";
  }

  return $rtn;
}

function dbGetSmartRemoveParameter($ProfileId, $CheckboxId, $FillinId) {

  // Is the parameter checkbox checked?
  $chk = dbSelectProfileSettingsRecord($ProfileId, $CheckboxId);

  $rtn = 0;
  // No record here = checkbox unset
  if ( count($chk["items"]) > 0 ) {
    // Record exists and is set
    if ($chk["items"][0]["profilevalue"] == "1") {
      $val = dbSelectProfileSettingsRecord($ProfileId, $FillinId);
      if ( count($val["items"]) > 0 ) {
        $rtn = $val["items"][0]["profilevalue"];
      }
    }
  }

  return $rtn;
}

function dbSmartRemove($ProfileId) {

  $arr = array();
  $arr["deleted"] = array();
  $arr["message"] = "No Snapshots were deleted by smart-remove";

  // Is the Smart Remove checkbox checked?
  $IsChecked = 0;
  $chk = dbSelectProfileSettingsRecord($ProfileId, "settingssmartremove");
  if (count($chk["items"]) > 0) {
    if ($chk["items"][0]["profilevalue"] == "1") {
      $IsChecked = 1;
    }
  }

  // If checked, get the other values
  if ($IsChecked == 1) {
    // Get the parameter settings
    $KeepAllForDays = dbGetSmartRemoveParameter($ProfileId, "settingssmartkeepallfordays", "settingssmartkeepallfordaysvalue");
    $KeepOnePerDayForDays = dbGetSmartRemoveParameter($ProfileId, "settingssmartkeeponeperdayfordays", "settingssmartkeeponeperdayfordaysvalue");
    $KeepOnePerWeekForWeeks = dbGetSmartRemoveParameter($ProfileId, "settingssmartkeeponeperweekforweeks", "settingssmartkeeponeperweekforweeksvalue");
    $KeepOnePerMonthForMonths = dbGetSmartRemoveParameter($ProfileId, "settingssmartkeeponepermonthformonths", "settingssmartkeeponepermonthformonthsvalue");

    // Check for the "Don't Remove Named Snapshots" option being set
    $chk = dbSelectProfileSettingsRecord($ProfileId, "settingsdontremovenamed");

    // Take into account whether named snapshots can be deleted
    $DontDelNamed = '';
    $DontDelNamedSQ = '';
    // No record here = checkbox unset
    // Record exists and is set
    if (count($chk["items"]) > 0) {
      if ($chk["items"][0]["profilevalue"] == "1") {
        $DontDelNamed = " and ifnull(snapshots.snapdesc,'') = '' ";
        $DontDelNamedSQ = " and ifnull(md.snapdesc,'') = '' ";
      }
    }

    // The specified times are all concurrent, based on today. WYGIWYD.

    $KeepIds = array();
    // Add the keeper IDs for 'Keep all for days'
    if ($KeepAllForDays > 0) {
      $sql = "select id as kid from snapshots where profileid = ".$ProfileId." and snaptime >= date('now','-".$KeepAllForDays." days')".$DontDelNamed.";";
      $res = dbExecQuery($sql);
      foreach($res["items"] as $item) {
        if(!in_array($item["kid"], $KeepIds)) {
          $KeepIds[count($KeepIds)] = $item["kid"];
        }
      }
    }

    // Add the keeper IDs for 'One Per Day For Days' - newest
    if ($KeepOnePerDayForDays > 0){
      $lowday = $KeepOnePerDayForDays;
      $highday = $KeepAllForDays;
      $sql = "select distinct (select max(id) from snapshots md where md.profileid = ".$ProfileId." and strftime('%Y-%m-%d',md.snaptime) = strftime('%Y-%m-%d',snapshots.snaptime)".$DontDelNamedSQ.") kid from snapshots WHERE snapshots.profileid = ".$ProfileId." and snapshots.snaptime >= date('now','-".$KeepOnePerDayForDays." days')".$DontDelNamed.";";
      $res = dbExecQuery($sql);
      foreach($res["items"] as $item) {
        if(!in_array($item["kid"], $KeepIds)) {
          $KeepIds[count($KeepIds)] = $item["kid"];
        }
      }
    }

    // Add the keeper IDs for 'One Per Week For Weeks'
    if ($KeepOnePerWeekForWeeks > 0){
      $sql = "select distinct (select max(id) from snapshots md where md.profileid = ".$ProfileId." and strftime('%Y-%W',md.snaptime) = strftime('%Y-%W',snapshots.snaptime)".$DontDelNamedSQ.") kid from snapshots WHERE snapshots.profileid = ".$ProfileId." and snapshots.snaptime >= date('now','-".$KeepOnePerWeekForWeeks." days')".$DontDelNamed.";";
      $res = dbExecQuery($sql);
      foreach($res["items"] as $item) {
        if(!in_array($item["kid"], $KeepIds)) {
          $KeepIds[count($KeepIds)] = $item["kid"];
        }
      }
    }

    // Add the keeper IDs for 'One Per Month For Months'
    if ($KeepOnePerMonthForMonths > 0){
      $sql = "select distinct (select max(id) from snapshots md where md.profileid = ".$ProfileId." and strftime('%Y-%m',md.snaptime) = strftime('%Y-%m',snapshots.snaptime)".$DontDelNamedSQ.") kid from snapshots WHERE snapshots.profileid = ".$ProfileId." and snapshots.snaptime >= date('now','-".$KeepOnePerMonthForMonths." months')".$DontDelNamed.";";
      $res = dbExecQuery($sql);
      foreach($res["items"] as $item) {
        if(!in_array($item["kid"], $KeepIds)) {
          $KeepIds[count($KeepIds)] = $item["kid"];
        }
      }
    }

    // Add the keeper IDs for 'Keep One Per Year For All Years'
    $sql = "select distinct (select max(id) from snapshots md where md.profileid = ".$ProfileId." and strftime('%Y',md.snaptime) = strftime('%Y',snapshots.snaptime)".$DontDelNamedSQ.") kid from snapshots WHERE snapshots.profileid = ".$ProfileId." and".$DontDelNamed.";";
    $res = dbExecQuery($sql);
    foreach($res["items"] as $item) {
      if(!in_array($item["kid"], $KeepIds)) {
        $KeepIds[count($KeepIds)] = $item["kid"];
      }
    }
    
    // We now have the ids to be kept, so build a query to get the ids to delete
    $sql = "select id from snapshots where profileid = ".$ProfileId." and id not in (".implode(",", $KeepIds).")".$DontDelNamed.";";
    $res = dbExecQuery($sql);
    
    // Call the standard function to delete the snapshots
    $cnt = 0;
    foreach($res["items"] as $item) {
      $arr["items"][$cnt] = dbDeleteSnapshot( $item["id"] );
      $cnt = $cnt + 1;
    }
    if ($cnt > 0) $arr["message"] = "Snapshots were deleted by smart-remove";
  }

  return $arr;
}

// Select all profiles from the Profiles list
function dbExecQuery($sql) {
  $rtn = array();

  $db = new MyDB();
  if(!$db) {
    $rtn["result"] = "ko";
    $rtn["message"] = $db->lastErrorMsg();
    $rtn["items"] = array();
  } else {
    // Sort in ascending order - this is default
    $rows = $db->query($sql);
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

function dbGetCanDeleteNamedSnapshots($ProfileId) {

  // This flips the select, to read 'Can Delete Named Snapshots'

  // Check for the "Don't Remove Named Snapshots" option being set
  $chk = dbSelectProfileSettingsRecord($ProfileId, "settingsdontremovenamed");

  $CanDelSnap = 0;
  // No record here = checkbox unset
  if ( count($chk["items"]) == 0 ) {
    $CanDelSnap = 1;
  }
  else {
    // Record exists and is unset
    if ($chk["items"][0]["profilevalue"] == "0") {
      $CanDelSnap = 1;
    }
  }

  return $CanDelSnap;
} 

function dbFreeSpaceCheck($ProfileId) {
  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["message"] = "";
  $rtn["availspaceMB"] = 0;
  $rtn["requiredspaceMB"] = 0;
  $rtn["freeok"] = "ko";
  
  // Is the delete if free space option set?
  $FreeSpaceCheck = dbGetSmartRemoveParameter($ProfileId, "settingsdeletefreespacelessthan", "settingsdeletefreespacelessthanvalue");
  $FreeSpaceUnits = "";
  // It's checked and there's a value here
  if ($FreeSpaceCheck > 0) {
    // Get the units of free space to check for
    $chk = dbSelectProfileSettingsRecord($ProfileId, "settingsdeletefreespacelessthanunit");
    // Record exists and is set
    if (count($chk["items"]) > 0) {
      $FreeSpaceUnits = strtolower( $chk["items"][0]["profilevalue"] );
    }

    // The filesystem space will be quoted in 1MB blocks
    // so convert the user specified space to MB
    $multiplier = 1;
    switch ($FreeSpaceUnits) {
      case "GiB":
        $multiplier = 1024;
        break;
      case "TiB":
        $multiplier = 1048576;  // 1024 * 1024
        break;
      default:
        $multiplier = 1;
        break;
    }

    // Convert to MiB
    $RequiredSpace = $FreeSpaceCheck * $multiplier;

    // For this profile, get where to save snapshots
    $SnapshotPathArr = dbSelectProfileSettingsRecord($ProfileId, "settingssaveto");
    $SnapshotPath = $SnapshotPathArr["items"][0]["profilevalue"];

    // Get the free space
    // df -k "/mnt/USB/USB1_e1/Backup" | awk ' { gsub (/ [ ]+/," "); print }'
    $cmd = 'df -k "'.$SnapshotPath.'" | awk '."'".' { gsub (/ [ ]+/," "); print }'."'";
    exec($cmd, $res, $int);

    // $ df -m "/mnt/USB/USB1_e1/Backup" | awk ' { gsub (/ [ ]+/," "); print }'
    // Filesystem Inodes Used Available Use% Mounted on
    // /dev/mapper/md1 243671040 203854 243467186 0% /mnt/HD/HD_a2

    // Parse the output of the command
    // Split into an array using the space separator
    $resArr = explode(" ", $res[1]);
    $freeok = "ok";
    if ($resArr[3] - $RequiredSpace < 0) $freeok = "ko";
    
    // Space is in 1024b blocks, so make the multiplication

    // Return the number of free inodes
    $rtn["availspaceMB"] = $resArr[3];
    $rtn["requiredspaceMB"] = $RequiredSpace;
    $rtn["freeok"] = $freeok;
    $rtn["message"] = "'Amount of Free Space' returned";   
  }
  else {
    $rtn["message"] = "'Delete Free Space' is unchecked or is not set";   
  }

  return $rtn;
}

function dbFreeInodesCheck($ProfileId) {
  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["message"] = "";
  $rtn["availinodes"] = 0;
  $rtn["requiredinodes"] = 0;
  $rtn["freeok"] = "ko";

  // Is the delete if free inodes option set?
  $FreeInodeCheck = dbGetSmartRemoveParameter($ProfileId, "settingsdeleteinodeslessthan", "settingsdeleteinodeslessthanvalue");
  if ($FreeInodeCheck > 0) {
    // For this profile, get where to save snapshots
    $SnapshotPathArr = dbSelectProfileSettingsRecord($ProfileId, "settingssaveto");
    $SnapshotPath = $SnapshotPathArr["items"][0]["profilevalue"];

    // Get the free inodes 
    $cmd = 'df -i "'.$SnapshotPath.'" | awk '."'".' { gsub (/ [ ]+/," "); print }'."'";
    exec($cmd, $res, $int);

    // $ df -i "/mnt/USB/USB1_e1/Backup" | awk ' { gsub (/ [ ]+/," "); print }'
    // Filesystem Inodes Used Available Use% Mounted on
    // /dev/sde1 122101760 9521 122092239 0% /mnt/USB/USB1_e1

    // Parse the output of the command
    // Split into an array using the space separator
    $resArr = explode(" ", $res[1]);
    $freeok = "ok";
    if ($resArr[3] - $FreeInodeCheck < 0) $freeok = "ko";

    // Return the number of free inodes
    $rtn["availinodes"] = $resArr[3];
    $rtn["requiredinodes"] = $FreeInodeCheck;
    $rtn["freeok"] = $freeok;
    $rtn["message"] = "'Number of Free Inodes' returned";   
  }
  else {
    $rtn["message"] = "'Delete Free Inodes' is unchecked or is not set";   
  }

  return $rtn;
}

function dbTakeSnapshot($ProfileArr, $dbTimeStampDirStr) {

  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["items"] = array();
  $rtn["message"] = "";

  $ProfileCount = 0;
  foreach($ProfileArr["items"] as $Profile) {

    $ProfileId = $Profile["id"];

    // Initialise the return array for this profile
    $arr["result"] = "ko";
    $arr["items"] = array();
    $arr["message"] = "Profile ID: ".$ProfileId." - unknown error";

    // If it's a local 
    // Check for the backup type/mode
    $chk = dbSelectProfileSettingsRecord($ProfileId, "selectmode");
    if (count($chk["items"]) > 0) {
      switch ($chk["items"][0]["profilevalue"]) {
        case "modelocal":
          $arr = dbTakeLocalSnapshot($ProfileId, $dbTimeStampDirStr);
          break;
        case "modessh":  
          $arr = dbTakeSshSnapshot($ProfileId, $dbTimeStampDirStr);
          break;
        default:
          $arr["result"] = "ok";
          $arr["items"] = array();
          $arr["message"] = "Unknown backup type: ".$chk["items"][0]["profilevalue"];
      }
    }

    // Add the result to the output array
    array_push($rtn["items"], $arr);
  } // foreach Profile

  return $rtn;
}

function dbTakeSshSnapshot($ProfileId, $dbTimeStampDirStr) {
  // Creates snapshot files by executing rsync commands on a remote machine
  // Presupposes that the user has connected at least once before
  // Assumes that there are SSH keys available - Private here, Public on remote
  // Creates an SSH session on the remote machine, and executes commands
  $arr = array();
  $arr["snapshotrecords"] = array();
  $arr["result"] = "ok";
  $arr["message"] = "SSH backup function not yet available";

  return $arr;
}

function dbTakeLocalSnapshot($ProfileId, $dbTimeStampStr) {
  
  // ssh user1@server1 date
  // ssh user1@server1 'df -H'
  // ssh root@nas01 uname -mrs
  
  // Creates snapshot files on a locally connected filesystem/folder

  // Create the Snapshot records
  // Use the Snapshot records to build the rsync command exclude/include parts
  $arr = array();
  $arr["snapshotrecords"] = array();
  $arr["result"] = "";
  $arr["message"] = "";

  // Check for at least one included directory
  $ProfileIncl = dbSelectProfileIncludeExclude($ProfileId, "include");
  $HasIncludedDir = 0;
  foreach ( $ProfileIncl["items"] as $Incl ) {
    if ($Incl["settype"] == "d") {
      $HasIncludedDir = 1;
      break;
    }
  }

  if ($HasIncludedDir == 1) {
    // Create a record set for this snapshot, returns the record set (header and includes/excludes)
    
    $arr["snapshotrecords"] = dbCreateSnapshotRecords($ProfileId, $dbTimeStampStr);

    $SnapshotId = "-1";
    if ($arr["snapshotrecords"]["result"] == "ok") {
      // Get the Snapshot ID from the returned arrays
      $SnapshotId = $arr["snapshotrecords"]["snapshot"][0]["id"];

      // Get the backup path from the database record
      $BackupTSPath = $arr["snapshotrecords"]["snapshot"][0]["snapbasepath"];
      // Work out where 'current' should be
      $BackupCurrentPath = substr($BackupTSPath, 0, strrpos($BackupTSPath, "/"))."/current";

      // Get the includes and excludes of this snapshot profile
      $InexArr = dbSelectRsyncExcludesIncludes($SnapshotId);

      // Append the backup path with the TimeStamp, and ensure that path exists
      $mdcmd = "mkdir -p \"".$BackupTSPath."\"";    
      $mdres = dbExecOSCommand($mdcmd);

      // Go through each snapshot path to include, and call rsync for it
      // Excludes covers all directories, files, and patterns
      // Includes covers only patterns
      $inex = "";
      foreach($InexArr["items"] as $item) {
        // Excludes
        if ($item["snapshotinclexcl"] == "exclude") {
          $inex = $inex." --exclude \"".$item["snapshotpath"]."\"";
        }
        // Includes
        if ($item["snapshotinclexcl"] == "include" && $item["snapshotpathtype"] != "d") {
          $inex = $inex." --include \"".$item["snapshotpath"]."\"";
        }
      }

      // Build the command for each include directory
      // These will be run asynchronously
      foreach($InexArr["items"] as $item) {
        if ($item["snapshotinclexcl"] == "include" && $item["snapshotpathtype"] == "d") {

          // Work out the full path of the backup, base plus include
          $FullBackupPath = $BackupTSPath."/".$ProfileId."/".$item["snapshotpath"];
          $mdcmd = "mkdir -p \"".$FullBackupPath."\"";    
          exec($mdcmd, $cmdres, $int);

          // Build the parameters part of the command
          $params = $inex." --link-dest=\"".$BackupCurrentPath."\" \"".$item["snapshotpath"]."\" \"".$FullBackupPath."\"";

          // Execute the rsync command asynchronously and store the PID as returned
          $cmd = "/usr/sbin/rsync -aPr --delete --log-file=/mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/scripts/rsync.log ".$params. ' > /dev/null 2>&1 & echo $!; ';
          $RsyncPid = exec($cmd, $output);

          // Save the PID to the snapshotpids table
          $pidrtn = dbCreateSnapshotPid($SnapshotId, $item["snappathid"], $RsyncPid);
          array_push($arr["pid"], $RsyncPid); 

          // DEBUG
          $f = fopen("log.txt", "w");
          fwrite($f, "CMD: ".$cmd."\nPID: ".json_encode($RsyncPid)."\nOUTPUT: ".json_encode($output));
          fclose($f);
        }
      }

      $arr["result"] = "ok";
      $arr["message"] = "Snapshot underway!";
    }
  }
  else {
    $arr["result"] = "ko";
    $arr["message"] = "No Backup Folders (Include Directories) were found.";
  }

  return $arr;
}

function dbPostSnapshotActions() {

  $rtn = array();

  $statuses = array();
  array_push($statuses, "psac"); // post snapshot actions (smart-delete)

  // Get all snapshots that are waiting
  $Snapshots = dbSelectSnapshotsForStatus($statuses);
  // Run the process to update the snapshot status
  foreach($Snapshots["items"] as $Snapshot) {
  
    // Get the Profile ID of the current Snapshot
    $ProfileId = $Snapshot["profileid"];
    
    // This is run after the backups are completed
    // Called by a CURL script run asynchronouely from the command line
        
    // Remove the old symbolic link, and point a new one
    $rmres = dbExecOSCommand("rm \"".$BackupCurrentPath."\"");
    // Point a new Symbolic Link
    $lnres = dbExecOSCommand("ln -s \"".$BackupTSPath."\" \"".$BackupCurrentPath."\"");


    // Remove snapshots over age
    $arr["removeoverage"] = dbRemoveSnapshotsOverAge($ProfileId);

    // Now deal with any Smart Remove options
    $arr["smartremove"] = dbSmartRemove($ProfileId);

    // Deal with deletions required by free inodes specifier
    $max = 100;
    $try = 0;
    $chk = dbFreeInodesCheck($ProfileId);
    $arr["freeinodes"][$try] = $chk;
    while ($chk["freeok"] == "ko" && $try < $max) {
      $arr["freeinodes"][$try]["deletedsnapshot"] = dbDeleteLastSnapshot($ProfileId);

      // Is there enough free inode yet?
      $try = $try + 1;
      $chk = dbFreeInodesCheck($ProfileId);
      $arr["freeinodes"][$try] = $chk;
    }

    // Deal with deletions required by free space specifier
    $max = 100;
    $try = 0;
    $chk = dbFreeSpaceCheck($ProfileId);
    $arr["freespace"][$try] = $chk;
    while ($chk[0]["freeok"] == "ko" && $try < $max) {
      $arr["freespace"][$try]["deletedsnapshot"] = dbDeleteLastSnapshot($ProfileId);

      // Is there enough free space yet?
      $try = $try + 1;
      $chk = dbFreeSpaceCheck($ProfileId);
      $arr["freespace"][$try] = $chk;
    }
    
    // Mark as complete
    $arr = dbUpdateSnapshotStatus($SnapshotId, "comp");
  }
  
  // Copy across the database file
  // Need to get the profilesetting backup dir
  $cmd = "cp '/mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/php/app.sqlite.db' '".$BackupTSPath."'";
  exec($cmd, $cmdres, $int);

  $rtn["items"] = array();
  $rtn["result"] = "ok";
  $rtn["message"] = "Snapshots Completed";
  
}

function dbUpdateSnapshotStatusForRsync($SnapshotId) {
  // Checks whether or not the rsync process is running
  // If not, it updates the status to 'auto'

  $rtn = array();

  // Get the Snapshot PID records
  $SnapshotPids = dbSelectSnapshotPidForSnapshotId($SnapshotId);

  // Are the process IDs still executing
  if (count($SnapshotPids) > 0) {
    foreach($SnapshotPids["items"] as $PidRec) {
      // Get the pid and build the os command
      $pid = $PidRec["snapshotpid"];
      $cmd = 'ps -a | grep '.$pid.' | grep rsync';

      $res = array();  // initialise
      $cmdres = exec($cmd, $res, $return_var );

      // Was there a valid pid? Check for a result line without grep
      $liveprocess = false;
      foreach($res as $resline) {
        if (strpos($resline, 'grep') == false) { 
          $liveprocess = true;
          break;
        }
      }

      // If the process is not running any more.
      if ( $liveprocess == false ) {
        // Delete the PID record
        $delpid = dbDeleteSnapshotPid( $PidRec["id"] );
      }
    }

    // Get a new count of snapshotpid records for the snapshot id
    $SnapshotPids = dbSelectSnapshotPidForSnapshotId($SnapshotId); 
  }

  if ( count($SnapshotPids["items"]) == 0 ) {
    // No PID records remain - update the snapshot status to 'snap' - snapshot completed
    $arr = dbUpdateSnapshotStatus($SnapshotId, "snap");
  }

  // Get the snapshot record in case of reporting back on
  $Snapshot = dbSelectSnapshotRecordForId($SnapshotId);

  $rtn["items"] = $Snapshot["items"];
  $rtn["result"] = "ok";
  $rtn["message"] = "Snapshot Status Returned";

  return $rtn;
}

function dbCallSmartDelete() {
  $rtn = array();

  $statuses = array();
  array_push($statuses, "snap"); // snapshot completed (rsync)

  // Get all snapshots that are complete
  $Snapshots = dbSelectSnapshotsForStatus($statuses);
  
  if (count($Snapshots) > 0) {
    // Update the snapshot statuses to signify Post Snapshot Actions to be run
    foreach($Snapshots as $Snapshot) {
      $arr = dbUpdateSnapshotStatus($Snapshot["id"], "psac");
    }
    // Now there's a need to call the auto delete process asynchronously
    // It's a hack (well, pretty much all of this is!), but curl should do the trick!
    // curl -X POST -H "Content-Type: text/json" -d '{"fn": "postSnapshotActions"}' https://localhost/timesync/vendor/app/php/app.php
    shell_exec('curl -X POST -H "Content-Type: text/json" -d '."'".'{"fn": "postSnapshotActions"}'."'".' https://localhost/timesync/vendor/app/php/app.php &');
  }

  return $rtn;
}

function dbUpdateSnapshotStatuses() {
  // This is called from the JS Front End and nudges on the snapshot a bit
  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["items"] = array();
  $rtn["message"] = "Statuses Updated";
  
  $statuses = array();
  array_push($statuses, "rsyn"); // processing snapshot (rsync)

  // Get all snapshots that are processing (should only ever be one at a time)
  $Snapshots = dbSelectSnapshotsForStatus($statuses);
  // Run the process to update the snapshot status
  foreach($Snapshots["items"] as $Snapshot) {
    // The rsync happens asynchronously, so the JS callback enables this check
    $arr = dbUpdateSnapshotStatusForRsync($Snapshot["id"]);

    array_push($rtn["items"], $arr["items"]);
  }
  
  // This will call Post Snapshot Actions for the correct status ('snap' - snapshot completed)
  dbCallSmartDelete();

  return $rtn;
}
/*
function dbGetSnapshotsByStatus($SnapshotStatus) {
  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["items"] = array();
  $rtn["message"] = "No snapshots are at status: ".$SnapshotStatus;

  $statuses = array();
  array_push($statuses, $SnapshotStatus);

  // Get all snapshots that are processing
  $arr = dbSelectSnapshotsForStatus($statuses);
  $rtn["items"] = $arr["items"];

  if (count($Snapshots) > 0) {
    $rtn["message"] = "Found snapshots at status: ".$SnapshotStatus;
  }

  return $rtn;
}
*/
// ==============================================================================
// Web Method Functions
// ==============================================================================

function getDirectoryContentsFromShell() {

  $filetype = SQLite3::escapeString($_POST["type"]);  // Choose 'file' or 'folder'
  $dir = SQLite3::escapeString($_POST["dir"]);        // Where to start browsing
  $sel = SQLite3::escapeString($_POST["sel"]);        // What the user clicked on
  $hid = SQLite3::escapeString($_POST["hid"]);        // Show Hidden
  $snapid = SQLite3::escapeString($_POST["snapid"]);  // Snapshot ID

  if ($snapid != "now") {
    // Get the snapshot's base directory
    $SnapshotRecs = dbSelectSnapshotForId($snapid);

    // Get the backup path from the database record
    $BackupTSPath = $SnapshotRecs["items"][0]["snapbasepath"];

    // Trim slashes as appropriate
    $BackupTSPath = rtrim($BackupTSPath, "/");
    $dir = ltrim(rtrim($dir, "/"), "/");
    // The snapshot path should prepend the wanted path
    $dir = $BackupTSPath."/".$dir;
  }

  $dirArr = array();
  $dirArr["filetype"] = $filetype;
  $dirArr["dir"] = $dir;
  $dirArr["sel"] = $sel;
  $dirArr["hid"] = $hid;
  $arr = dbGetDirectoryContentsFromShell($dirArr);

  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
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

function updateNoDelNamed() {
  $rtn = array();
  $ProfileId    = SQLite3::escapeString($_POST["profileid"]);
  $ProfileKey   = SQLite3::escapeString($_POST["settingname"]);
  $ProfileValue = SQLite3::escapeString($_POST["settingvalue"]);

  // Attempt the update
  $rtn["updatenodelnamed"] = dbInsertUpdateProfileSetting($ProfileId, $ProfileKey, $ProfileValue);
  
  // Get the updated snapshots list
  $rtn["snaplist"] = dbSelectSnapshotsList();

  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function updateSettingsPaths() {

  $arr = array();
  $ProfileId    = SQLite3::escapeString($_POST["profileid"]);
  $SettingsArr  = json_decode(SQLite3::escapeString($_POST["settings"]), true);

  foreach($SettingsArr as $so) {
    $arr = dbInsertUpdateProfileSetting($ProfileId, $so["key"], $so["val"]);
    $res[$so["key"]] = $arr;
  }

  echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
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

  foreach($ScheduleOpts as $so) {
    $arr = dbInsertUpdateProfileSetting($ProfileId, $so["key"], $so["val"]);
    $res[$so["key"]] = $arr;
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
  $Dir       = SQLite3::escapeString($_POST["dir"]);
  // Profile
  // ProfileSettings
  // ProfileInclExcl
  $AllProfile = array();

  // Profile Info
  $ProfileItems = dbSelectProfileForId($ProfileId);
  $AllProfile["profile"] = $ProfileItems[items];

  // Profile Settings
  $ProfileSettings = dbSelectProfileSettings($ProfileId);
  $AllProfile["profilesettings"] = $ProfileSettings["items"];

  // Includes for the current Profile
  $ProfileIncl = dbSelectProfileIncludeExclude($ProfileId, "include");
  $AllProfile["profileinclude"] = $ProfileIncl[items];

  // Excludes for the current Profile
  $ProfileExcl = dbSelectProfileIncludeExclude($ProfileId, "exclude");
  $AllProfile["profileexclude"] = $ProfileExcl[items];

  // A list of all snapshots, for the side menu
  $SnapList = dbSelectSnapshotsList();
  $AllProfile["snaplist"] = $SnapList[items];

  // Update any snapshots that might be in progress
  $UpdatedStatuses = dbUpdateSnapshotStatuses();
  if ( count($UpdatedStatuses["items"]) == 0 ) {
    $UpdatedStatuses = array();
  }
  else {
    $UpdatedStatuses = $UpdatedStatuses["items"][0];
  }
  $AllProfile["updatedstatuses"] = $UpdatedStatuses;
  
  // Return a list of any running snapshots
//  $RunningSnapshots = dbGetSnapshotsByStatus("rsyn");
//  $AllProfile["runningsnapshots"] = $RunningSnapshots[items];

  $dirArr = array();
  $dirArr["filetype"] = "-";
  $dirArr["dir"] = $Dir;
  $dirArr["sel"] = "";
  $dirArr["hid"] = "";
  $DirList = dbGetDirectoryContentsFromShell($dirArr);
  $AllProfile["showfiles"] = $DirList[items];

  echo json_encode($AllProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function selectSnapshotsList(){

  $arr = dbSelectSnapshotsList();
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function removeTimestampPunct($TimeStamp) {
  return str_replace(".", "_", str_replace(":", "", str_replace("T", "_", str_replace("-", "", $TimeStamp))));
}

function buildRsyncSsh($SshOpt) {
  
  // Mount the ssh filesystem, if required
  if (count($SshOpt) > 0) {
    $SshCmd[0] = ' -e "ssh';
    // ssh part
    if (!empty($SshOpt["port"])) {
      $SshCmd[0] = $SshCmd[0]." -p ".$SshOpt["port"];   // e.g.:  -e 'ssh -p 2222'
    }
    $SshCmd[0] = $SshCmd[0]." -i /mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/profiles/".$ProfileId."/".$SshOpt["privatekey"].'"';
    
    // User@Host:Path
    $SshCmd[1] = $SshOpt["user"]."@".$SshOpt["host"].":".$SshOpt["path"];
    

  }
}

function takeSnapshot() {

  // Create the Snapshot records
  // Decide what kind of snapshot, and go for it!
  $ProfileCount = 0;
  $rtn = array();
  $rtn["result"] = "ko";
  $rtn["message"] = "No active snapshot configuration found";
  $rtn["items"] = array();

  // Get the TimeStampDir to save everything in
  $dbTimeStampRes = dbGetTimeStampDirStr();
  if ($dbTimeStampRes["result"] == 'ok') {
    $dbTimeStampDirStr = $dbTimeStampRes["items"][0];

    // Get all Profile records
    $ProfileArr = dbSelectProfilesEnabledList();

    // For each profile
    if ($ProfileArr["result"] == "ok") {
      if (count($ProfileArr["items"]) > 0) {

        // Now execute the code to take a snapshot
        // Here the $rtn is returned as the snapshots are taken asynchronously
        $rtn = dbTakeSnapshot($ProfileArr, $dbTimeStampDirStr);

        $rtn["result"] = "ok";
        $rtn["message"] = "Snapshot started";
        $rtn["timestamp"] = $dbTimeStampDirStr;
      }
      else {
        $rtn["result"] = "ko";
        $rtn["message"] = "Could not get any profile items";
      }
    }
    else {
      $rtn["result"] = "ok";
      $rtn["message"] = "No enabled snapshot profiles found";
    }
  }
  else {
    $rtn["result"] = "ok";
    $rtn["message"] = "No timestamp directory string could be made";
  }
  
  // Return to the client
  // Return a new snapshot list to save making an extra ajax callback
  $rtn["snaplist"] = dbSelectSnapshotsList();
  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function deleteSnapshot() {
  $SnapshotId = SQLite3::escapeString($_POST["snapshotid"]);

  $rtn = array();

  if ($SnapshotId == "0" || $SnapshotId == "now") {
    $rtn["result"] = "ko";
    $rtn["message"] = "You cannot delete the 'Now' snapshot";
  }
  else {
    // Does the snapshot record have a description?  
    // Is the "Don't Remove Named Snapshots" checkbox set?
    // If both are true, refuse to delete 
    $arr = dbSelectSnapshotForId($SnapshotId);  
    if ($arr["result"] == "ok") {  
      $arr = dbDeleteSnapshot($SnapshotId);
      $rtn = $arr;
      
      // Return the latest list of snapshots to save making an extra ajax callback
      if ($arr["result"] == "ok") {
        $rtn["snaplist"] = dbSelectSnapshotsList();
      }
    }
    else {
      // No snapshot found
      $rtn = $arr;
    }
  }
  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function updateSnapshotName(){
  $SnapshotId = SQLite3::escapeString($_POST["snapshotid"]);
  $SnapshotName= SQLite3::escapeString($_POST["snapshotname"]);

  $rtn = dbUpdateSnapshotName($SnapshotId, $SnapshotName);
  if ($rtn["result"] == "ok") {
    $rtn["snaplist"] = dbSelectSnapshotsList();
  }

  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function selectSnapshotData() {

  $rtn = array();
  $SnapshotId = SQLite3::escapeString($_POST["snapshotid"]);

  // Get the Snapshot backup paths
  $rtn["snapshot"] = dbSelectSnapshotForId($SnapshotId);

  // Get the initial directory listing of the first snapshot backup path


  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function getSnapshotStatusForId() {
  // Gets the status of the snapshot
  $rtn = array();
  $SnapshotId = SQLite3::escapeString($_POST["snapshotid"]);

  // Get the Snapshot Status
  $rtn["snapshotstatus"] = dbGetSnapshotStatusForId($SnapshotId);

  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function updateSnapshotStatuses(){
  $rtn = dbUpdateSnapshotStatuses();
  if ( count($rtn["items"]) == 0 ) {
    $rtn = array();
  }
  else {
    $rtn = $rtn["items"][0];
  }
  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}
/*
function getCurrentSnapshotsStatus() {

  $rtn = array();
  // rsyn - rsync; snap - snapshot completed; psac - post-snapshot actions
  $CurrentSnapshots = dbGetSnapshotsByStatus("rsyn","snap","psac");

  foreach($SnapshotsInProgres as $Snapshots) {
    // Get the Snapshots in progress
    $rtn["currentsnapshotsstatus"] = dbSelectSnapshotRecordForId($SnapshotId);
  }

  // Return the snapshots in motion
  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}
*/
function postSnapshotActions() {
  // Called by the CURL command
  $rtn = array();
  $rtn["result"] = "ok";
  $rtn["message"] = "Post Snapshot Actions called";
  $rtn["items"] = array();
  
  dbPostSnapshotActions();
  echo json_encode($rtn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}

function writeErrorMsg() {
  echo "{ \"result\" : \"Sin dinero, sin esquí!\" }";
}


// ==============================================================================
// AJAX time
// ==============================================================================


// What do we want to run?
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
  case "updatenodelnamed":
    updateNoDelNamed();
    break;
  case "updatesettingspaths":
    updateSettingsPaths();
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
  case "updatesnapshotname":
    updateSnapshotName();
    break;
  case "selectsnapshotdata":
    selectSnapshotData();
    break;
//  case "getsnapshotstatus":
// Not called, as yet
//    getSnapshotStatusForId();
//    break;
  case "updatesnapshotstatuses":
    // Not called as yet
    updateSnapshotStatuses();
    break;
  case "postSnapshotActions":
    postSnapshotActions();
    break;
  default:
    writeErrorMsg();
}

?>
