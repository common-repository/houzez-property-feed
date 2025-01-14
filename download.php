<?php

include('../../../wp-load.php');

global $wpdb;

if ( !isset($_GET['import_id']) || ( isset($_GET['import_id']) && empty($_GET['import_id']) ) )
{
	die("No import ID passed");
}

if ( !isset($_GET['file']) || ( isset($_GET['file']) && empty($_GET['file']) ) )
{
	die("No file passed");
}

$import_settings = get_import_settings_from_id( (int)$_GET['import_id'] );

if ( $import_settings === false )
{
	die("Import passed doesn't exist");
}

switch ( $import_settings['format'] )
{
	case "blm_local":
	case "openimmo_local":
	case "reaxml_local":
	case "rentman":
	{
		$file = $import_settings['local_directory'] . '/' . base64_decode($_GET['file']);
		$filename = base64_decode($_GET['file']);
		$parts = explode('-', $filename);
		array_pop($parts);
		$filename = implode('-', $parts);
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		readfile($file);
    	exit;
	}
	default:
	{
		die('Unknown format: ' . $import_settings['format']);
	}
}