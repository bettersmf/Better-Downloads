<?php
if (!defined('SMF')) die('No direct access.');
global $sourcedir;
require_once($sourcedir . '/Subs.php');
foreach (array(
	'integrate_actions' => 'bd_integrate_actions',
	'integrate_menu_buttons' => 'bd_integrate_menu_buttons',
	'integrate_admin_areas' => 'bd_integrate_admin_areas',
	'integrate_load_theme' => 'bd_integrate_load_theme',
	'integrate_load_permissions' => 'bd_integrate_load_permissions',
	'integrate_load_illegal_guest_permissions' => 'bd_integrate_load_illegal_guest_permissions',
) as $hook => $function)
	remove_integration_function($hook, $function, true, '$sourcedir/BetterDownloads.php');
updateSettings(array('betterstorage_integration_downloads'=>0, 'settings_updated'=>time()));
