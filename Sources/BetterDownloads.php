<?php
if (!defined('SMF'))
	die('No direct access.');

function bd_load_language()
{
	global $txt;
	loadLanguage('BetterDownloads');

	// Backfill newer strings when a custom/generated theme still carries an
	// older Better Downloads language file. The default-theme language file
	// remains authoritative, but stale theme-local copies must not hide new UI.
	$fallback = array(
		'bd_guest_browse_only' => 'Allow guests to browse, but require login to download',
		'bd_guest_browse_only_help' => 'Guests can browse all enabled categories plus approved download pages, descriptions and screenshots, but the file itself remains protected. Direct download URLs send guests to the SMF login page.',
		'bd_login_to_download' => 'Login to Download',
	);
	foreach ($fallback as $key => $value)
		if (!isset($txt[$key]))
			$txt[$key] = $value;
}

function bd_integrate_actions(&$actions)
{
	$actions['downloads'] = array('BetterDownloads.php', 'bd_dispatch');
}

function bd_integrate_menu_buttons(&$buttons)
{
	global $scripturl, $txt, $modSettings;
	if (empty($modSettings['bd_enabled']) || empty($modSettings['bd_show_menu']) || !bd_can_view())
		return;
	bd_load_language();
	$buttons['downloads'] = array(
		'title' => $txt['better_downloads'],
		'href' => $scripturl . '?action=downloads',
		'show' => true,
		'icon' => 'better_downloads_menu.png',
		'sub_buttons' => array(),
	);
}

function bd_integrate_admin_areas(&$admin_areas)
{
	global $txt;
	bd_load_language();
	$admin_areas['config']['areas']['betterdownloads'] = array(
		'label' => $txt['bd_admin_title'],
		'file' => 'BetterDownloads.php',
		'function' => 'bd_admin_dispatch',
		'permission' => array('admin_forum'),
		'icon' => 'better_downloads.png',
		'subsections' => array(
			'downloads' => array($txt['bd_downloads']),
			'categories' => array($txt['bd_categories']),
			'settings' => array($txt['bd_settings']),
		),
	);
}

function bd_integrate_load_theme()
{
	global $context, $settings;
	if (empty($settings['default_theme_url'])) return;
	if (!isset($context['html_headers'])) $context['html_headers'] = '';
	if (strpos($context['html_headers'], 'BetterDownloads.css') === false)
		$context['html_headers'] .= "\n<link rel=\"stylesheet\" href=\"" . htmlspecialchars($settings['default_theme_url'] . '/css/BetterDownloads.css?bd=0.1.0', ENT_QUOTES, 'UTF-8') . '\">';
}

function bd_integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	bd_load_language();
	if (!in_array('better_downloads', $permissionGroups['membergroup']))
		$permissionGroups['membergroup'][] = 'better_downloads';
	foreach (array('better_downloads_view','better_downloads_download','better_downloads_submit','better_downloads_edit_own','better_downloads_moderate') as $permission)
		$permissionList['membergroup'][$permission] = array(false, 'better_downloads');
}

function bd_integrate_load_illegal_guest_permissions()
{
	global $context;
	foreach (array('better_downloads_submit','better_downloads_edit_own','better_downloads_moderate') as $permission)
		if (!in_array($permission, $context['non_guest_permissions'])) $context['non_guest_permissions'][] = $permission;
}

function bd_allowed($permission)
{
	global $user_info;
	return !empty($user_info['is_admin']) || allowedTo($permission);
}

function bd_require($permission)
{
	global $user_info;
	if (!empty($user_info['is_admin'])) return;
	isAllowedTo($permission);
}


function bd_guest_browse_only()
{
	global $modSettings, $user_info;
	return empty($user_info['id']) && !empty($modSettings['bd_guest_browse_only']);
}

function bd_can_view()
{
	global $user_info;
	if (!empty($user_info['is_admin'])) return true;
	if (bd_guest_browse_only()) return true;
	return bd_allowed('better_downloads_view');
}

function bd_can_download($id_download = 0)
{
	global $user_info;
	if (!empty($user_info['is_admin'])) return $id_download ? (bool) bd_get_download((int) $id_download, false) : true;
	if (bd_guest_browse_only()) return false;
	if (!bd_allowed('better_downloads_download')) return false;
	return $id_download ? (bool) bd_get_download((int) $id_download, false) : true;
}

function bd_slug($text)
{
	$text = strtolower(trim((string)$text));
	$text = preg_replace('~[^a-z0-9]+~', '-', $text);
	return trim($text, '-') ?: 'item';
}

function bd_storage_root()
{
	global $boarddir, $modSettings;
	return !empty($modSettings['bd_storage_path']) ? rtrim((string)$modSettings['bd_storage_path'], '/\\') : rtrim((string)$boarddir, '/\\') . '/better_downloads_storage';
}


function bd_betterstorage_available()
{
    global $sourcedir, $modSettings;

    if (!function_exists('betterstorage_source_config') && is_file($sourcedir . '/BetterStorage.php'))
        require_once($sourcedir . '/BetterStorage.php');

    $available = function_exists('betterstorage_source_config') && function_exists('betterstorage_s3_put_file');

    // Tell Better Storage that the Downloads adapter is genuinely active.
    // Only write when the state needs changing to avoid a DB write on every request.
    if ($available && empty($modSettings['betterstorage_integration_downloads']) && function_exists('updateSettings'))
    {
        updateSettings(array('betterstorage_integration_downloads' => 1));
        $modSettings['betterstorage_integration_downloads'] = 1;
    }

    return $available;
}

function bd_storage_config()
{
    if (bd_betterstorage_available())
        return betterstorage_source_config('downloads');
    return array('backend'=>'local');
}

function bd_stored_is_s3($stored)
{
    return strpos((string) $stored, 's3:') === 0;
}

function bd_stored_s3_key($stored)
{
    return ltrim(substr((string) $stored, 3), '/');
}

function bd_delete_stored_file($stored)
{
    if ($stored === '') return;
    if (bd_stored_is_s3($stored))
    {
        if (bd_betterstorage_available())
        {
            $key = bd_stored_s3_key($stored);
            $cfg = betterstorage_source_config('downloads');
            betterstorage_s3_delete($cfg, $key);
            if (function_exists('betterstorage_unregister_object'))
                betterstorage_unregister_object('downloads', 's3', $key);
        }
        return;
    }
    $name = basename((string) $stored);
    if ($name !== '') @unlink(bd_storage_root() . '/files/' . $name);
    if (bd_betterstorage_available() && function_exists('betterstorage_unregister_object'))
        betterstorage_unregister_object('downloads', 'local', 'files/' . $name);
}


