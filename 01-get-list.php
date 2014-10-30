<?php

$apireq = "http://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&format=json&iiprop=url&iilimit=100&generator=categorymembers&gcmtitle=Category%3AWikimania%202009%20presentation%20videos&gcmprop=title&gcmtype=file&gcmlimit=100";

$result = file_get_contents( $apireq );

$data = json_decode( $result );

$members = array();
$urls = array();

foreach( get_object_vars( $data->query->pages ) as $id => $page) {
	$title = $page->title;
	$members[] = $title;
	$urls[$title] = $page->imageinfo[0]->url;
}

var_dump($members);
var_dump($urls);

file_put_contents( 'members.json', json_encode( $members ) );
file_put_contents( 'urls.json', json_encode( $urls ) );
