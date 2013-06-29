<?php
/*
Written By Rawad Hilal
Usage 
POST
	usr			Domain name. Required if !key
	pass		Domain password. Required if !key
GET
	key			Domain name. Required if !user & !pass
	a	 		The subdomain to modify. Optional
	linked		Modify all records with same ip. Optional
*/
function authMd5(&$db,$md5authent){
	$_SESSION['valid_user'] = "";
	$records = $db->getAll("SELECT domainid,domain,password FROM domains");
	foreach( $records as $record ) {
		$user = $record['domain'];
		$pass = $record['password'];
		$cred = md5($user.$pass);
		if(strcmp($cred,$md5authent)==0){
			$_SESSION['valid_user'] = $user;
			$_SESSION['domainid'] = $record['domainid'];
			return true;
			break;
		}
	}
	return false;
}

/*
if ($_SERVER['SERVER_PORT'] != '443'){
	echo "403 Unauthorized Access. HTTPS Required";
	http_response_code(403);
	exit;
}
*/

include("includes/globals.php");

$user = $_POST['usr'];
$pass = $_POST['pass'];

$key = $_GET['key'];
$subdomain = $db->quote($_GET['a']);
$linked = $db->quote($_GET['linked']);

$remoteip = $_SERVER['REMOTE_ADDR'];
$remoteip_array = array("address"=>$remoteip);

//Authenticate user
if(!empty($key)) {
    authMd5($db,$key);
}
elseif(!empty($user) && !empty($pass)) {
    $session->auth($user,$pass);
}
$domain = $_SESSION['valid_user'];
if(empty($domain)){
	echo "403 Unauthorized Access. Incorrect domain name and password";
	http_response_code(403);
	exit;
}

//Validate $subdomain
if(empty($_GET['a'])){
	$subdomain = "''";
}

//Get domain id of domain
$domainid = $dns->domainId($domain);

if(!empty($linked)){  //Change all similar ips of domain to new ip
	//Get old ip of domain
	$recordip = $db->getOne("SELECT address FROM records_a WHERE domainid = $domainid AND name = $subdomain");
	//Get list of record ids of old ip
	$records = $db->getAll("SELECT recordid FROM records_a WHERE domainid = $domainid AND address = '$recordip'");
	$error = 0;
	foreach( $records as $record ) {
    	//Modify record
    	if($dns->modRecord($domain, $record['recordid'], $remoteip_array, 'a')){
		}
		else{
			$error = 1;
			break;
		}
	}
	if($error == 0){
		echo "200 IP updated successfully to $remoteip."; 
	}
	else{
		echo "406 Unknown error occured.";
		http_response_code(406);
		exit;	
	}
}
else{ //Change only sub domain to new ip
	//Get record id of subdomain
	$recordid = $db->getOne("SELECT recordid FROM records_a WHERE domainid = $domainid AND name = $subdomain");
	if($dns->modRecord($domain, $recordid, $remoteip_array, 'a')){
		echo "200 IP updated successfully to $remoteip."; 
	}
	else{
		echo "406 Unknown error occured. $dns->error";
		http_response_code(406);
		exit;	
	}
}

//Process modification queue
//$dns->processModqueue();

http_response_code(200);
?>