function bd_format_bytes($bytes)
{
	$bytes = max(0, (int) $bytes);
	if ($bytes === 0) return '';
	$units = array('B','KB','MB','GB');
	$i = 0;
	$value = (float) $bytes;
	while ($value >= 1024 && $i < count($units) - 1) { $value /= 1024; $i++; }
	return ($i === 0 ? (string) (int) $value : number_format($value, $value >= 10 ? 1 : 2)) . ' ' . $units[$i];
}

function bd_package_type_label($type)
{
	$labels = array('mod'=>'SMF MOD','theme'=>'SMF Theme','document'=>'Document','other'=>'Other');
	return isset($labels[$type]) ? $labels[$type] : 'Other';
}

function bd_screenshot_is_local($value)
{
	return strpos((string) $value, 'local:') === 0;
}

function bd_screenshot_src($item)
{
	global $scripturl;
	if (empty($item['screenshot_url'])) return '';
	if (bd_screenshot_is_local($item['screenshot_url']))
		return $scripturl . '?action=downloads;sa=screenshot;id=' . (int) $item['id_download'];
	return (string) $item['screenshot_url'];
}

function bd_delete_local_screenshot($value)
{
	if (!bd_screenshot_is_local($value)) return;
	$name = basename(substr((string) $value, 6));
	if ($name !== '') @unlink(bd_storage_root() . '/screenshots/' . $name);
}

function bd_store_screenshot($file)
{
	if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
	if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) fatal_error('Could not upload the screenshot.', false);
	if ((int) $file['size'] > 8 * 1024 * 1024) fatal_error('Screenshot files must be 8 MB or smaller.', false);
	$info = @getimagesize($file['tmp_name']);
	if (!$info || empty($info['mime'])) fatal_error('The screenshot must be a valid image.', false);
	$allowed = array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
	if (!isset($allowed[$info['mime']])) fatal_error('Screenshot must be JPG, PNG, GIF or WebP.', false);
	$root = bd_storage_root() . '/screenshots';
	if (!is_dir($root)) @mkdir($root, 0755, true);
	$name = bin2hex(random_bytes(16)) . '.' . $allowed[$info['mime']];
	if (!@move_uploaded_file($file['tmp_name'], $root . '/' . $name)) fatal_error('Could not store the screenshot.', false);
	return 'local:' . $name;
}

function bd_screenshot_src_from_value($id_download, $value, $id_screenshot = 0)
{
	global $scripturl;
	if ((string) $value === '') return '';
	if (bd_screenshot_is_local($value)) {
		if ((int) $id_screenshot > 0)
			return $scripturl . '?action=downloads;sa=screenshot;shot=' . (int) $id_screenshot;
		return $scripturl . '?action=downloads;sa=screenshot;id=' . (int) $id_download;
	}
	return (string) $value;
}

function bd_get_screenshots($id_download, $admin = false)
{
	global $smcFunc;
	$id_download = (int) $id_download;
	if ($id_download <= 0) return array();
	if (!$admin && !bd_get_download($id_download, false)) return array();
	$request = $smcFunc['db_query']('', 'SELECT id_screenshot, id_download, screenshot_url, sort_order, is_primary, created_at FROM {db_prefix}bd_screenshots WHERE id_download={int:id} ORDER BY is_primary DESC, sort_order, id_screenshot', array('id'=>$id_download));
	$rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($request)) {
		$row['id_screenshot'] = (int) $row['id_screenshot'];
		$row['id_download'] = (int) $row['id_download'];
		$row['sort_order'] = (int) $row['sort_order'];
		$row['is_primary'] = !empty($row['is_primary']) ? 1 : 0;
		$row['src'] = bd_screenshot_src_from_value($id_download, $row['screenshot_url'], $row['id_screenshot']);
		$rows[] = $row;
	}
	$smcFunc['db_free_result']($request);
	return $rows;
}

function bd_get_primary_screenshot($item, $admin = false)
{
	if (empty($item['id_download'])) return false;
	$shots = bd_get_screenshots((int) $item['id_download'], $admin);
	if (!empty($shots)) return $shots[0];
	if (!empty($item['screenshot_url'])) return array(
		'id_screenshot'=>0,
		'id_download'=>(int)$item['id_download'],
		'screenshot_url'=>$item['screenshot_url'],
		'sort_order'=>0,
		'is_primary'=>1,
		'src'=>bd_screenshot_src($item),
	);
	return false;
}

function bd_delete_screenshot_record($shot)
{
	global $smcFunc;
	if (!$shot) return;
	bd_delete_local_screenshot($shot['screenshot_url']);
	if (!empty($shot['id_screenshot']))
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bd_screenshots WHERE id_screenshot={int:id}', array('id'=>(int)$shot['id_screenshot']));
}

function bd_sync_primary_screenshot($id_download, $preferred = 0)
{
	global $smcFunc;
	$id_download = (int) $id_download;
	$shots = bd_get_screenshots($id_download, true);
	if (!$shots) {
		$smcFunc['db_query']('', 'UPDATE {db_prefix}bd_downloads SET screenshot_url={string:empty} WHERE id_download={int:id}', array('empty'=>'','id'=>$id_download));
		return 0;
	}
	$valid = array();
	foreach ($shots as $shot) $valid[$shot['id_screenshot']] = $shot;
	$primary = isset($valid[(int)$preferred]) ? (int)$preferred : 0;
	if (!$primary) {
		foreach ($shots as $shot) if (!empty($shot['is_primary'])) { $primary = (int)$shot['id_screenshot']; break; }
	}
	if (!$primary) $primary = (int)$shots[0]['id_screenshot'];
	$smcFunc['db_query']('', 'UPDATE {db_prefix}bd_screenshots SET is_primary=CASE WHEN id_screenshot={int:primary} THEN 1 ELSE 0 END WHERE id_download={int:id}', array('primary'=>$primary,'id'=>$id_download));
	$value = isset($valid[$primary]) ? $valid[$primary]['screenshot_url'] : $shots[0]['screenshot_url'];
	$smcFunc['db_query']('', 'UPDATE {db_prefix}bd_downloads SET screenshot_url={string:value} WHERE id_download={int:id}', array('value'=>$value,'id'=>$id_download));
	return $primary;
}

function bd_add_screenshot($id_download, $value, $sort_order = 0, $primary = 0)
{
	global $smcFunc;
	$value = trim((string)$value);
	if ($value === '') return 0;
	if (!bd_screenshot_is_local($value) && !filter_var($value, FILTER_VALIDATE_URL)) fatal_error('Screenshot URL is not valid.', false);
	$smcFunc['db_insert']('insert', '{db_prefix}bd_screenshots', array('id_download'=>'int','screenshot_url'=>'string','sort_order'=>'int','is_primary'=>'int','created_at'=>'int'), array((int)$id_download,$value,(int)$sort_order,$primary?1:0,time()), array('id_screenshot'));
	return (int)$smcFunc['db_insert_id']('{db_prefix}bd_screenshots','id_screenshot');
}

