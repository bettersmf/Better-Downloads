<?php
if (!defined('SMF'))
	die('No direct access.');

global $sourcedir, $boarddir, $smcFunc;
require_once($sourcedir . '/Subs.php');
db_extend('packages');

foreach (array(
	'integrate_actions' => 'bd_integrate_actions',
	'integrate_menu_buttons' => 'bd_integrate_menu_buttons',
	'integrate_admin_areas' => 'bd_integrate_admin_areas',
	'integrate_load_theme' => 'bd_integrate_load_theme',
	'integrate_load_permissions' => 'bd_integrate_load_permissions',
	'integrate_load_illegal_guest_permissions' => 'bd_integrate_load_illegal_guest_permissions',
) as $hook => $function)
	add_integration_function($hook, $function, true, '$sourcedir/BetterDownloads.php');

$tables = array(
	'bd_categories' => array(
		array('name'=>'id_category','type'=>'mediumint','size'=>8,'unsigned'=>true,'auto'=>true),
		array('name'=>'name','type'=>'varchar','size'=>160,'default'=>''),
		array('name'=>'description','type'=>'text'),
		array('name'=>'slug','type'=>'varchar','size'=>180,'default'=>''),
		array('name'=>'allowed_groups','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'sort_order','type'=>'smallint','size'=>5,'unsigned'=>true,'default'=>0),
		array('name'=>'enabled','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
		array('name'=>'created_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
		array('name'=>'updated_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
	),
	'bd_downloads' => array(
		array('name'=>'id_download','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
		array('name'=>'id_category','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
		array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
		array('name'=>'title','type'=>'varchar','size'=>200,'default'=>''),
		array('name'=>'slug','type'=>'varchar','size'=>220,'default'=>''),
		array('name'=>'description','type'=>'text'),
		array('name'=>'version','type'=>'varchar','size'=>80,'default'=>''),
		array('name'=>'package_type','type'=>'varchar','size'=>32,'default'=>'other'),
		array('name'=>'smf_compatibility','type'=>'varchar','size'=>120,'default'=>''),
		array('name'=>'changelog','type'=>'text'),
		array('name'=>'support_url','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'screenshot_url','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'external_url','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'filename','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'stored_name','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'mime_type','type'=>'varchar','size'=>120,'default'=>''),
		array('name'=>'file_size','type'=>'bigint','size'=>20,'unsigned'=>true,'default'=>0),
		array('name'=>'num_downloads','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
		array('name'=>'approved','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
		array('name'=>'enabled','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
		array('name'=>'created_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
		array('name'=>'updated_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
	),
	'bd_screenshots' => array(
		array('name'=>'id_screenshot','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
		array('name'=>'id_download','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
		array('name'=>'screenshot_url','type'=>'varchar','size'=>255,'default'=>''),
		array('name'=>'sort_order','type'=>'smallint','size'=>5,'unsigned'=>true,'default'=>0),
		array('name'=>'is_primary','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>0),
		array('name'=>'created_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
	),

);
$indexes = array(
	'bd_categories' => array(
		array('type'=>'primary','columns'=>array('id_category')),
		array('type'=>'unique','columns'=>array('slug')),
		array('type'=>'index','columns'=>array('enabled','sort_order')),
	),
	'bd_downloads' => array(
		array('type'=>'primary','columns'=>array('id_download')),
		array('type'=>'index','columns'=>array('id_category','approved','enabled')),
		array('type'=>'index','columns'=>array('updated_at')),
		array('type'=>'index','columns'=>array('num_downloads')),
	),
	'bd_screenshots' => array(
		array('type'=>'primary','columns'=>array('id_screenshot')),
		array('type'=>'index','columns'=>array('id_download','is_primary','sort_order')),
	),
);
foreach ($tables as $name => $columns)
	$smcFunc['db_create_table']('{db_prefix}' . $name, $columns, $indexes[$name], array(), 'ignore');

$defaults = array(
	'bd_enabled' => 1,
	'bd_show_menu' => 1,
	'bd_guest_browse_only' => 1,
	'bd_allow_member_submissions' => 0,
	'bd_submission_groups' => '',
	'bd_require_approval' => 1,
	'bd_allowed_extensions' => 'zip,rar,7z,pdf',
	'bd_max_file_mb' => 100,
	'bd_items_per_page' => 20,
	'bd_storage_path' => '',
);
$request = $smcFunc['db_query']('', 'SELECT variable FROM {db_prefix}settings WHERE variable IN ({array_string:vars})', array('vars'=>array_keys($defaults)));
$existing = array();
while ($row = $smcFunc['db_fetch_assoc']($request)) $existing[$row['variable']] = true;
$smcFunc['db_free_result']($request);
foreach ($defaults as $key => $value)
	if (!isset($existing[$key])) updateSettings(array($key=>$value));

// Seed practical permissions. Guest file access is additionally capped by the browse-only setting (enabled by default).
$grants = array(
	array(-1, 'better_downloads_view', 1),
	array(-1, 'better_downloads_download', 1),
	array(0, 'better_downloads_view', 1),
	array(0, 'better_downloads_download', 1),
);
$smcFunc['db_insert']('ignore', '{db_prefix}permissions', array('id_group'=>'int','permission'=>'string','add_deny'=>'int'), $grants, array('id_group','permission'));

$storage = rtrim($boarddir, '/\\') . '/better_downloads_storage';
if (!is_dir($storage)) @mkdir($storage, 0755, true);
if (!is_dir($storage . '/files')) @mkdir($storage . '/files', 0755, true);
if (!is_dir($storage . '/screenshots')) @mkdir($storage . '/screenshots', 0755, true);
@file_put_contents($storage . '/index.php', "<?php http_response_code(403); exit;\n");
@file_put_contents($storage . '/.htaccess', "Deny from all\n");
// Register Better Downloads as an active Better Storage integration when available.
if (is_file($sourcedir . '/BetterStorage.php'))
{
    require_once($sourcedir . '/BetterStorage.php');
    if (function_exists('betterstorage_source_config'))
        updateSettings(array('betterstorage_integration_downloads' => 1));
}

updateSettings(array('settings_updated'=>time()));

// Migrate dev1/dev2 single screenshots into the dev3 gallery table without duplicating them.
$request = $smcFunc['db_query']('', 'SELECT id_download, screenshot_url FROM {db_prefix}bd_downloads WHERE screenshot_url != {string:empty}', array('empty'=>''));
while ($row = $smcFunc['db_fetch_assoc']($request)) {
	$check = $smcFunc['db_query']('', 'SELECT id_screenshot FROM {db_prefix}bd_screenshots WHERE id_download={int:id} AND screenshot_url={string:url} LIMIT 1', array('id'=>(int)$row['id_download'],'url'=>$row['screenshot_url']));
	$exists = $smcFunc['db_fetch_assoc']($check);
	$smcFunc['db_free_result']($check);
	if (!$exists)
		$smcFunc['db_insert']('insert', '{db_prefix}bd_screenshots', array('id_download'=>'int','screenshot_url'=>'string','sort_order'=>'int','is_primary'=>'int','created_at'=>'int'), array((int)$row['id_download'],$row['screenshot_url'],0,1,time()), array('id_screenshot'));
}
$smcFunc['db_free_result']($request);

