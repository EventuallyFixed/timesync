<?php
$ret = array();

$ProfileId = $_POST["profileid"];
$output_dir = "/mnt/HD/HD_a2/Nas_Prog/timesync/vendor/app/profiles/".$ProfileId."/";

if(isset($_POST["op"]) && $_POST["op"] == "delete" && isset($_POST['file']))
{
	$fileName = $_POST['file'];
	$fileName=str_replace("..",".",$fileName); //required. if somebody is trying parent folder files	
	$filePath = $output_dir. $fileName;
	if (file_exists($filePath)) 
	{
    unlink($filePath);
    $ret["result"] = "ok";
    $ret["message"] = "File deleted";
    $ret["filename"] = $fileName;
  }
  else {
    $ret["result"] = "ko";
    $ret["message"] = "Could not delete file";
    $ret["filename"] = $fileName;
  }
	echo json_encode($ret);
}

?>