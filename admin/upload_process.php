<?php
ob_start();
include('internalaccess/session.php');
include('internalaccess/connectdb.php');
$folder = $_REQUEST['img_folder'].'/';
$baseUploadfileName = basename($_FILES['images']['name']);
$uploadfileNameArr = explode(".", $baseUploadfileName);
$filename = md5("attendance" . rand(999,9999)) . "-" . ".".$uploadfileNameArr[count($uploadfileNameArr)-1];
move_uploaded_file( $_FILES["images"]["tmp_name"], "temp_img/" . $filename);


function clean($var){
	$specials = array(' ','!','@','#','$','%','^','&','(',')','_','+','`','~',',',';',"'",']','[','}','{');
	$cleaned = strtolower($var);
	$cleaned = str_replace($specials,'-',$cleaned);
	$cleaned = str_replace('--------------------','-',$cleaned);
	$cleaned = str_replace('-------------------','-',$cleaned);
	$cleaned = str_replace('------------------','-',$cleaned);
	$cleaned = str_replace('-----------------','-',$cleaned);
	$cleaned = str_replace('----------------','-',$cleaned);
	$cleaned = str_replace('---------------','-',$cleaned);
	$cleaned = str_replace('--------------','-',$cleaned);
	$cleaned = str_replace('-------------','-',$cleaned);
	$cleaned = str_replace('------------','-',$cleaned);
	$cleaned = str_replace('-----------','-',$cleaned);
	$cleaned = str_replace('----------','-',$cleaned);
	$cleaned = str_replace('---------','-',$cleaned);
	$cleaned = str_replace('--------','-',$cleaned);
	$cleaned = str_replace('-------','-',$cleaned);
	$cleaned = str_replace('------','-',$cleaned);
	$cleaned = str_replace('-----','-',$cleaned);
	$cleaned = str_replace('----','-',$cleaned);
	$cleaned = str_replace('---','-',$cleaned);
	$cleaned = str_replace('--','-',$cleaned);
	$cleaned = str_replace('-','-',$cleaned);
	return $cleaned;
	}

echo str_replace(' ','',$filename);
?>



