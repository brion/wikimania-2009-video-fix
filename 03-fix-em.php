<?php

$oggs = glob("*.ogg");

mkdir('step1');
mkdir('step2');

foreach( $oggs as $ogg ) {
	$encogg = escapeshellarg( $ogg );
	$cmd = "ffmpeg -i $encogg -c copy step1/$encogg";
	echo "$cmd\n";
	system($cmd);
	
	$cmd2 = "oggz-chop -o step2/$encogg step1/$encogg";
	echo "$cmd2\n";
	system($cmd2);
}
