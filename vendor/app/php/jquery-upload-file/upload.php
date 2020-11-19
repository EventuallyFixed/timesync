<?php
$ret = array();

$ProfileId = $_POST["profileid"];
$output_dir = "/mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/profiles/".$ProfileId."/";

if(isset($_FILES["myfile"]))
{
//	This is for custom errors;	
/*	$custom_error= array();
	$custom_error['jquery-upload-file-error']="File already exists";
	echo json_encode($custom_error);
	die();
	$error =$_FILES["myfile"]["error"];
*/
	// You need to handle both cases
	// If Any browser does not support serializing of multiple files using FormData() 
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
	if(!is_array($_FILES["myfile"]["name"])) //single file
	{  
    $fileName = $_FILES["myfile"]["name"];
    $mime = finfo_file($finfo, $_FILES["myfile"]["tmp_name"]);
    if ($mime == 'text/plain') {
      move_uploaded_file($_FILES["myfile"]["tmp_name"],$output_dir.$fileName);
      $ret["files"][0]["filename"] = $fileName;
      $ret["files"][0]["result"] = "ok";      
      $ret["files"][0]["message"] = "Uploaded";
    }
    else {
      $ret["files"][0]["filename"] = $fileName;
      $ret["files"][0]["result"] = "ko";
      $ret["files"][0]["message"] = "Failed - Incorrect file type: ".$mime;
    }
	}
	else  //Multiple files, file[]
	{
	  $fileCount = count($_FILES["myfile"]["name"]);
	  for($i=0; $i < $fileCount; $i++)
	  {
      $fileName = $_FILES["myfile"]["name"][$i];
      $mime = finfo_file($finfo, $_FILES["myfile"]["tmp_name"]);
      if ($mime == 'text/plain') {
        move_uploaded_file($_FILES["myfile"]["tmp_name"][$i],$output_dir.$fileName);
        $ret["files"][$i]["filename"] = $fileName;
        $ret["files"][$i]["result"] = "ok";      
        $ret["files"][$i]["message"] = "Uploaded";
      }
      else {
        $ret["files"][$i]["filename"] = $fileName;
        $ret["files"][$i]["result"] = "ko";
        $ret["files"][$i]["message"] = "Failed - Incorrect file type: ".$mime;
      }
	  }

	}
  echo json_encode($ret);
}
?>