function bd_normalize_uploaded_files($files)
{
	$out = array();
	if (empty($files) || empty($files['name'])) return $out;
	if (!is_array($files['name'])) return array($files);
	foreach ($files['name'] as $i=>$name) {
		if ((string)$name === '') continue;
		$out[] = array(
			'name'=>$name,
			'type'=>isset($files['type'][$i])?$files['type'][$i]:'',
			'tmp_name'=>isset($files['tmp_name'][$i])?$files['tmp_name'][$i]:'',
			'error'=>isset($files['error'][$i])?$files['error'][$i]:UPLOAD_ERR_NO_FILE,
			'size'=>isset($files['size'][$i])?(int)$files['size'][$i]:0,
		);
	}
	return $out;
}

function bd_user_groups()
{
	global $user_info;
	$groups = array();
	foreach (array('id_group','id_post_group') as $key)
		if (isset($user_info[$key])) $groups[] = (int)$user_info[$key];
	if (!empty($user_info['additional_groups']))
		$groups = array_merge($groups, array_map('intval', (array)$user_info['additional_groups']));
	if (!empty($user_info['groups']))
		$groups = array_merge($groups, array_map('intval', (array)$user_info['groups']));
	return array_values(array_unique($groups));
}

function bd_submission_group_ids()
{
	global $modSettings;
	$raw = isset($modSettings['bd_submission_groups']) ? trim((string) $modSettings['bd_submission_groups']) : '';
	if ($raw === '') return array();
	return array_values(array_unique(array_map('intval', array_filter(explode(',', $raw), 'strlen'))));
}

function better_downloads_member_can_submit()
{
	global $modSettings, $user_info;

	// Admins continue to manage downloads through the admin area regardless of
	// whether community submissions are enabled. This gate is for member-facing
	// submission workflows only.
	if (empty($modSettings['bd_allow_member_submissions']) || empty($user_info['id']))
		return false;
	if (!bd_allowed('better_downloads_submit'))
		return false;

	$allowed = bd_submission_group_ids();
	if (!$allowed)
		return false;

	// Group 0 is the SMF Regular Members audience. Treat it as all registered
	// members, including members whose primary group is a custom group.
	if (in_array(0, $allowed, true))
		return true;

	return (bool) array_intersect($allowed, bd_user_groups());
}

function bd_category_visible($category)
{
	global $user_info;
	if (empty($category['enabled']) || !bd_can_view()) return false;
	if (!empty($user_info['is_admin'])) return true;

	// Guest browse-only mode is intentionally a public catalog view.
	// When enabled, guests may see every enabled category and every
	// approved/enabled download within it, while the actual file remains
	// protected by bd_can_download()/bd_public_download().
	if (bd_guest_browse_only()) return true;

	$raw = trim((string)$category['allowed_groups']);
	if ($raw === '') return true;
	$allowed = array_filter(array_map('intval', explode(',', $raw)), function($v){ return $v !== 0; });
	return (bool)array_intersect($allowed, bd_user_groups());
}

function bd_get_categories($include_disabled = false)
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}bd_categories' . ($include_disabled ? '' : ' WHERE enabled = {int:on}') . ' ORDER BY sort_order, name', array('on'=>1));
	$rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		if ($include_disabled || bd_category_visible($row)) $rows[(int)$row['id_category']] = $row;
	$smcFunc['db_free_result']($request);
	return $rows;
}

function bd_get_category_counts()
{
	global $smcFunc;
	$visible = bd_get_categories(false);
	if (!$visible) return array();
	$request = $smcFunc['db_query']('', 'SELECT id_category, COUNT(*) AS total FROM {db_prefix}bd_downloads WHERE approved={int:on} AND enabled={int:on} AND id_category IN ({array_int:cats}) GROUP BY id_category', array('on'=>1,'cats'=>array_keys($visible)));
	$counts = array_fill_keys(array_keys($visible), 0);
	while ($row = $smcFunc['db_fetch_assoc']($request)) $counts[(int)$row['id_category']] = (int)$row['total'];
	$smcFunc['db_free_result']($request);
	return $counts;
}

function bd_get_download($id, $admin = false)
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', 'SELECT d.*, c.name AS category_name, c.allowed_groups, c.enabled AS category_enabled FROM {db_prefix}bd_downloads AS d LEFT JOIN {db_prefix}bd_categories AS c ON (c.id_category=d.id_category) WHERE d.id_download={int:id} LIMIT 1', array('id'=>(int)$id));
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);
	if (!$row) return false;
	if ($admin) return $row;
	if (empty($row['enabled']) || empty($row['approved'])) return false;
	$category = array('enabled'=>$row['category_enabled'],'allowed_groups'=>$row['allowed_groups']);
	if (!bd_category_visible($category)) return false;
	return $row;
}

function bd_get_downloads($category = 0, $limit = 0, $sort = 'updated')
{
	global $smcFunc;
	$categories = bd_get_categories(false);
	if ($category && !isset($categories[$category])) return array();
	$ids = array_keys($categories);
	if (!$ids) return array();
	$order = $sort === 'popular' ? 'd.num_downloads DESC, d.updated_at DESC' : ($sort === 'latest' ? 'd.created_at DESC' : 'd.updated_at DESC');
	$sql = 'SELECT d.*, c.name AS category_name FROM {db_prefix}bd_downloads AS d INNER JOIN {db_prefix}bd_categories AS c ON (c.id_category=d.id_category) WHERE d.approved={int:on} AND d.enabled={int:on} AND d.id_category IN ({array_int:cats})';
	$params = array('on'=>1,'cats'=>$category ? array((int)$category) : $ids);
	$sql .= ' ORDER BY ' . $order;
	if ($limit > 0) { $sql .= ' LIMIT {int:lim}'; $params['lim']=(int)$limit; }
	$request = $smcFunc['db_query']('', $sql, $params);
	$rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($request)) $rows[]=$row;
	$smcFunc['db_free_result']($request);
	return $rows;
}

// Public read API for Better Portal and other Better SMF integrations.
function better_downloads_get_categories()
{
	return bd_get_categories(false);
}
function better_downloads_get_latest($limit = 5, $category = 0)
{
	return bd_get_downloads((int)$category, max(1,(int)$limit), 'latest');
}
function better_downloads_get_recently_updated($limit = 5, $category = 0)
{
	return bd_get_downloads((int)$category, max(1,(int)$limit), 'updated');
}
function better_downloads_get_popular($limit = 5, $category = 0)
{
	return bd_get_downloads((int)$category, max(1,(int)$limit), 'popular');
}
function better_downloads_can_download($id_download)
{
	return bd_can_download((int)$id_download);
}

