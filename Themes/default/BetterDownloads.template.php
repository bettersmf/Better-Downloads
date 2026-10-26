<?php
function bd_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bd_excerpt($text, $length = 260)
{
	$text = trim(preg_replace('~\s+~', ' ', strip_tags((string)$text)));
	if (function_exists('mb_strlen') && mb_strlen($text) > $length)
		return rtrim(mb_substr($text, 0, $length - 1)) . '…';
	if (strlen($text) > $length)
		return rtrim(substr($text, 0, $length - 1)) . '…';
	return $text;
}
function bd_render_badge($text, $class = '')
{
	if ((string)$text === '') return;
	echo '<span class="bd_badge ', bd_h($class), '">', bd_h($text), '</span>';
}
function template_bd_index()
{
	global $context,$scripturl,$txt,$user_info;
	$active = (int)$context['bd_active_category'];
	echo '<div class="bd_wrap">';
	echo '<div class="cat_bar"><h3 class="catbg">',bd_h($txt['better_downloads']),'</h3></div>';
	echo '<div class="windowbg bd_landing_body">';
	if ($context['bd_categories']) {
		echo '<div class="bd_category_cards">';
		echo '<a class="bd_category_card', $active===0?' active':'', '" href="',$scripturl,'?action=downloads"><strong>All Downloads</strong><span>Browse every available download.</span></a>';
		foreach($context['bd_categories'] as $c){
			$count = isset($context['bd_category_counts'][(int)$c['id_category']]) ? (int)$context['bd_category_counts'][(int)$c['id_category']] : 0;
			echo '<a class="bd_category_card', $active===(int)$c['id_category']?' active':'', '" href="',$scripturl,'?action=downloads;cat=',(int)$c['id_category'],'"><strong>',bd_h($c['name']),'</strong>';
			if(trim((string)$c['description'])!=='') echo '<span>',bd_h(bd_excerpt($c['description'],100)),'</span>';
			echo '<small>',$count,' ', $count===1?'download':'downloads','</small></a>';
		}
		echo '</div>';
	}
	if (!$context['bd_downloads']) echo '<div class="bd_empty">',bd_h($txt['bd_no_downloads']),'</div>';
	else {
		echo '<div class="bd_download_list">';
		foreach($context['bd_downloads'] as $d){
			$shot = bd_screenshot_src($d);
			echo '<article class="bd_download_card">';
			echo '<div class="bd_download_visual">';
			if($shot!=='') echo '<a href="',$scripturl,'?action=downloads;sa=item;id=',(int)$d['id_download'],'"><img class="bd_card_thumb" src="',bd_h($shot),'" alt="',bd_h($d['title']),'"></a>';
			else echo '<div class="bd_placeholder" aria-hidden="true">↓</div>';
			echo '</div><div class="bd_download_content">';
			echo '<div class="bd_card_heading"><div><h3><a href="',$scripturl,'?action=downloads;sa=item;id=',(int)$d['id_download'],'">',bd_h($d['title']),'</a></h3><div class="bd_badges">';
			bd_render_badge($d['category_name'],'category');
			if($d['version']!=='') bd_render_badge('v'.$d['version'],'version');
			if($d['package_type']!=='') bd_render_badge(bd_package_type_label($d['package_type']),'type');
			echo '</div></div><div class="bd_count"><strong>',(int)$d['num_downloads'],'</strong><span>',bd_h($txt['bd_download_count']),'</span></div></div>';
			echo '<p class="bd_description">',nl2br(bd_h(bd_excerpt($d['description'],360))),'</p>';
			echo '<div class="bd_card_footer"><div class="bd_meta_line">';
			if($d['smf_compatibility']!=='') echo '<span><strong>',bd_h($txt['bd_compatibility']),':</strong> ',bd_h($d['smf_compatibility']),'</span>';
			if(!empty($d['file_size'])) echo '<span><strong>Size:</strong> ',bd_h(bd_format_bytes($d['file_size'])),'</span>';
			echo '</div><div class="bd_actions"><a class="button" href="',$scripturl,'?action=downloads;sa=item;id=',(int)$d['id_download'],'">Details</a>';
			if(bd_can_download((int)$d['id_download'])) echo '<a class="button" href="',$scripturl,'?action=downloads;sa=download;id=',(int)$d['id_download'],'">',bd_h($txt['bd_download_now']),'</a>'; elseif(bd_guest_browse_only()) echo '<a class="button" href="',$scripturl,'?action=login">',bd_h($txt['bd_login_to_download']),'</a>';
			echo '</div></div></div></article>';
		}
		echo '</div>';
	}
	echo '</div></div>';
}
function template_bd_item()
{
	global $context,$scripturl,$txt,$user_info;
	$d=$context['bd_item'];
	$shots=!empty($context['bd_screenshots'])?(array)$context['bd_screenshots']:array();
	if (!$shots) { $legacy=bd_get_primary_screenshot($d,false); if ($legacy) $shots=array($legacy); }
	$primary=!empty($shots)?$shots[0]:false;
	$shot=$primary&&!empty($primary['src'])?$primary['src']:'';
	echo '<div class="bd_wrap">';
	echo '<div class="cat_bar"><h3 class="catbg">',bd_h($d['title']),'</h3></div>';
	echo '<div class="windowbg bd_detail_body">';
	echo '<div class="bd_detail_hero"><div class="bd_detail_media"><div class="bd_detail_visual">';
	if($shot!=='') echo '<a id="bd-primary-shot-link" href="',bd_h($shot),'" target="_blank" rel="noopener"><img id="bd-primary-shot" class="bd_detail_thumb" src="',bd_h($shot),'" alt="',bd_h($d['title']),'"></a>'; else echo '<div class="bd_placeholder large" aria-hidden="true">↓</div>';
	echo '</div>';
	if(count($shots)>1){
		echo '<div class="bd_gallery_thumbs" aria-label="Download screenshots">';
		foreach($shots as $i=>$gallery){
			$src=!empty($gallery['src'])?$gallery['src']:bd_screenshot_src_from_value((int)$d['id_download'],$gallery['screenshot_url'],isset($gallery['id_screenshot'])?(int)$gallery['id_screenshot']:0);
			echo '<a class="bd_gallery_thumb', $i===0?' active':'', '" href="',bd_h($src),'" target="_blank" rel="noopener" onclick="var img=document.getElementById(\'bd-primary-shot\'),lnk=document.getElementById(\'bd-primary-shot-link\');if(img){img.src=this.href;if(lnk)lnk.href=this.href;document.querySelectorAll(\'.bd_gallery_thumb\').forEach(function(el){el.classList.remove(\'active\');});this.classList.add(\'active\');return false;}"><img src="',bd_h($src),'" alt="Screenshot ',($i+1),'"></a>';
		}
		echo '</div>';
	}
	echo '</div><div class="bd_detail_summary"><div class="bd_badges">';
	bd_render_badge($d['category_name'],'category'); if($d['version']!=='') bd_render_badge('v'.$d['version'],'version'); bd_render_badge(bd_package_type_label($d['package_type']),'type');
	echo '</div><h2>',bd_h($d['title']),'</h2><div class="bd_detail_meta_grid">';
	if($d['version']!=='') echo '<div><span>',bd_h($txt['bd_version']),'</span><strong>',bd_h($d['version']),'</strong></div>';
	if($d['smf_compatibility']!=='') echo '<div><span>',bd_h($txt['bd_compatibility']),'</span><strong>',bd_h($d['smf_compatibility']),'</strong></div>';
	echo '<div><span>',bd_h($txt['bd_download_count']),'</span><strong>',(int)$d['num_downloads'],'</strong></div>';
	if(!empty($d['file_size'])) echo '<div><span>File size</span><strong>',bd_h(bd_format_bytes($d['file_size'])),'</strong></div>';
	echo '</div><div class="bd_actions bd_primary_actions">';
	if(bd_can_download((int)$d['id_download'])) echo '<a class="button" href="',$scripturl,'?action=downloads;sa=download;id=',(int)$d['id_download'],'">',bd_h($txt['bd_download_now']),'</a>'; elseif(bd_guest_browse_only()) echo '<a class="button" href="',$scripturl,'?action=login">',bd_h($txt['bd_login_to_download']),'</a>';
	if(!empty($d['support_url'])) echo '<a class="button" href="',bd_h($d['support_url']),'" rel="noopener">',bd_h($txt['bd_support']),'</a>';
	echo '</div></div></div>';
	if (!empty($context['bd_item_integrations_html'])) echo $context['bd_item_integrations_html'];
	echo '<div class="bd_detail_sections"><section><h3>Description</h3><div class="bd_prose">',nl2br(bd_h($d['description'])),'</div></section>';
	if($d['changelog']!=='') echo '<section><h3>',bd_h($txt['bd_changelog']),'</h3><div class="bd_prose">',nl2br(bd_h($d['changelog'])),'</div></section>';
	echo '<section><h3>File information</h3><dl class="bd_file_info">';
	echo '<div><dt>Category</dt><dd>',bd_h($d['category_name']),'</dd></div><div><dt>Type</dt><dd>',bd_h(bd_package_type_label($d['package_type'])),'</dd></div>';
	if($d['filename']!=='') echo '<div><dt>File</dt><dd>',bd_h($d['filename']),'</dd></div>';
	if($d['smf_compatibility']!=='') echo '<div><dt>',bd_h($txt['bd_compatibility']),'</dt><dd>',bd_h($d['smf_compatibility']),'</dd></div>';
	if(!empty($d['updated_at'])) echo '<div><dt>Last updated</dt><dd>',bd_h(strip_tags(timeformat($d['updated_at']))),'</dd></div>';
	echo '</dl></section></div></div></div>';
}

