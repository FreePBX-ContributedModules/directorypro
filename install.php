<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed');}
global $db, $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

$new = $db->query('SELECT * FROM directorypro_details');
if (DB::IsError($new)) {
	$new = true;
} else {
	$new = false;
}

$sql[] = 'CREATE TABLE IF NOT EXISTS `directorypro_entries` (
	`id` int(11) default NULL,
	`e_id` int(11) default NULL,
	`grammar` varchar(255) default NULL
	)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `directorypro_details` (
	`id` int(11) NOT NULL,
	`speech_enabled` varchar(15) default NULL,
	`pro_announcement` int(11) default NULL,
	`pro_repeat_loops` varchar(3) default NULL,
	`pro_repeat_recording` int(11) default NULL,
	`pro_invalid_recording` int(11) default NULL,
	`pro_invalid_destination` varchar(50) default NULL,
	`pro_say_extension` varchar(5) default NULL,
	`pro_retivr` varchar(10) default NULL,
	PRIMARY KEY  (`id`)
	)';

foreach ($sql as $s) {
	$do = $db->query($s);
	if (DB::IsError($do)) {
		out(_('Can not create Directory Pro table: ') . $check->getMessage());
		return false;
	}
}

//
//add retivr field if it doesnt already exists
//
$sql = 'SHOW COLUMNS FROM directorypro_details LIKE "pro_retivr"';
$res = $db->getAll($sql);
//check to see if the field already exists
if (count($res) == 0) {
	//if not add it
	$sql = 'ALTER TABLE directorypro_details ADD COLUMN pro_retivr varchar(10) AFTER pro_say_extension';
	$do = $db->query($sql);
	if(DB::IsError($do)) {
		out(_("cannot add field pro_retivr to table directory_entries \n" . $do->getDebugInfo()));
	} else {
		out(_("pro_retivr added to table directory_entries"));
	}
}