// dev7: richer read-only integration API for Better Portal and other Better SMF consumers.
// Better Downloads remains the authority for visibility and screenshot access.
function better_downloads_get_download($id_download)
{
	return bd_get_download((int)$id_download, false);
}
function better_downloads_get_screenshots($id_download)
{
	$item = bd_get_download((int)$id_download, false);
	if (!$item) return array();
	return bd_get_screenshots((int)$id_download, false);
}
function better_downloads_get_category_counts()
{
	return bd_get_category_counts();
}

// dev8: member-facing submission API for Portal and other Better SMF consumers.
// Better Downloads remains authoritative for permissions, validation, storage,
// approval policy and database writes.
function better_downloads_can_submit()
{
	global $user_info;
	return !empty($user_info['is_admin']) || better_downloads_member_can_submit();
}

function better_downloads_get_submission_options()
{
	global $modSettings, $user_info;
	if (!better_downloads_can_submit()) return false;
	$categories = array();
	foreach (bd_get_categories(false) as $id => $cat)
		$categories[(int)$id] = (string)$cat['name'];
	return array(
		'categories' => $categories,
		'package_types' => array('mod'=>'SMF MOD','theme'=>'SMF Theme','document'=>'Document','other'=>'Other'),
		'allowed_extensions' => isset($modSettings['bd_allowed_extensions']) ? (string)$modSettings['bd_allowed_extensions'] : '',
		'max_file_mb' => max(1, (int)($modSettings['bd_max_file_mb'] ?? 10)),
		'require_approval' => empty($user_info['is_admin']) && !empty($modSettings['bd_require_approval']),
	);
}

function better_downloads_submit_download($data, $files = array())
{
	global $smcFunc, $modSettings, $user_info;
	if (!better_downloads_can_submit()) fatal_lang_error('no_access', false);
	checkSession('post');

	$data = is_array($data) ? $data : array();
	$files = is_array($files) ? $files : array();
	$title = trim((string)($data['title'] ?? ''));
	if ($title === '') fatal_error('Download title is required.', false);
	$id_category = (int)($data['id_category'] ?? 0);
	$categories = bd_get_categories(false);
	if (!isset($categories[$id_category])) fatal_error('Choose a valid category.', false);

	$type = (string)($data['package_type'] ?? 'other');
	if (!in_array($type, array('mod','theme','document','other'), true)) $type = 'other';
	$external = trim((string)($data['external_url'] ?? ''));
	if ($external !== '' && !filter_var($external, FILTER_VALIDATE_URL)) fatal_error('External URL is not valid.', false);
	$support = trim((string)($data['support_url'] ?? ''));
	if ($support !== '' && !filter_var($support, FILTER_VALIDATE_URL)) fatal_error('Support URL is not valid.', false);

	$file = array('filename'=>'','stored_name'=>'','mime_type'=>'','file_size'=>0);
	if (!empty($files['download_file']['tmp_name'])) $file = bd_store_upload($files['download_file']);
	if (empty($file['stored_name']) && $external === '') fatal_error('Upload a file or provide an external download URL.', false);

	$approved = !empty($user_info['is_admin']) || empty($modSettings['bd_require_approval']) ? 1 : 0;
	$now = time();
	$smcFunc['db_insert']('insert','{db_prefix}bd_downloads',array(
		'id_category'=>'int','id_member'=>'int','title'=>'string','slug'=>'string','description'=>'string','version'=>'string','package_type'=>'string','smf_compatibility'=>'string','changelog'=>'string','support_url'=>'string','screenshot_url'=>'string','external_url'=>'string','filename'=>'string','stored_name'=>'string','mime_type'=>'string','file_size'=>'int','num_downloads'=>'int','approved'=>'int','enabled'=>'int','created_at'=>'int','updated_at'=>'int'
	),array(
		$id_category,(int)$user_info['id'],$title,bd_slug($title),(string)($data['description'] ?? ''),(string)($data['version'] ?? ''),$type,(string)($data['smf_compatibility'] ?? ''),(string)($data['changelog'] ?? ''),$support,'',$external,$file['filename'],$file['stored_name'],$file['mime_type'],$file['file_size'],0,$approved,1,$now,$now
	),array('id_download'));
	$id = (int)$smcFunc['db_insert_id']('{db_prefix}bd_downloads','id_download');

	$next_sort = 10;
	if (!empty($files['screenshot_files'])) {
		foreach (bd_normalize_uploaded_files($files['screenshot_files']) as $upload) {
			if ((int)$upload['error'] === UPLOAD_ERR_NO_FILE) continue;
			$value = bd_store_screenshot($upload);
			if ($value) { bd_add_screenshot($id,$value,$next_sort,0); $next_sort += 10; }
		}
	}
	$urls = preg_split('~[\r\n]+~', (string)($data['screenshot_urls'] ?? ''));
	foreach ($urls as $url) {
		$url = trim($url); if ($url === '') continue;
		if (!filter_var($url, FILTER_VALIDATE_URL)) fatal_error('One of the screenshot URLs is not valid.', false);
		bd_add_screenshot($id,$url,$next_sort,0); $next_sort += 10;
	}
	bd_sync_primary_screenshot($id,0);

	return array('id_download'=>$id,'approved'=>$approved,'pending'=>!$approved);
}

function bd_dispatch()
{
	global $context, $txt, $modSettings;
	if (empty($modSettings['bd_enabled'])) fatal_lang_error('feature_disabled', false);
	if (!bd_can_view()) bd_require('better_downloads_view');
	bd_load_language();
	loadTemplate('BetterDownloads');
	$context['page_title'] = $txt['better_downloads'];
	$sa = isset($_REQUEST['sa']) ? (string)$_REQUEST['sa'] : 'index';
	if ($sa === 'item') return bd_public_item();
	if ($sa === 'download') return bd_public_download();
	if ($sa === 'screenshot') return bd_public_screenshot();
	return bd_public_index();
}

function bd_public_index()
{
	global $context, $modSettings;
	$category = isset($_REQUEST['cat']) ? (int)$_REQUEST['cat'] : 0;
	$context['bd_categories'] = bd_get_categories(false);
	$context['bd_category_counts'] = bd_get_category_counts();
	$context['bd_active_category'] = $category;
	$context['bd_downloads'] = bd_get_downloads($category, max(1,(int)$modSettings['bd_items_per_page']), 'updated');
	$context['sub_template'] = 'bd_index';
}

