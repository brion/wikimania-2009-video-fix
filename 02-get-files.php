<?php

$urls = json_decode( file_get_contents( 'urls.json' ), true );
foreach ($urls as $title => $url) {
	$encurl = escapeshellarg( $url );
	$cmd = "wget $encurl";
	echo $cmd;
	system( $cmd );
}
