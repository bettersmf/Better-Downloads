<?php
if (!defined('SMF')) die('No direct access.');
global $boarddir, $modSettings, $smcFunc;
$root = !empty($modSettings['bd_storage_path']) ? rtrim($modSettings['bd_storage_path'], '/\\') : rtrim($boarddir, '/\\') . '/better_downloads_storage';
$default = rtrim($boarddir, '/\\') . '/better_downloads_storage';
$remove_tree = function($path) use (&$remove_tree) {
	if (!is_dir($path) || is_link($path)) return;
	$items = @scandir($path); if (!is_array($items)) return;
	foreach ($items as $item) { if ($item === '.' || $item === '..') continue; $p=$path.DIRECTORY_SEPARATOR.$item; if (is_dir($p) && !is_link($p)) $remove_tree($p); else @unlink($p); }
	@rmdir($path);
};
$remove_tree($root . '/files');
$remove_tree($root . '/screenshots');
foreach (array('.htaccess','index.php') as $f) if (is_file($root.'/'.$f)) @unlink($root.'/'.$f);
if (rtrim($root,'/\\') === rtrim($default,'/\\')) @rmdir($root);
foreach (array('bd_screenshots','bd_downloads','bd_categories') as $table) $smcFunc['db_query']('', 'DROP TABLE IF EXISTS {db_prefix}' . $table, array());
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable LIKE {string:p}', array('p'=>'bd_%'));
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}permissions WHERE permission IN ({array_string:p})', array('p'=>array('better_downloads_view','better_downloads_download','better_downloads_submit','better_downloads_edit_own','better_downloads_moderate')));
updateSettings(array('settings_updated'=>time()));