function bd_public_item()
{
	global $context, $txt;
	$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
	$item = bd_get_download($id, false);
	if (!$item) fatal_lang_error('no_access', false);
	$context['bd_item'] = $item;
	$context['bd_screenshots'] = bd_get_screenshots($id, false);
	$context['bd_item_integrations_html'] = '';
	unset($context['better_tags_download_view_ran']);

	if (function_exists('call_integration_hook'))
		call_integration_hook(
			'integrate_betterdownloads_item_view',
			array(&$context['bd_item_integrations_html'], $context['bd_item'])
		);

	if (empty($context['better_tags_download_view_ran']))
	{
		$args = array(&$context['bd_item_integrations_html'], $context['bd_item']);
		bd_optional_better_tags_bridge('better_tags_download_view', $args);
	}

	$context['page_title'] = $item['title'] . ' - ' . $txt['better_downloads'];
	$context['sub_template'] = 'bd_item';
}

function bd_public_download()
{
	global $smcFunc, $scripturl, $user_info;
	$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
	$item = bd_get_download($id, false);
	if (!$item) fatal_lang_error('no_access', false);
	if (bd_guest_browse_only())
		redirectexit('action=login');
	if (!bd_can_download($id))
		bd_require('better_downloads_download');
	$smcFunc['db_query']('', 'UPDATE {db_prefix}bd_downloads SET num_downloads=num_downloads+1 WHERE id_download={int:id}', array('id'=>$id));
	if (!empty($item['external_url'])) redirectexit($item['external_url']);
	if (empty($item['stored_name'])) fatal_error('The download file is missing.', false);
	if (bd_stored_is_s3($item['stored_name']))
	{
		if (!bd_betterstorage_available()) fatal_error('Better Storage is required to access this cloud-stored download.', false);
		$cfg = betterstorage_source_config('downloads');
		$url = betterstorage_s3_presign_get($cfg, bd_stored_s3_key($item['stored_name']), 300, (string) $item['filename']);
		if ($url === '') fatal_error('The cloud download could not be prepared.', false);
		redirectexit($url);
	}
	$path = bd_storage_root() . '/files/' . basename($item['stored_name']);
	if (!is_file($path)) fatal_error('The download file is missing.', false);
	while (ob_get_level()) @ob_end_clean();
	header('Content-Type: ' . (!empty($item['mime_type']) ? $item['mime_type'] : 'application/octet-stream'));
	header('Content-Disposition: attachment; filename="' . str_replace(array('"','\r','\n'), '', $item['filename']) . '"');
	header('Content-Length: ' . filesize($path));
	header('X-Content-Type-Options: nosniff');
	readfile($path);
	exit;
}

function bd_public_screenshot()
{
	global $smcFunc;
	$shot_id = isset($_REQUEST['shot']) ? (int) $_REQUEST['shot'] : 0;
	$value = '';
	if ($shot_id > 0) {
		$request = $smcFunc['db_query']('', 'SELECT s.id_download, s.screenshot_url FROM {db_prefix}bd_screenshots AS s WHERE s.id_screenshot={int:id} LIMIT 1', array('id'=>$shot_id));
		$shot = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
		if (!$shot || !bd_get_download((int)$shot['id_download'], false)) fatal_lang_error('no_access', false);
		$value = $shot['screenshot_url'];
	} else {
		$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
		$item = bd_get_download($id, false);
		if (!$item) fatal_lang_error('no_access', false);
		$value = $item['screenshot_url'];
	}
	if (!bd_screenshot_is_local($value)) fatal_lang_error('no_access', false);
	$name = basename(substr((string) $value, 6));
	$path = bd_storage_root() . '/screenshots/' . $name;
	if (!is_file($path)) fatal_error('The screenshot is missing.', false);
	$mime = 'image/jpeg';
	if (function_exists('finfo_open')) { $f = finfo_open(FILEINFO_MIME_TYPE); if ($f) { $m = finfo_file($f, $path); if ($m) $mime = $m; finfo_close($f); } }
	while (ob_get_level()) @ob_end_clean();
	header('Content-Type: ' . $mime);
	header('Content-Length: ' . filesize($path));
	header('Cache-Control: public, max-age=86400');
	header('X-Content-Type-Options: nosniff');
	readfile($path);
	exit;
}

function bd_admin_dispatch()
{
	global $context, $txt;
	isAllowedTo('admin_forum');
	bd_load_language();
	loadTemplate('BetterDownloads');

	// dev8n: Admin panel frame authority.
	// Keep this admin-only and source-injected so generated/custom themes cannot
	// accidentally make the panel frame invisible through --tl-border or a stale
	// theme-local BetterDownloads stylesheet/template.
	if (!isset($context['html_headers']))
		$context['html_headers'] = '';
	$context['html_headers'] .= '\n<style id="better-downloads-admin-frame">
.bd_admin_panel{
  box-sizing:border-box!important;
  border:1px solid #aeb8c2!important;
  border-radius:7px!important;
  background:transparent!important;
  overflow:hidden!important;
}
.bd_admin_panel>.cat_bar{
  margin:0!important;
  border:0!important;
  border-radius:0!important;
}
.bd_admin_panel>.bd_admin_body{
  box-sizing:border-box!important;
  margin:0!important;
  border:0!important;
  border-radius:0!important;
}
</style>';

	$context['page_title'] = $txt['bd_admin_title'];
	$allowed = array('downloads', 'categories', 'settings');
	$context['bd_admin_sa'] = isset($_REQUEST['sa']) && in_array((string) $_REQUEST['sa'], $allowed, true) ? (string) $_REQUEST['sa'] : 'downloads';

	// Use SMF's native admin subsection tabs, matching Better Portal and the
	// rest of the Administration Center instead of drawing our own button row.
	if (!empty($context['admin_menu_name']))
	{
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['bd_admin_title'],
			'description' => $txt['bd_admin_desc'],
			'tabs' => array(
				'downloads' => array('description' => $txt['bd_admin_downloads_desc']),
				'categories' => array('description' => $txt['bd_admin_categories_desc']),
				'settings' => array('description' => $txt['bd_admin_settings_desc']),
			),
		);
	}

	if ($context['bd_admin_sa'] === 'categories') return bd_admin_categories();
	if ($context['bd_admin_sa'] === 'settings') return bd_admin_settings();
	return bd_admin_downloads();
}

function bd_admin_membergroups()
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', 'SELECT id_group, group_name FROM {db_prefix}membergroups WHERE id_group >= {int:min} ORDER BY min_posts, group_name', array('min'=>1));
	$groups = array();
	while ($row=$smcFunc['db_fetch_assoc']($request)) $groups[(int)$row['id_group']]=$row['group_name'];
	$smcFunc['db_free_result']($request);
	return $groups;
}

