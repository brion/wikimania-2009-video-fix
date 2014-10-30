<?php

date_default_timezone_set('UTC');


$alloggs = glob("*.ogg");
$oggs = array();
foreach ($alloggs as $ogg) {
	if (filesize( $ogg ) >=	 100 * 1024 * 1024) {
		$oggs[] = $ogg;
	}
}
// test with smallest one:
// https://commons.wikimedia.org/wiki/File:200908261425-travis_kriplean-tools_for.ogg
//$oggs = array('200908261425-travis_kriplean-tools_for.ogg');

define('CHUNK_SIZE', 10 * 1000 * 1000);
//define('CHUNK_SIZE', 1 * 1000 * 1000);



// define() WIKI_USERNAME and WIKI_PASSWORD
require "creds.php";

define('API_BASE', "https://commons.wikimedia.org/w/api.php");
//define('API_BASE', "https://test.wikipedia.org/w/api.php");

// pear install HTTP_Request2
require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/CookieJar.php';

$cookieJar = new HTTP_Request2_CookieJar();

function doLogin() {
	$token = '';
	while (true) {
		$request = new HTTP_Request2(API_BASE, HTTP_Request2::METHOD_POST);
		$request->setConfig( 'ssl_verify_host', false ); // hack for funky cert
		global $cookieJar;
		$request->setCookieJar( $cookieJar );
		$request->addPostParameter(array(
			'format' => 'json',
			'action' => 'login',
			'lgname' => WIKI_USERNAME,
			'lgpassword' => WIKI_PASSWORD,
		));
		if ($token) {
			$request->addPostParameter('lgtoken', $token);
		}
		$response = $request->send();
	
		$status = $response->getStatus();
		$body = $response->getBody();
		$data = json_decode( $body );
	
		if ($data->login->result == 'NeedToken') {
			$token = $data->login->token;
			echo "login token $token\n";
			continue;
		} else if ($data->login->result == 'Success') {
			echo "login success\n";
			return;
		} else {
			echo "$status login fail?? $body\n";
			throw new Exception("explody");
		}
	}

	return $token;
}

function getUploadToken($filename) {
	$request = new HTTP_Request2(API_BASE, HTTP_Request2::METHOD_POST);
	$request->setConfig( 'ssl_verify_host', false ); // hack for funky cert
	global $cookieJar;
	$request->setCookieJar( $cookieJar );
	$request->addPostParameter(array(
		'format' => 'json',
		'action' => 'query',
		'prop' => 'info',
		'intoken' => 'edit',
		'titles' => 'File:' . $filename
	));
	$response = $request->send();
	
	$status = $response->getStatus();
	$body = $response->getBody();
	$data = json_decode( $body );
	$pages = get_object_vars( $data->query->pages );
	$pageIds = array_keys( $pages );
	$pageid = $pageIds[0];
	$page = $pages[$pageid];
	$token = $page->edittoken;
	
	echo "Token $token for $filename\n";

	return $token;
}

function performUploadChunk($filename, $offset, $data, $filekey) {
	$sourceFilename = "step2/$filename";
	
	$token = getUploadToken($filename);
	
	$request = new HTTP_Request2(API_BASE, HTTP_Request2::METHOD_POST);
	$request->setConfig( 'ssl_verify_host', false ); // hack for funky cert
	global $cookieJar;
	$request->setCookieJar( $cookieJar );
	$request->addPostParameter(array(
		'format' => 'json',
		'action' => 'upload',
		'filename' => $filename,
		'comment' => 'Rewrite packet headers and timecode to fix transcodes and playback',
		'ignorewarnings' => 1,
		'token' => $token,
	));
	if ($data === null) {
		// final upload
		$request->addPostParameter( 'filekey', $filekey );
	} else {
		// chunks
		$request->addPostParameter( array(
			'stash' => 1,
			'offset' => $offset,
			'filesize' => filesize( $sourceFilename ),
		) );
		if ($offset > 0) {
			$request->addPostParameter( 'filekey', $filekey );
		}
		file_put_contents( 'chunk.chunk', $data );
		$request->addUpload('chunk', "chunk.chunk", $filename, 'video/ogg');
	}
	
	try {
		$response = $request->send();
	} catch (Exception $e) {
		$fail = array( 'exception' => $e->getMessage() );
		var_dump( $fail );
		file_put_contents( $statusFile, json_encode( $fail ) );
		return;
	}
	
	$status = $response->getStatus();
	echo "{$status} for $filename\n";
	
	$body = $response->getBody();
	//file_put_contents($statusFile, $body );
	
	$data = json_decode($body);
	echo "upload: {$data->upload->result}\n";
	echo "filekey: {$data->upload->filekey}\n";
	var_dump( $data );
	return $data->upload->filekey;
}

function performUpload( $filename ) {
	$size = filesize( $filename );
	$filekey = null;
	for ($offset = 0; $offset < $size; $offset += CHUNK_SIZE) {
		echo "chunk at $offset\n";
		$data = file_get_contents( "step2/$filename", false, null, $offset, CHUNK_SIZE);
		$filekey = performUploadChunk($filename, $offset, $data, $filekey);
	}
	echo "final for $filekey\n";
	performUploadChunk($filename, -1, null, $filekey);
}

mkdir('upload');
doLogin();
foreach( $oggs as $ogg ) {
	performUpload( $ogg );
}