function template_bd_admin_categories()
{
	global $context,$scripturl; $e=$context['bd_edit_category'];
	echo '<div class="bd_admin_grid"><section class="bd_admin_panel"><div class="cat_bar"><h3 class="catbg">Categories</h3></div><div class="bd_admin_body windowbg"><table class="bd_table"><tr><th>Name</th><th>Order</th><th>Status</th><th></th></tr>';
	foreach($context['bd_categories_all'] as $c) echo '<tr><td><strong>',bd_h($c['name']),'</strong>',trim((string)$c['description'])!==''?'<small>'.bd_h(bd_excerpt($c['description'],90)).'</small>':'','</td><td>',(int)$c['sort_order'],'</td><td>',empty($c['enabled'])?'<span class="bd_status bd_status_off">Disabled</span>':'<span class="bd_status bd_status_live">Enabled</span>','</td><td><a href="',$scripturl,'?action=admin;area=betterdownloads;sa=categories;edit=',(int)$c['id_category'],'">Edit</a></td></tr>';
	echo '</table></div></section>';
	echo '<section class="bd_admin_panel"><div class="cat_bar"><h3 class="catbg">',empty($e)?'Add category':'Edit category','</h3></div><div class="bd_admin_body windowbg"><form method="post" action="',$scripturl,'?action=admin;area=betterdownloads;sa=categories"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><input type="hidden" name="id_category" value="',!empty($e['id_category'])?(int)$e['id_category']:0,'">';
	echo '<div class="bd_form_section"><h4>Category details</h4><div class="bd_form_row"><label>Name</label><input type="text" name="name" value="',bd_h(isset($e['name'])?$e['name']:''),'" required></div><div class="bd_form_row"><label>Description</label><textarea name="description" rows="4">',bd_h(isset($e['description'])?$e['description']:''),'</textarea></div></div>';
	echo '<div class="bd_form_section"><h4>Visibility & order</h4><div class="bd_form_row"><label>Visible to membergroups</label><select name="allowed_groups[]" multiple size="6">'; $sel=array_filter(array_map('intval',explode(',',isset($e['allowed_groups'])?$e['allowed_groups']:''))); foreach($context['bd_membergroups'] as $id=>$name) echo '<option value="',$id,'"',in_array($id,$sel)?' selected':'','>',bd_h($name),'</option>'; echo '</select><small>Leave all unselected for everyone with view permission.</small></div><div class="bd_form_row"><label>Sort order</label><input type="number" name="sort_order" value="',isset($e['sort_order'])?(int)$e['sort_order']:0,'"></div><label class="bd_check"><input type="checkbox" name="enabled" value="1" ',empty($e)||!empty($e['enabled'])?'checked':'','> Enabled</label></div>';
	echo '<div class="bd_actions"><button class="button" type="submit" name="save_category" value="1">Save</button>'; if(!empty($e['id_category'])) echo '<button class="button" type="submit" name="delete_category" value="1" onclick="return confirm(\'Delete this category?\')">Delete</button>'; echo '</div></form></div></section></div>';
}
function template_bd_admin_downloads()
{
	global $context,$scripturl; $e=$context['bd_edit_download'];
	echo '<div class="bd_admin_grid"><section class="bd_admin_panel"><div class="cat_bar"><h3 class="catbg">Downloads</h3></div><div class="bd_admin_body windowbg"><table class="bd_table bd_download_admin_table"><tr><th>Title</th><th>Category</th><th>Downloads</th><th>Status</th><th></th></tr>';
	foreach($context['bd_downloads_all'] as $d) {
		$public=$scripturl.'?action=downloads;sa=item;id='.(int)$d['id_download'];
		echo '<tr><td class="bd_admin_download_title"><a href="',$public,'" target="_blank" rel="noopener">',bd_h($d['title']),'</a>',($d['version']!==''?'<small>v'.bd_h($d['version']).'</small>':''),'</td><td>',bd_h($d['category_name']),'</td><td>',(int)$d['num_downloads'],'</td><td>',empty($d['enabled'])?'<span class="bd_status bd_status_off">Disabled</span>':(empty($d['approved'])?'<span class="bd_status bd_status_pending">Pending</span>':'<span class="bd_status bd_status_live">Live</span>'),'</td><td><a href="',$scripturl,'?action=admin;area=betterdownloads;edit=',(int)$d['id_download'],'">Edit</a></td></tr>';
	}
	echo '</table></div></section>';
	echo '<section class="bd_admin_panel"><div class="cat_bar"><h3 class="catbg">',empty($e)?'Add download':'Edit download','</h3></div><div class="bd_admin_body windowbg">';
	if(!$context['bd_categories_all']) echo '<div class="bd_notice">Create a category first.</div>'; else {
		echo '<form method="post" enctype="multipart/form-data" action="',$scripturl,'?action=admin;area=betterdownloads"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><input type="hidden" name="id_download" value="',!empty($e['id_download'])?(int)$e['id_download']:0,'"><input type="hidden" name="id_member" value="',!empty($e['id_member'])?(int)$e['id_member']:1,'">';
		echo '<div class="bd_form_section"><h4>Download details</h4><div class="bd_form_row"><label>Title</label><input type="text" name="title" required value="',bd_h(isset($e['title'])?$e['title']:''),'"></div><div class="bd_form_row"><label>Category</label><select name="id_category">'; foreach($context['bd_categories_all'] as $id=>$c) echo '<option value="',$id,'"',isset($e['id_category'])&&(int)$e['id_category']===$id?' selected':'','>',bd_h($c['name']),'</option>'; echo '</select></div><div class="bd_form_row"><label>Description</label><textarea name="description" rows="5">',bd_h(isset($e['description'])?$e['description']:''),'</textarea></div>';
		if (!empty($context['bd_download_form_integrations_html'])) echo $context['bd_download_form_integrations_html'];
		echo '</div>';
		echo '<div class="bd_form_section"><h4>Release information</h4><div class="bd_form_two"><div class="bd_form_row"><label>Version</label><input type="text" name="version" value="',bd_h(isset($e['version'])?$e['version']:''),'"></div><div class="bd_form_row"><label>Package type</label><select name="package_type">'; foreach(array('mod'=>'SMF MOD','theme'=>'SMF Theme','document'=>'Document','other'=>'Other') as $v=>$label) echo '<option value="',$v,'"',isset($e['package_type'])&&$e['package_type']===$v?' selected':'','>',$label,'</option>'; echo '</select></div></div><div class="bd_form_row"><label>SMF compatibility</label><input type="text" name="smf_compatibility" placeholder="2.1.x" value="',bd_h(isset($e['smf_compatibility'])?$e['smf_compatibility']:''),'"></div><div class="bd_form_row"><label>Changelog</label><textarea name="changelog" rows="4">',bd_h(isset($e['changelog'])?$e['changelog']:''),'</textarea></div><div class="bd_form_row"><label>Support URL</label><input type="text" name="support_url" value="',bd_h(isset($e['support_url'])?$e['support_url']:''),'"></div></div>';
		echo '<div class="bd_form_section"><h4>Screenshot gallery</h4>';
		$shots=!empty($context['bd_edit_screenshots'])?(array)$context['bd_edit_screenshots']:array();
		if($shots){
			echo '<div class="bd_admin_gallery">';
			foreach($shots as $shot){
				$sid=(int)$shot['id_screenshot'];
				echo '<div class="bd_admin_gallery_item"><img src="',bd_h($shot['src']),'" alt=""><div class="bd_admin_gallery_controls"><label class="bd_check"><input type="radio" name="primary_screenshot" value="',$sid,'" ',!empty($shot['is_primary'])?'checked':'','> Cover</label><label>Order <input class="bd_order_input" type="number" name="screenshot_order[',$sid,']" value="',(int)$shot['sort_order'],'"></label><label class="bd_check"><input type="checkbox" name="remove_screenshot_ids[]" value="',$sid,'"> Remove</label></div></div>';
			}
			echo '</div><small class="bd_gallery_help">Choose one cover image. Lower order numbers appear first in the gallery.</small>';
		}
		echo '<div class="bd_form_row"><label>Upload screenshots</label><input type="file" name="screenshot_files[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple><small>You can select multiple JPG, PNG, GIF or WebP images at once.</small></div><div class="bd_form_row"><label>Add screenshot URLs</label><textarea name="screenshot_urls" rows="3" placeholder="https://example.com/screenshot-1.jpg&#10;https://example.com/screenshot-2.jpg"></textarea><small>Optional. Add one external image URL per line.</small></div></div>';
		echo '<div class="bd_form_section"><h4>Download file</h4><div class="bd_form_row"><label>Upload file</label><input type="file" name="download_file">'; if(!empty($e['filename'])) echo '<small>Current: ',bd_h($e['filename']),!empty($e['file_size'])?' · '.bd_h(bd_format_bytes($e['file_size'])):'','</small>'; echo '</div><div class="bd_form_row"><label>External download URL</label><input type="text" name="external_url" value="',bd_h(isset($e['external_url'])?$e['external_url']:''),'"><small>Use either an uploaded file or an external URL.</small></div></div>';
		echo '<div class="bd_form_section"><h4>Publishing</h4><div class="bd_check_row"><label class="bd_check"><input type="checkbox" name="approved" value="1" ',empty($e)||!empty($e['approved'])?'checked':'','> Approved</label><label class="bd_check"><input type="checkbox" name="enabled" value="1" ',empty($e)||!empty($e['enabled'])?'checked':'','> Enabled</label></div></div><div class="bd_actions"><button class="button" type="submit" name="save_download" value="1">Save</button>'; if(!empty($e['id_download'])) echo '<button class="button" type="submit" name="delete_download" value="1" onclick="return confirm(\'Delete this download?\')">Delete</button>'; echo '</div></form>';
	}
	echo '</div></section></div>';
}

