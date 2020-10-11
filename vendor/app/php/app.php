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

function getProfileId($ProfileName) {

  $Exists = 0;
  $rtn = "-1";

  $db = new MyDB();
  if(!$db) {
    $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
  } else {

    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profiles WHERE profilename = '".$ProfileName."';");
    if (!$rows) {
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
    } else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];
    }


    if ($Exists > 0) {
      $rows = $db->query("SELECT id AS id FROM profiles WHERE profilename = '".$ProfileName."';");
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

function getProfileSettingsId($ProfileId, $ProfileKey) {
  // If there is no profile, create one to default to
  $Exists = "0";
  $rtn = "-1";

  $db = new MyDB();
  if(!$db) {
    $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  } else {

    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
    if (!$rows) {
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];
    }

    if ($Exists > 0) {
      $rows = $db->query("SELECT id FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$ProfileKey."';");
      if(!$rows){
        $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
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

function getFileSpecId($ProfileId, $InclExcl, $Pattern) {

  $Exists = 0;
  $rtn = "-1";

  $db = new MyDB();
  if(!$db) {
    $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
  } else {

    // Is there a profile of this profile ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profilesettings WHERE profileid = '".$ProfileId."' AND profilekey = '".$InclExcl."' AND profilevalue = '".$Pattern."';");
    if (!$rows) {
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)." }";
    } else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];
    }


    if ($Exists > 0) {
      $rows = $db->query("SELECT id AS id FROM profilesettings WHERE profileid = '".$ProfileId."' AND profilekey = '".$InclExcl."' AND profilevalue = '".$Pattern."';");
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


// Web Method Functions ===========================
/*
function getDirectoryContents() {

  $DirPath = SQLite3::escapeString($_POST["dir"]);

  $DirContentsArray = getDirectoryContentsFromShell($DirPath);
//  getDirectoryContentsBE($DirPath);

  echo json_encode($DirContentsArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}
*/

function db_create_schema() {
  $db = new MyDB();
  $rtn = "ok";

  if(!$db) {
    $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
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

      CREATE TABLE IF NOT EXISTS snapshots (
        id                            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        profileid                     INTEGER NOT NULL,
        basepath                      TEXT    NOT NULL,
        description                   TEXT    NOT NULL
      );

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
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    }
    $db->close();
  }

  return $rtn;
} // db_create_schema

function createDefaultProfile() {
  // If there is no profile, create one to default to

  $ProfileName = "Default";
  $ProfileId = getProfileId($ProfileName);

  $rtn = "ok";

  if ($ProfileId <= 0) {

    $db = new MyDB();

    if(!$db) {
      $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    } else {
      $ret = $db->exec("INSERT INTO profiles (profilename) VALUES ($ProfileName);");
      if(!$ret){
        $rtn = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
      }
      $db->close();

      $ProfileId = getProfileId($ProfileName);
    }
  }

  insertDefaultProfileValues($ProfileId);

  return $rtn;
}

function insertDefaultProfileValues($ProfileId) {

  $arr = array();

  $rtnarr["settingssaveto"] = insertProfileValue($ProfileId, "settingssaveto", "/shares/backup");
  $rtnarr["selectmode"] = insertProfileValue($ProfileId, "selectmode", "modelocal");;
  $rtnarr["settingshost"] = insertProfileValue($ProfileId, "settingshost", "mycloud");
  $rtnarr["settingsuser"] = insertProfileValue($ProfileId, "settingsuser", "me");
  $rtnarr["settingsdeletebackupolderthanperiod"] = insertProfileValue($ProfileId, "settingsdeletebackupolderthanperiod", "years");
  $rtnarr["settingsdeletefreespacelessthanunit"] = insertProfileValue($ProfileId, "settingsdeletefreespacelessthanunit", "gib");
  $rtnarr["selectschedule"] = insertProfileValue($ProfileId, "selectschedule", "xminutes");

  return $rtnarr;
}

function insertProfileValue($ProfileId, $ProfileKey, $ProfileValue) {

  $SettingsId = 0;
  $SettingsId = getProfileSettingsId($ProfileId, $ProfileKey);

  $arr = array();

  if ($SettingsId <= 0) {
    $db = new MyDB();

    // Insert a new record
    $ret = $db->exec("INSERT INTO profilesettings (profileid, profilekey, profilevalue) VALUES ('".$ProfileId."', '".$ProfileKey."','".$ProfileValue."');");
    if(!$ret){
      $arr["result"] = "ko";
      $arr["message"] = $db->lastErrorMsg();
    }
    else {
      $arr["result"] = "ok";
      $arr["message"] = "Value saved";
    }
    $db->close();
  }

  return $arr;
}

// Inserts default excludes: *.backup*, *~, .Private, .cache/*, .dropbox*, .gvfs, .thumbnails*, [Tt]rash*, lost+found/*
function insertDefaultExcludes() {

  $ProfileId = SQLite3::escapeString($_POST["profileid"]);
  $arr = array();
  $rtn = array();

  $arr = insertIncludeExcludeValue($ProfileId, "exclude", "*.backup*");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", "*~");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".Private");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".cache/*");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".dropbox*");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".gvfs");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".thumbnails*");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".[Tt]rash*");
  array_push($rtn, $arr);
  $arr = insertIncludeExcludeValue($ProfileId, "exclude", ".lost+found/*");
  array_push($rtn, $arr);

  $haserr = 0;
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

function insertIncludeExcludeValue($ProfileId, $InclExcl, $Pattern) {

  $arr = array();

  $ProfileSettingsId = getFileSpecId($ProfileId, $InclExcl, $Pattern);
  if ($ProfileSettingsId <= 0) {

    $db = new MyDB();
    if(!$db) {
      $arr["result"] = "ko";
      $arr["message"] = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    } else {
      // Insert
      $ret = $db->exec("INSERT INTO profilesettings (profileid, profilekey, profilevalue) VALUES ('".$ProfileId."', '".$InclExcl."','".$Pattern."');");
      if(!$ret){
        $arr["result"] = "ko";
        $arr["message"] = json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
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

// Select all Include or Exclude Settings for a Profile ID
function selectIncludeExclude($InclExcl) {

  $ProfileId = SQLite3::escapeString($_POST["profileid"]);

  $db = new MyDB();
  // Sort in ascending order - this is default
  $rows = $db->query("SELECT id setid, profilekey setkey, profilevalue setval FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey = '".$InclExcl."';");

  echo "{ \"result\" : \"ok\" , \"items\" : [ ";
  $rowcnt = 0;
  while($row = $rows->fetchArray(SQLITE3_ASSOC)){
    if ($rowcnt > 0) { echo " , "; }
    // echo json_encode(array_change_key_case($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    echo json_encode($row);
    $rowcnt = $rowcnt + 1;
  }
  echo " ] }";
  $db->close();
}

// Select all profiles from the Profiles list
function selectProfilesList() {
  $db = new MyDB();
  // Sort in ascending order - this is default
  $rows = $db->query("SELECT id, ProfileName profilename, case ProfileName WHEN 'Default' THEN 'selected' ELSE '' END selected FROM profiles ORDER BY ProfileName;");

  echo "{ \"result\" : \"ok\" , \"items\" : [ ";
  $rowcnt = 0;
  while($row = $rows->fetchArray(SQLITE3_ASSOC)) {
    if ($rowcnt > 0) { echo " , "; }
    echo json_encode(array_change_key_case($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    $rowcnt = $rowcnt + 1;
  }
  echo " ] }";
  $db->close();
}

// Select all Settings for a Profile ID, except the file includes
function selectProfileSettings() {

  $ProfileId = SQLite3::escapeString($_POST["profileid"]);

  $db = new MyDB();
  // Sort in ascending order - this is default
  $rows = $db->query("SELECT profilekey setkey, profilevalue setval FROM profilesettings WHERE profileid = ".$ProfileId." AND profilekey != 'include' AND profilekey != 'exclude';");

  echo "{ \"result\" : \"ok\" , \"items\" : [ ";
  $rowcnt = 0;
  while($row = $rows->fetchArray(SQLITE3_ASSOC)){
    if ($rowcnt > 0) { echo " , "; }
    echo json_encode(array_change_key_case($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    $rowcnt = $rowcnt + 1;
  }
  echo " ] }";
  $db->close();
}

// Inserts or Updates a Profile Setting key/value pair
function updateProfileSetting() {

  $ProfileExists = 0;
  $ProfileId    = SQLite3::escapeString($_POST["profileid"]);
  $ProfileKey   = SQLite3::escapeString($_POST["settingname"]);
  $ProfileValue = SQLite3::escapeString($_POST["settingvalue"]);

  echo "{ \"result\" : ";

  $SettingExists = getProfileSettingsId($ProfileId, $ProfileKey);

  $db = new MyDB();
  if(!$db) {
    echo "\"ko\" , \"message\" : ";
    echo json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  } else {
    if ($SettingExists <= 0) {
      $ret = $db->exec("INSERT INTO profilesettings (profileid, profilekey, profilevalue) VALUES ('".$ProfileId."', '".$ProfileKey."','".$ProfileValue."');");
      if(!$ret){
        echo "\"ko\" , \"message\" : ";
        echo json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
      }
      else {
        echo "\"ok\" , \"message\" : \"Value stored\"";
      }
    }
    else {
      $ret = $db->exec("UPDATE profilesettings SET profilevalue = '".$ProfileValue."' WHERE profileid = '".$ProfileId."' AND profilekey = '".$ProfileKey."';");
      if(!$ret){
        echo "\"ko\" , \"message\" : ";
        echo json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
      }
      else {
        echo "\"ok\" , \"message\" : \"Value saved\"";
      }
    }

    $db->close();

  } // Profile exists

  // Complete the JSON output
  echo " }";
}


function deleteProfileSetting() {

  $SettingId = SQLite3::escapeString($_POST["settingid"]);
  $Exists = "-1";

  echo "{ \"result\" : ";

  $db = new MyDB();
  if(!$db) {
    echo "\"ko\" , \"message\" : ";
    echo json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  } else {
    // Is there a row with this Setting ID?
    $rows = $db->query("SELECT COUNT(*) AS count FROM profilesettings WHERE id = ".$SettingId.";");
    if(!$rows){
      echo "\"ko\" , \"message\" : ";
      echo json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    }
    else {
      $row = $rows->fetchArray();
      $Exists = $row['count'];

      if ($Exists > 0) {
        $ret = $db->exec("DELETE FROM profilesettings WHERE id = ".$SettingId.";");
        if(!$ret){
          echo "\"ko\" , \"message\" : ";
          echo json_encode($db->lastErrorMsg(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        }
        else {
          echo "\"ok\" , \"message\" : \"Record deleted\"";
        }
      }
      else {
        echo "\"ko\" , \"message\" : \"No such id: ".$SettingId;
      }
    }
  }

  echo " }";
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
  $arr = insertIncludeExcludeValue ($ProfileId, "include", $FilePath);

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
  $arr = insertIncludeExcludeValue ($ProfileId, "exclude", $FilePath);

  echo "\"".$arr["result"]."\" , \"message\" : ";
  echo json_encode($arr["message"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
  echo " } ";
}





function writeErrorMsg() {
  echo "{ \"result\" : \"De nada deniro!\" }";
}

// ==============================================================================


// What shall we run?
$WhatToRun = $_POST["fn"];

switch ($WhatToRun) {
  case "init":
    $rtn = db_create_schema();
    if ($rtn == "ok") {
      $rtn = createDefaultProfile();
    }
    if ($rtn == "ok") {
      echo "{ \"result\" : \"ok\" , \"message\" : \"Init OK\" }";
    }
    else {
      echo "{ \"result\" : \"ok\" , \"message\" : \"Init failed: ".$rtn."\" }";
    }
    break;
  case "selectprofileslist":
    selectProfilesList();
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
    insertDefaultExcludes();
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
  default:
    writeErrorMsg();
}


// writeMsg(); // call the function

?>