function bd_admin_categories()
{
	global $context, $smcFunc, $scripturl;
	if (!empty($_POST['save_category'])) {
		checkSession('post');
		$id = !empty($_POST['id_category']) ? (int)$_POST['id_category'] : 0;
		$name = trim((string)$_POST['name']); if ($name==='') fatal_error('Category name is required.', false);
		$groups = !empty($_POST['allowed_groups']) ? array_map('intval',(array)$_POST['allowed_groups']) : array();
		$data = array('name'=>$name,'description'=>(string)$_POST['description'],'slug'=>bd_slug($name),'allowed_groups'=>implode(',',array_filter($groups)),'sort_order'=>(int)$_POST['sort_order'],'enabled'=>empty($_POST['enabled'])?0:1,'updated_at'=>time());
		if ($id) $smcFunc['db_query']('', 'UPDATE {db_prefix}bd_categories SET name={string:name},description={string:description},slug={string:slug},allowed_groups={string:groups},sort_order={int:sort},enabled={int:enabled},updated_at={int:now} WHERE id_category={int:id}', array('name'=>$data['name'],'description'=>$data['description'],'slug'=>$data['slug'],'groups'=>$data['allowed_groups'],'sort'=>$data['sort_order'],'enabled'=>$data['enabled'],'now'=>$data['updated_at'],'id'=>$id));
		else $smcFunc['db_insert']('insert','{db_prefix}bd_categories',array('name'=>'string','description'=>'string','slug'=>'string','allowed_groups'=>'string','sort_order'=>'int','enabled'=>'int','created_at'=>'int','updated_at'=>'int'),array($data['name'],$data['description'],$data['slug'],$data['allowed_groups'],$data['sort_order'],$data['enabled'],time(),time()),array('id_category'));
		redirectexit('action=admin;area=betterdownloads;sa=categories');
	}
	if (!empty($_POST['delete_category'])) {
		checkSession('post'); $id=(int)$_POST['id_category'];
		$request=$smcFunc['db_query']('', 'SELECT COUNT(*) AS c FROM {db_prefix}bd_downloads WHERE id_category={int:id}',array('id'=>$id)); $row=$smcFunc['db_fetch_assoc']($request); $smcFunc['db_free_result']($request);
		if (!empty($row['c'])) fatal_error('Move or delete downloads in this category first.', false);
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bd_categories WHERE id_category={int:id}',array('id'=>$id)); redirectexit('action=admin;area=betterdownloads;sa=categories');
	}
	$context['bd_categories_all']=bd_get_categories(true);
	$context['bd_membergroups']=bd_admin_membergroups();
	$edit=!empty($_REQUEST['edit'])?(int)$_REQUEST['edit']:0;
	$context['bd_edit_category']=$edit && isset($context['bd_categories_all'][$edit])?$context['bd_categories_all'][$edit]:array();
	$context['sub_template']='bd_admin_categories';
}

function bd_validate_upload($file)
{
	global $modSettings;
	if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$allowed = array_filter(array_map(function($v){ return strtolower(ltrim(trim($v),'.')); }, preg_split('~[\s,;]+~',(string)$modSettings['bd_allowed_extensions'])));
	if (!in_array($ext,$allowed,true)) fatal_error('That file type is not allowed.', false);
	$max = max(1,(int)$modSettings['bd_max_file_mb']) * 1024 * 1024;
	if ((int)$file['size'] > $max) fatal_error('That file is too large.', false);
	$mime='application/octet-stream';
	if (function_exists('finfo_open')) { $f=finfo_open(FILEINFO_MIME_TYPE); if ($f) { $m=finfo_file($f,$file['tmp_name']); if ($m) $mime=$m; finfo_close($f); } }
	return array('ext'=>$ext,'mime'=>$mime);
}

function bd_store_upload($file)
{
    $check=bd_validate_upload($file); if (!$check) return false;
    $stored=bin2hex(random_bytes(16)).'.'.$check['ext'];
    $cfg = bd_storage_config();
    if (!empty($cfg['backend']) && $cfg['backend'] === 's3' && bd_betterstorage_available())
    {
        $relative = 'files/' . $stored;
        $result = betterstorage_s3_put_file($cfg, $relative, $file['tmp_name'], $check['mime']);
        if (empty($result['ok'])) fatal_error('Could not store the uploaded file in Amazon S3: ' . ($result['error'] ?? 'Unknown error'), false);
        if (function_exists('betterstorage_register_object'))
            betterstorage_register_object('downloads','s3',$relative,(int)$file['size'],$check['mime'],0);
        return array('filename'=>basename($file['name']),'stored_name'=>'s3:'.$relative,'mime_type'=>$check['mime'],'file_size'=>(int)$file['size']);
    }
    $root=bd_storage_root().'/files'; if (!is_dir($root)) @mkdir($root,0755,true);
    if (!@move_uploaded_file($file['tmp_name'],$root.'/'.$stored)) fatal_error('Could not store the uploaded file.', false);
    if (bd_betterstorage_available() && function_exists('betterstorage_register_object'))
        betterstorage_register_object('downloads','local','files/'.$stored,(int)$file['size'],$check['mime'],0);
    return array('filename'=>basename($file['name']),'stored_name'=>$stored,'mime_type'=>$check['mime'],'file_size'=>(int)$file['size']);
}


function bd_optional_better_tags_bridge($function, array $args = array())
{
	global $sourcedir;

	if (!is_file($sourcedir . '/BetterTags.php'))
		return false;

	require_once($sourcedir . '/BetterTags.php');

	if (!function_exists($function))
		return false;

	call_user_func_array($function, $args);
	return true;
}


