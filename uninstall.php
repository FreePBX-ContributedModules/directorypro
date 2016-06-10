<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed');}
global $db;
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

$dirs = $db->getAll('SELECT id FROM directorypro_details');
if ($dirs) {
	out(_('Removing grammar files'));
	foreach ($dirs as $my => $dir) {
		$file = $amp_conf['ASTETCDIR'] . '/grammars/directory-' . $dir[0] . '.gram';
		if (file_exists($file)) {
			out(_('Removing grammar file ' .$file));
			unlink($file);
		}
	}
}
outn(_('dropping directorypro_details, directorypro_entries..'));
$db->query('DROP TABLE IF EXISTS directorypro_details, directorypro_entries');
out(_('ok'));
?>
