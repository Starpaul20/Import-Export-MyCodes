<?php
/**
 * Import/Export MyCodes
 * Copyright 2015 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Tell MyBB when to run the hooks
$plugins->add_hook("admin_config_mycode_begin", "importexportmycodes_run");
$plugins->add_hook("admin_page_output_nav_tabs_start", "importexportmycodes_tabs");
$plugins->add_hook("admin_tools_get_admin_log_action", "importexportmycodes_adminlog");

// The information that shows up on the plugin manager
function importexportmycodes_info()
{
	global $lang;
	$lang->load("importexportmycodes", true);

	return array(
		"name"				=> $lang->importexportmycodes_info_name,
		"description"		=> $lang->importexportmycodes_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"codename"			=> "importexportmycodes",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function importexportmycodes_activate()
{
}

// This function runs when the plugin is deactivated.
function importexportmycodes_deactivate()
{
}

// Actually import or export the MyCodes
function importexportmycodes_run()
{
	global $db, $mybb, $lang, $cache, $page, $sub_tabs;
	$lang->load("importexportmycodes", true);

	require_once MYBB_ROOT."inc/functions_upload.php";

	if($mybb->input['action'] == "export")
	{
		if($mybb->request_method == "post")
		{
			if(trim($mybb->input['name']) == '')
			{
				$errors[] = $lang->error_missing_name;
			}

			$exporttype = $mybb->get_input('exporttype', MyBB::INPUT_INT);

			if($exporttype == 3)
			{
				if(count($mybb->input['mycode_1_mycodes']) < 1)
				{
					$errors[] = $lang->error_no_mycodes_selected;
				}

				$mycode_checked[3] = "checked=\"checked\"";
			}
			else
			{
				$mycode_checked[0] = "checked=\"checked\"";
				$mybb->input['mycode_1_mycodes'] = '';
			}

			if(!$errors)
			{
				$where = '';
				if($exporttype == 1)
				{
					$where = "active='1'";
				}
				elseif($exporttype == 2)
				{
					$where = "active='0'";
				}
				elseif($exporttype == 3)
				{
					if(is_array($mybb->input['mycode_1_mycodes']))
					{
						$checked = array();
						foreach($mybb->input['mycode_1_mycodes'] as $cid)
						{
							$checked[] = (int)$cid;
						}

						$mycodes = implode(',', $checked);
						$where = "cid IN({$mycodes})";
					}
				}

				$xml = "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?".">\n";
				$xml .= "<mycodes version=\"{$mybb->version_code}\" exported=\"".TIME_NOW."\">\n";

				$query = $db->simple_select("mycode", "title, description, regex, replacement, active, parseorder", $where, array('order_by' => 'title', 'order_dir' => 'ASC'));
				while($mycode = $db->fetch_array($query))
				{
					$xml .= "\t<mycode>\n";
					foreach($mycode as $key => $value)
					{
						$value = str_replace(']]>', ']]]]><![CDATA[>', $value);
						$xml .= "\t\t<{$key}><![CDATA[{$value}]]></{$key}>\n";
					}
					$xml .= "\t</mycode>\n";
				}

				$xml .= "</mycodes>";
				$mybb->input['name'] = urlencode($mybb->input['name']);

				// Log admin action
				log_admin_action();

				header("Content-disposition: filename=".$mybb->input['name'].".xml");
				header("Content-Length: ".my_strlen($xml));
				header("Content-type: unknown/unknown");
				header("Pragma: no-cache");
				header("Expires: 0");

				echo $xml;
				exit;
			}
		}

		$sub_tabs['mycode'] = array(
			'title'	=> $lang->mycode,
			'link' => "index.php?module=config-mycode",
			'description' => $lang->mycode_desc
		);

		$sub_tabs['add_new_mycode'] = array(
			'title'	=> $lang->add_new_mycode,
			'link' => "index.php?module=config-mycode&amp;action=add",
			'description' => $lang->add_new_mycode_desc
		);

		$page->add_breadcrumb_item($lang->export_mycode);
		$page->output_header($lang->custom_mycode." - ".$lang->export_mycode);
		$page->output_nav_tabs($sub_tabs, 'export_mycode');

		$form = new Form("index.php?module=config-mycode&amp;action=export", "post", false, true);

		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$mybb->input['mycode_1_mycodes'] = '';
			$mybb->input['name'] = 'mycodes';
			$mycode_checked[0] = "checked=\"checked\"";
			$mycode_checked[1] = '';
			$mycode_checked[2] = '';
			$mycode_checked[3] = '';
		}

		$form_container = new FormContainer($lang->export_mycode);

		$query = $db->simple_select("mycode", "cid, title", "", array("order_by" => "title", "order_dir" => "asc"));
		while($mycode = $db->fetch_array($query))
		{
			$mycodes[$mycode['cid']] = $mycode['title'];
		}

		$type_select = "<script type=\"text/javascript\">
			function checkAction(id)
			{
				var checked = '';

				$('.'+id+'s_check').each(function(e, val)
				{
					if($(this).prop('checked') == true)
					{
						checked = $(this).val();
					}
				});
				$('.'+id+'s').each(function(e)
				{
					$(this).hide();
				});
				if($('#'+id+'_'+checked))
				{
					$('#'+id+'_'+checked).show();
				}
			}
		</script>

		<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"exporttype\" value=\"0\" {$mycode_checked[0]} class=\"mycodes_check\" onclick=\"checkAction('mycode');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_mycodes}</strong></label></dt>
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"exporttype\" value=\"1\" {$mycode_checked[1]} class=\"mycodes_check\" onclick=\"checkAction('mycode');\" style=\"vertical-align: middle;\" /> <strong>{$lang->enabled_mycodes}</strong></label></dt>
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"exporttype\" value=\"2\" {$mycode_checked[2]} class=\"mycodes_check\" onclick=\"checkAction('mycode');\" style=\"vertical-align: middle;\" /> <strong>{$lang->disabled_mycodes}</strong></label></dt>
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"exporttype\" value=\"3\" {$mycode_checked[3]} class=\"mycodes_check\" onclick=\"checkAction('mycode');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_mycodes}</strong></label></dt>
			<dd style=\"margin-top: 4px;\" id=\"mycode_3\" class=\"mycodes\">
				<table cellpadding=\"4\">
					<tr>
						<td valign=\"top\"><small>{$lang->mycodes_colon}</small></td>
						<td>".$form->generate_select_box('mycode_1_mycodes[]', $mycodes, $mybb->input['mycode_1_mycodes'], array('multiple' => true, 'size' => 5))."</td>
					</tr>
				</table>
			</dd>
		</dl>
		<script type=\"text/javascript\">
			checkAction('mycode');
		</script>";

		$form_container->output_row($lang->export_type." <em>*</em>", '', $type_select);
		$form_container->output_row($lang->file_name.' <em>*</em>', $lang->file_name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');

		$form_container->end();

		$buttons[] = $form->generate_submit_button($lang->export_mycode);

		$form->output_submit_wrapper($buttons);
		$form->end();

		$page->output_footer();
	}

	if($mybb->input['action'] == "import")
	{
		if($mybb->request_method == "post")
		{
			if(!is_uploaded_file($_FILES['mycodefile']['tmp_name']))
			{
				$errors[] = $lang->error_missing_file;
			}

			$ext = get_extension(my_strtolower($_FILES['mycodefile']['name']));
			if(!preg_match("#^(xml)$#i", $ext)) 
			{
				$errors[] = $lang->error_invalid_extension;
			}

			if(!$errors)
			{

				require_once MYBB_ROOT."inc/class_xml.php";

				$contents = @file_get_contents($_FILES['mycodefile']['tmp_name']);
				// Delete the temporary file if possible
				@unlink($_FILES['mycodefile']['tmp_name']);

				$parser = new XMLParser($contents);
				$parser->collapse_dups = 0;
				$tree = $parser->get_tree();

				foreach($tree['mycodes'][0]['mycode'] as $mycode)
				{
					$new_mycode = array(
						'title' => $db->escape_string($mycode['title'][0]['value']),
						'description' => $db->escape_string($mycode['description'][0]['value']),
						'regex' => $db->escape_string($mycode['regex'][0]['value']),
						'replacement' => $db->escape_string($mycode['replacement'][0]['value']),
						'active' => $db->escape_string($mycode['active'][0]['value']),
						'parseorder' => $db->escape_string($mycode['parseorder'][0]['value'])
					);

					$db->insert_query("mycode", $new_mycode);
				}

				$cache->update_mycode();

				// Log admin action
				log_admin_action();

				flash_message($lang->success_imported_mycode, 'success');
				admin_redirect('index.php?module=config-mycode');
			}
		}

		$sub_tabs['mycode'] = array(
			'title'	=> $lang->mycode,
			'link' => "index.php?module=config-mycode",
			'description' => $lang->mycode_desc
		);

		$sub_tabs['add_new_mycode'] = array(
			'title'	=> $lang->add_new_mycode,
			'link' => "index.php?module=config-mycode&amp;action=add",
			'description' => $lang->add_new_mycode_desc
		);

		$page->add_breadcrumb_item($lang->import_mycode);
		$page->output_header($lang->custom_mycode." - ".$lang->import_mycode);
		$page->output_nav_tabs($sub_tabs, 'import_mycode');

		$form = new Form("index.php?module=config-mycode&amp;action=import", "post", false, true);

		if($errors)
		{
			$page->output_inline_error($errors);
		}

		$form_container = new FormContainer($lang->import_mycode);
		$form_container->output_row($lang->mycode_file."<em>*</em>", $lang->mycode_file_desc, $form->generate_file_upload_box("mycodefile", array('id' => 'mycodefile')), 'mycodefile');
		$form_container->end();

		$buttons[] = $form->generate_submit_button($lang->import_mycode);

		$form->output_submit_wrapper($buttons);
		$form->end();

		$page->output_footer();
	}
}

// Add nav tabs
function importexportmycodes_tabs(&$tabs)
{
	global $lang;
	$lang->load("importexportmycodes", true);

	if($tabs['add_new_mycode'])
	{
		$tabs['import_mycode'] = array(
			'title'	=> $lang->import_mycode,
			'link' => "index.php?module=config-mycode&amp;action=import",
			'description' => $lang->import_mycode_desc
		);

		$tabs['export_mycode'] = array(
			'title'	=> $lang->export_mycode,
			'link' => "index.php?module=config-mycode&amp;action=export",
			'description' => $lang->export_mycode_desc
		);
	}
}

// Admin Log display
function importexportmycodes_adminlog($plugin_array)
{
	global $lang;
	$lang->load("importexportmycodes", true);

	return $plugin_array;
}
?>