function bd_admin_downloads()
{
	global $context, $smcFunc;
	if (!empty($_POST['save_download'])) {
		checkSession('post');
		$id=!empty($_POST['id_download'])?(int)$_POST['id_download']:0;
		$old=$id?bd_get_download($id,true):false;
		$title=trim((string)$_POST['title']); if ($title==='') fatal_error('Download title is required.', false);
		$id_category=(int)$_POST['id_category']; $cats=bd_get_categories(true); if (!isset($cats[$id_category])) fatal_error('Choose a valid category.', false);
		$file=array('filename'=>$old?$old['filename']:'','stored_name'=>$old?$old['stored_name']:'','mime_type'=>$old?$old['mime_type']:'','file_size'=>$old?(int)$old['file_size']:0);
		if (!empty($_FILES['download_file']['tmp_name'])) { $new=bd_store_upload($_FILES['download_file']); if ($old && !empty($old['stored_name'])) bd_delete_stored_file($old['stored_name']); $file=$new; }
		$external=trim((string)$_POST['external_url']); if ($external!=='' && !filter_var($external,FILTER_VALIDATE_URL)) fatal_error('External URL is not valid.', false);
		$legacy_shot=$old?(string)$old['screenshot_url']:'';
		$values=array('id_category'=>$id_category,'id_member'=>(int)$_POST['id_member'],'title'=>$title,'slug'=>bd_slug($title),'description'=>(string)$_POST['description'],'version'=>(string)$_POST['version'],'package_type'=>(string)$_POST['package_type'],'smf_compatibility'=>(string)$_POST['smf_compatibility'],'changelog'=>(string)$_POST['changelog'],'support_url'=>(string)$_POST['support_url'],'screenshot_url'=>$legacy_shot,'external_url'=>$external,'filename'=>$file['filename'],'stored_name'=>$file['stored_name'],'mime_type'=>$file['mime_type'],'file_size'=>$file['file_size'],'approved'=>empty($_POST['approved'])?0:1,'enabled'=>empty($_POST['enabled'])?0:1,'updated_at'=>time());
		if ($id) {
			$smcFunc['db_query']('', 'UPDATE {db_prefix}bd_downloads SET id_category={int:cat},id_member={int:member},title={string:title},slug={string:slug},description={string:description},version={string:version},package_type={string:type},smf_compatibility={string:compat},changelog={string:changelog},support_url={string:support},external_url={string:external},filename={string:filename},stored_name={string:stored},mime_type={string:mime},file_size={int:size},approved={int:approved},enabled={int:enabled},updated_at={int:now} WHERE id_download={int:id}', array('cat'=>$values['id_category'],'member'=>$values['id_member'],'title'=>$values['title'],'slug'=>$values['slug'],'description'=>$values['description'],'version'=>$values['version'],'type'=>$values['package_type'],'compat'=>$values['smf_compatibility'],'changelog'=>$values['changelog'],'support'=>$values['support_url'],'external'=>$values['external_url'],'filename'=>$values['filename'],'stored'=>$values['stored_name'],'mime'=>$values['mime_type'],'size'=>$values['file_size'],'approved'=>$values['approved'],'enabled'=>$values['enabled'],'now'=>$values['updated_at'],'id'=>$id));
		} else {
			$smcFunc['db_insert']('insert','{db_prefix}bd_downloads',array('id_category'=>'int','id_member'=>'int','title'=>'string','slug'=>'string','description'=>'string','version'=>'string','package_type'=>'string','smf_compatibility'=>'string','changelog'=>'string','support_url'=>'string','screenshot_url'=>'string','external_url'=>'string','filename'=>'string','stored_name'=>'string','mime_type'=>'string','file_size'=>'int','num_downloads'=>'int','approved'=>'int','enabled'=>'int','created_at'=>'int','updated_at'=>'int'),array($values['id_category'],$values['id_member'],$values['title'],$values['slug'],$values['description'],$values['version'],$values['package_type'],$values['smf_compatibility'],$values['changelog'],$values['support_url'],'',$values['external_url'],$values['filename'],$values['stored_name'],$values['mime_type'],$values['file_size'],0,$values['approved'],$values['enabled'],time(),time()),array('id_download'));
			$id=(int)$smcFunc['db_insert_id']('{db_prefix}bd_downloads','id_download');
		}

		// Update existing gallery items first.
		$shots = bd_get_screenshots($id, true);
		$remove = !empty($_POST['remove_screenshot_ids']) ? array_map('intval',(array)$_POST['remove_screenshot_ids']) : array();
		$order = isset($_POST['screenshot_order']) && is_array($_POST['screenshot_order']) ? $_POST['screenshot_order'] : array();
		foreach ($shots as $shot) {
			$sid=(int)$shot['id_screenshot'];
			if (in_array($sid,$remove,true)) { bd_delete_screenshot_record($shot); continue; }
			if (isset($order[$sid])) $smcFunc['db_query']('', 'UPDATE {db_prefix}bd_screenshots SET sort_order={int:sort} WHERE id_screenshot={int:id}', array('sort'=>(int)$order[$sid],'id'=>$sid));
		}
		$next_sort = 10;
		$remaining = bd_get_screenshots($id, true);
		foreach ($remaining as $shot) $next_sort=max($next_sort,(int)$shot['sort_order']+10);

		// Add multiple uploaded screenshots.
		if (!empty($_FILES['screenshot_files'])) {
			foreach (bd_normalize_uploaded_files($_FILES['screenshot_files']) as $upload) {
				if ((int)$upload['error'] === UPLOAD_ERR_NO_FILE) continue;
				$value=bd_store_screenshot($upload);
				if ($value) { bd_add_screenshot($id,$value,$next_sort,0); $next_sort+=10; }
			}
		}
		// Add any external screenshot URLs, one per line.
		$urls = isset($_POST['screenshot_urls']) ? preg_split('~[\r\n]+~',(string)$_POST['screenshot_urls']) : array();
		foreach ($urls as $url) {
			$url=trim($url); if ($url==='') continue;
			if (!filter_var($url,FILTER_VALIDATE_URL)) fatal_error('One of the screenshot URLs is not valid.', false);
			bd_add_screenshot($id,$url,$next_sort,0); $next_sort+=10;
		}
		$preferred = !empty($_POST['primary_screenshot']) ? (int)$_POST['primary_screenshot'] : 0;
		bd_sync_primary_screenshot($id,$preferred);

		unset($GLOBALS['better_tags_download_saved_ran'][(int) $id]);
		if (function_exists('call_integration_hook'))
			call_integration_hook('integrate_betterdownloads_item_saved', array($id));
		if (empty($GLOBALS['better_tags_download_saved_ran'][(int) $id]))
			bd_optional_better_tags_bridge('better_tags_download_saved', array($id));

		redirectexit('action=admin;area=betterdownloads;edit='.$id);
	}
	if (!empty($_POST['delete_download'])) {
		checkSession('post'); $id=(int)$_POST['id_download']; $old=bd_get_download($id,true);
		if ($old && !empty($old['stored_name'])) bd_delete_stored_file($old['stored_name']);
		foreach (bd_get_screenshots($id,true) as $shot) bd_delete_screenshot_record($shot);
		if ($old && !empty($old['screenshot_url']) && !bd_get_screenshots($id,true)) bd_delete_local_screenshot($old['screenshot_url']);
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bd_downloads WHERE id_download={int:id}',array('id'=>$id));

		unset($GLOBALS['better_tags_download_deleted_ran'][(int) $id]);
		if (function_exists('call_integration_hook'))
			call_integration_hook('integrate_betterdownloads_item_deleted', array($id));
		if (empty($GLOBALS['better_tags_download_deleted_ran'][(int) $id]))
			bd_optional_better_tags_bridge('better_tags_download_deleted', array($id));

		redirectexit('action=admin;area=betterdownloads');
	}
	$context['bd_categories_all']=bd_get_categories(true);
	$request=$smcFunc['db_query']('', 'SELECT d.*, c.name AS category_name FROM {db_prefix}bd_downloads AS d LEFT JOIN {db_prefix}bd_categories AS c ON (c.id_category=d.id_category) ORDER BY d.updated_at DESC',array());
	$context['bd_downloads_all']=array(); while($row=$smcFunc['db_fetch_assoc']($request)) $context['bd_downloads_all'][]=$row; $smcFunc['db_free_result']($request);
	$edit=!empty($_REQUEST['edit'])?(int)$_REQUEST['edit']:0; $context['bd_edit_download']=$edit?bd_get_download($edit,true):array();
	$context['bd_edit_screenshots']=$edit?bd_get_screenshots($edit,true):array();
	$context['bd_download_form_integrations_html'] = '';
	unset($context['better_tags_download_form_ran']);

	if (function_exists('call_integration_hook'))
		call_integration_hook(
			'integrate_betterdownloads_item_form',
			array(&$context['bd_download_form_integrations_html'], $context['bd_edit_download'])
		);

	if (empty($context['better_tags_download_form_ran']))
	{
		$args = array(&$context['bd_download_form_integrations_html'], $context['bd_edit_download']);
		bd_optional_better_tags_bridge('better_tags_download_form', $args);
	}

	$context['sub_template']='bd_admin_downloads';
}