function template_bd_admin_settings()
{
	global $context,$scripturl,$txt; $v=$context['bd_settings_values'];
	$selected = isset($context['bd_submission_groups_selected']) ? (array) $context['bd_submission_groups_selected'] : array();
	echo '<section class="bd_admin_panel"><div class="cat_bar"><h3 class="catbg">Settings</h3></div><div class="bd_admin_body windowbg"><form method="post" action="',$scripturl,'?action=admin;area=betterdownloads;sa=settings"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'">';
	echo '<div class="bd_form_section"><h4>General</h4><div class="bd_check_row"><label class="bd_check"><input type="checkbox" name="bd_enabled" value="1" ',!empty($v['bd_enabled'])?'checked':'','> ',bd_h($txt['bd_enabled']),'</label><label class="bd_check"><input type="checkbox" name="bd_show_menu" value="1" ',!empty($v['bd_show_menu'])?'checked':'','> ',bd_h($txt['bd_show_menu']),'</label></div><div class="bd_form_row"><label class="bd_check"><input type="checkbox" name="bd_guest_browse_only" value="1" ',!empty($v['bd_guest_browse_only'])?'checked':'','> ',bd_h($txt['bd_guest_browse_only']),'</label><small>',bd_h($txt['bd_guest_browse_only_help']),'</small></div></div>';
	echo '<div class="bd_form_section"><h4>',bd_h($txt['bd_member_submissions']),'</h4><div class="bd_form_row"><label class="bd_check"><input type="checkbox" name="bd_allow_member_submissions" value="1" ',!empty($v['bd_allow_member_submissions'])?'checked':'','> ',bd_h($txt['bd_allow_member_submissions']),'</label><small>',bd_h($txt['bd_allow_member_submissions_help']),'</small></div>';
	echo '<div class="bd_form_row"><label>',bd_h($txt['bd_submission_groups']),'</label><div class="bd_group_grid">';
	foreach ($context['bd_submission_membergroups'] as $id_group => $name)
		echo '<label class="bd_check"><input type="checkbox" name="bd_submission_groups[]" value="',(int)$id_group,'" ',in_array((int)$id_group,$selected,true)?'checked':'','> ',bd_h($name),'</label>';
	echo '</div><small>',bd_h($txt['bd_submission_groups_help']),'</small></div>';
	echo '<div class="bd_form_row"><label class="bd_check"><input type="checkbox" name="bd_require_approval" value="1" ',!empty($v['bd_require_approval'])?'checked':'','> ',bd_h($txt['bd_require_approval']),'</label><small>',bd_h($txt['bd_require_approval_help']),'</small></div></div>';
	echo '<div class="bd_form_section"><h4>Files</h4><div class="bd_form_two"><div class="bd_form_row"><label>',bd_h($txt['bd_allowed_extensions']),'</label><input type="text" name="bd_allowed_extensions" value="',bd_h($v['bd_allowed_extensions']),'"></div><div class="bd_form_row"><label>',bd_h($txt['bd_max_file_mb']),'</label><input type="number" min="1" name="bd_max_file_mb" value="',(int)$v['bd_max_file_mb'],'"></div></div><div class="bd_form_row"><label>',bd_h($txt['bd_storage_path']),'</label><input type="text" name="bd_storage_path" value="',bd_h(isset($v['bd_storage_path'])?$v['bd_storage_path']:''),'"><small>',bd_h($txt['bd_storage_path_help']),'</small></div></div>';
	echo '<div class="bd_form_section"><h4>Display</h4><div class="bd_form_row"><label>',bd_h($txt['bd_items_per_page']),'</label><input type="number" min="1" name="bd_items_per_page" value="',(int)$v['bd_items_per_page'],'"></div></div><div class="bd_actions bd_actions_end"><button class="button" type="submit" name="save_settings" value="1">Save</button></div></form></div></section>';
}