function bd_admin_settings()
{
	global $context, $modSettings;
	if (!empty($_POST['save_settings'])) {
		checkSession('post');
		$path=trim((string)$_POST['bd_storage_path']);
		$submission_groups = isset($_POST['bd_submission_groups']) ? array_map('intval', (array) $_POST['bd_submission_groups']) : array();
		$submission_groups = array_values(array_unique(array_filter($submission_groups, function($v){ return $v >= 0; })));
		updateSettings(array(
			'bd_enabled'=>empty($_POST['bd_enabled'])?0:1,
			'bd_show_menu'=>empty($_POST['bd_show_menu'])?0:1,
			'bd_guest_browse_only'=>empty($_POST['bd_guest_browse_only'])?0:1,
			'bd_allow_member_submissions'=>empty($_POST['bd_allow_member_submissions'])?0:1,
			'bd_submission_groups'=>implode(',', $submission_groups),
			'bd_require_approval'=>empty($_POST['bd_require_approval'])?0:1,
			'bd_allowed_extensions'=>trim((string)$_POST['bd_allowed_extensions']),
			'bd_max_file_mb'=>max(1,(int)$_POST['bd_max_file_mb']),
			'bd_items_per_page'=>max(1,(int)$_POST['bd_items_per_page']),
			'bd_storage_path'=>$path,
		));
		redirectexit('action=admin;area=betterdownloads;sa=settings');
	}
	$context['bd_settings_values']=$modSettings;
	$context['bd_submission_membergroups']=array(0 => 'Regular Members') + bd_admin_membergroups();
	$context['bd_submission_groups_selected']=bd_submission_group_ids();
	// Render the settings form from the MOD source rather than relying on a
	// possibly stale theme-local BetterDownloads.template.php. Theme Lab and
	// other generated themes may contain an older copy that otherwise masks
	// newly added admin settings even though the MOD itself upgraded correctly.
	$context['sub_template']='bd_admin_settings_runtime';
}

function template_bd_admin_settings_runtime()
{
	global $context, $scripturl, $txt;
	$v = $context['bd_settings_values'];
	$selected = isset($context['bd_submission_groups_selected']) ? (array) $context['bd_submission_groups_selected'] : array();
	$h = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };

	echo '<section class="bd_admin_panel"><div class="cat_bar"><h3 class="catbg">Settings</h3></div><div class="bd_admin_body windowbg"><form method="post" action="', $scripturl, '?action=admin;area=betterdownloads;sa=settings"><input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';
	echo '<div class="bd_form_section"><h4>General</h4><div class="bd_check_row"><label class="bd_check"><input type="checkbox" name="bd_enabled" value="1" ', !empty($v['bd_enabled']) ? 'checked' : '', '> ', $h($txt['bd_enabled']), '</label><label class="bd_check"><input type="checkbox" name="bd_show_menu" value="1" ', !empty($v['bd_show_menu']) ? 'checked' : '', '> ', $h($txt['bd_show_menu']), '</label></div>';
	echo '<div class="bd_form_row"><label class="bd_check"><input type="checkbox" name="bd_guest_browse_only" value="1" ', !empty($v['bd_guest_browse_only']) ? 'checked' : '', '> ', $h($txt['bd_guest_browse_only']), '</label><small>', $h($txt['bd_guest_browse_only_help']), '</small></div></div>';
	echo '<div class="bd_form_section"><h4>', $h($txt['bd_member_submissions']), '</h4><div class="bd_form_row"><label class="bd_check"><input type="checkbox" name="bd_allow_member_submissions" value="1" ', !empty($v['bd_allow_member_submissions']) ? 'checked' : '', '> ', $h($txt['bd_allow_member_submissions']), '</label><small>', $h($txt['bd_allow_member_submissions_help']), '</small></div>';
	echo '<div class="bd_form_row"><label>', $h($txt['bd_submission_groups']), '</label><div class="bd_group_grid">';
	foreach ($context['bd_submission_membergroups'] as $id_group => $name)
		echo '<label class="bd_check"><input type="checkbox" name="bd_submission_groups[]" value="', (int) $id_group, '" ', in_array((int) $id_group, $selected, true) ? 'checked' : '', '> ', $h($name), '</label>';
	echo '</div><small>', $h($txt['bd_submission_groups_help']), '</small></div>';
	echo '<div class="bd_form_row"><label class="bd_check"><input type="checkbox" name="bd_require_approval" value="1" ', !empty($v['bd_require_approval']) ? 'checked' : '', '> ', $h($txt['bd_require_approval']), '</label><small>', $h($txt['bd_require_approval_help']), '</small></div></div>';
	echo '<div class="bd_form_section"><h4>Files</h4><div class="bd_form_two"><div class="bd_form_row"><label>', $h($txt['bd_allowed_extensions']), '</label><input type="text" name="bd_allowed_extensions" value="', $h($v['bd_allowed_extensions']), '"></div><div class="bd_form_row"><label>', $h($txt['bd_max_file_mb']), '</label><input type="number" min="1" name="bd_max_file_mb" value="', (int) $v['bd_max_file_mb'], '"></div></div><div class="bd_form_row"><label>', $h($txt['bd_storage_path']), '</label><input type="text" name="bd_storage_path" value="', $h(isset($v['bd_storage_path']) ? $v['bd_storage_path'] : ''), '"><small>', $h($txt['bd_storage_path_help']), '</small></div></div>';
	echo '<div class="bd_form_section"><h4>Display</h4><div class="bd_form_row"><label>', $h($txt['bd_items_per_page']), '</label><input type="number" min="1" name="bd_items_per_page" value="', (int) $v['bd_items_per_page'], '"></div></div><div class="bd_actions bd_actions_end"><button class="button" type="submit" name="save_settings" value="1">Save</button></div></form></div></section>';
}
