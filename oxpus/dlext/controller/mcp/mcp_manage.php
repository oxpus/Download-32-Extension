<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\controller\mcp;

use Symfony\Component\DependencyInjection\Container;

class mcp_manage
{
	protected $u_action;

	/* @var string phpBB root path */
	protected $root_path;

	/* @var string phpEx */
	protected $php_ext;

	/* @var Container */
	protected $phpbb_container;

	/* @var \phpbb\extension\manager */
	protected $phpbb_extension_manager;

	/* @var \phpbb\path_helper */
	protected $phpbb_path_helper;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\request\request_interface */
	protected $request;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\language\language */
	protected $language;

	/** @var extension owned objects */
	protected $ext_path;
	protected $ext_path_web;
	protected $ext_path_ajax;

	protected $dlext_auth;
	protected $dlext_extra;
	protected $dlext_files;
	protected $dlext_main;
	protected $dlext_topic;
	protected $dlext_status;

	/**
	* Constructor
	*
	* @param string									$root_path
	* @param string									$php_ext
	* @param Container 								$phpbb_container
	* @param \phpbb\extension\manager				$phpbb_extension_manager
	* @param \phpbb\path_helper						$phpbb_path_helper
	* @param \phpbb\db\driver\driver_interfacer		$db
	* @param \phpbb\config\config					$config
	* @param \phpbb\controller\helper				$helper
	* @param \phpbb\auth\auth						$auth
	* @param \phpbb\request\request_interface 		$request
	* @param \phpbb\template\template				$template
	* @param \phpbb\user							$user
	* @param \phpbb\language\language				$language
	*/
	public function __construct(
		$root_path,
		$php_ext,
		Container $phpbb_container,
		\phpbb\extension\manager $phpbb_extension_manager,
		\phpbb\path_helper $phpbb_path_helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\auth\auth $auth,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\language\language $language,
		$dlext_auth,
		$dlext_extra,
		$dlext_files,
		$dlext_main,
		$dlext_topic,
		$dlext_status
	)
	{
		$this->root_path				= $root_path;
		$this->php_ext 					= $php_ext;
		$this->phpbb_container 			= $phpbb_container;
		$this->phpbb_extension_manager 	= $phpbb_extension_manager;
		$this->phpbb_path_helper		= $phpbb_path_helper;
		$this->db 						= $db;
		$this->config 					= $config;
		$this->helper 					= $helper;
		$this->auth						= $auth;
		$this->request					= $request;
		$this->template 				= $template;
		$this->user 					= $user;
		$this->language					= $language;

		$this->ext_path					= $this->phpbb_extension_manager->get_extension_path('oxpus/dlext', true);
		$this->ext_path_web				= $this->phpbb_path_helper->update_web_root_path($this->ext_path);
		$this->ext_path_ajax			= $this->ext_path_web . 'assets/javascript/';

		$this->dlext_auth				= $dlext_auth;
		$this->dlext_extra				= $dlext_extra;
		$this->dlext_files				= $dlext_files;
		$this->dlext_main				= $dlext_main;
		$this->dlext_topic				= $dlext_topic;
		$this->dlext_status				= $dlext_status;
	}

	public function set_action($u_action)
	{
		$this->u_action = $u_action;
	}

	public function handle()
	{
		$nav_view = 'modcp';

		// Include the default base init script
		include_once($this->ext_path . 'phpbb/includes/base_init.' . $this->php_ext);

		$action = ($delete) ? 'delete' : $action;
		$action = ($lock) ? 'lock' : $action;
		$action = ($cancel) ? '' : $action;

		if (($action == 'delete' || $delete) && $cancel && $modcp == 99)
		{
			redirect($this->u_action . '&amp;mode=mcp_approve');
		}

		$this->template->assign_var('S_DL_MCP', true);

		if (isset($index[$cat_id]['total']) && $index[$cat_id]['total'] && $cat_id)
		{
			$deny_modcp = false;

			$access_cat = array();
			$access_cat = $this->dlext_main->full_index(0, 0, 0, 2);

			$cat_auth = array();
			$cat_auth = $this->dlext_auth->dl_cat_auth($cat_id);
			
			if (sizeof($access_cat) || $this->auth->acl_get('a_'))
			{
				$deny_modcp = false;
			}
			
			if (isset($index[$cat_id]['auth_mod']) && $index[$cat_id]['auth_mod'])
			{
				$deny_modcp = false;
			}
			
			if ($cat_id && $cat_auth['auth_mod'])
			{
				$deny_modcp = false;
			}
			
			if ($action == 'delete')
			{
				$deny_modcp = false;
			}
			
			if ($action == 'sort')
			{
				$sort = true;
				$action = '';
			}

			if ($deny_modcp)
			{
				trigger_error($this->language->lang('DL_NO_PERMISSION'));
			}
			else
			{
				if ($action == 'move' && $new_cat && $cat_id)
				{
					if (!empty($dlo_id))
					{
						$new_path = $index[$new_cat]['cat_path'];
		
						$sql = 'SELECT dl_id, ver_real_file FROM ' . DL_VERSIONS_TABLE . '
							WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
						$result = $this->db->sql_query($sql);
		
						while ($row = $this->db->sql_fetchrow($result))
						{
							$real_ver_file[$row['dl_id']][] = $row['ver_real_file'];
						}
		
						$this->db->sql_freeresult($result);
		
						for ($i = 0; $i < sizeof($dlo_id); $i++)
						{
							$df_id = intval($dlo_id[$i]);
		
							$sql = 'SELECT c.path, d.real_file FROM ' . DOWNLOADS_TABLE . ' d, ' . DL_CAT_TABLE . ' c
								WHERE d.cat = c.id
									AND d.id = ' . (int) $df_id . '
									AND c.id = ' . (int) $cat_id . '
									AND d.extern = 0';
							$result = $this->db->sql_query($sql);
							$row = $this->db->sql_fetchrow($result);
		
							$old_path = $row['path'];
							$real_file = $row['real_file'];
		
							$this->db->sql_freeresult($result);
		
							if ($new_path != $old_path)
							{
								@copy(DL_EXT_FILEBASE_PATH. 'downloads/' . $old_path . $real_file, DL_EXT_FILEBASE_PATH. 'downloads/' . $new_path . $real_file);
								@chmod(DL_EXT_FILEBASE_PATH. 'downloads/' . $new_path . $real_file, 0777);
								@unlink(DL_EXT_FILEBASE_PATH. 'downloads/' . $old_path . $real_file);
		
								if (isset($real_ver_file[$df_id]))
								{
									for ($j = 0; $j < sizeof($real_ver_file[$df_id]); $j++)
									{
										@copy(DL_EXT_FILEBASE_PATH. 'downloads/' . $old_path . $real_ver_file[$df_id][$j], DL_EXT_FILEBASE_PATH. 'downloads/' . $new_path . $real_ver_file[$df_id][$j]);
										@chmod(DL_EXT_FILEBASE_PATH. 'downloads/' . $new_path . $real_ver_file[$df_id][$j], 0777);
										@unlink(DL_EXT_FILEBASE_PATH. 'downloads/' . $old_path . $real_ver_file[$df_id][$j]);
									}
								}
							}
						}
		
						$sql = 'UPDATE ' . DOWNLOADS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'cat' => $new_cat)) . ' WHERE ' . $this->db->sql_in_set('id', $dlo_id) . ' AND cat = ' . (int) $cat_id;
						$this->db->sql_query($sql);
		
						$sql = "UPDATE " . DL_STATS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'cat_id' => $new_cat)) . ' WHERE ' . $this->db->sql_in_set('id', $dlo_id) . ' AND cat_id = ' . (int) $cat_id;
						$this->db->sql_query($sql);
		
						$sql = "UPDATE " . DL_COMMENTS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'cat_id' => $new_cat)) . ' WHERE ' . $this->db->sql_in_set('id', $dlo_id) . ' AND cat_id = ' . (int) $cat_id;
						$this->db->sql_query($sql);
		
						// Purge the files cache
						@unlink(DL_EXT_CACHE_PATH . 'data_dl_cat_counts.' . $this->php_ext);
						@unlink(DL_EXT_CACHE_PATH . 'data_dl_file_p.' . $this->php_ext);
						@unlink(DL_EXT_CACHE_PATH . 'data_dl_file_preset.' . $this->php_ext);
					}
		
					$fmove = '';
					$action = '';
				}
		
				if ($action == 'lock')
				{
					if (!empty($dlo_id))
					{
						$sql = 'UPDATE ' . DOWNLOADS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'approve' => 0)) . ' WHERE ' . $this->db->sql_in_set('id', $dlo_id);
						$this->db->sql_query($sql);
					}
		
					$fmove = '';
					$action = '';
				}

				if ($action == 'assign')
				{
					$username	= $this->request->variable('username', '', true);
		
					if (!empty($dlo_id) && $username)
					{
						$sql = 'SELECT user_id FROM ' . USERS_TABLE . "
								WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";
						$result = $this->db->sql_query($sql);
						$user_id = (int) $this->db->sql_fetchfield('user_id');
						$this->db->sql_freeresult($result);
		
						if ($user_id)
						{
							$sql = 'UPDATE ' . DOWNLOADS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
								'add_user' => $user_id)) . ' WHERE ' . $this->db->sql_in_set('id', $dlo_id);
							$this->db->sql_query($sql);
						}
					}
		
					$fmove = '';
					$action = '';
				}
		
				if ($action == 'delete' && !empty($dlo_id))
				{
					if (confirm_box(true))
					{
						if ($del_file)
						{
							$sql = 'SELECT ver_id, dl_id, ver_real_file FROM ' . DL_VERSIONS_TABLE . '
								WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
							$result = $this->db->sql_query($sql);
	
							while ($row = $this->db->sql_fetchrow($result))
							{
								$real_ver_file[$row['dl_id']][] = $row['ver_real_file'];
							}
	
							$this->db->sql_freeresult($result);
						}
	
						$sql = 'SELECT c.path, d.cat, d.real_file, d.thumbnail, d.dl_topic, d.id AS df_id FROM ' . DL_CAT_TABLE . ' c, ' . DOWNLOADS_TABLE . ' d
							WHERE c.id = d.cat
								AND ' . $this->db->sql_in_set('d.id', $dlo_id);
						$result = $this->db->sql_query($sql);
	
						$dl_topics	= array();
						$dl_t_ids	= array();
	
						while ($row = $this->db->sql_fetchrow($result))
						{
							$cat_id = $row['cat'];
	
							if (!$this->auth->acl_get('a_') && isset($index[$cat_id]['auth_mod']) && !$index[$cat_id]['auth_mod'])
							{
								trigger_error($this->language->lang('DL_NO_PERMISSION') . __LINE__);
							}
	
							$cat_auth = array();
							$cat_auth = $this->dlext_auth->dl_cat_auth($cat_id);
	
							if (!$this->auth->acl_get('a_') && !$cat_auth['auth_mod'])
							{
								trigger_error($this->language->lang('DL_NO_PERMISSION') . __LINE__);
							}
	
							$path		= $row['path'];
							$real_file	= $row['real_file'];
							$df_id		= $row['df_id'];
	
							@unlink(DL_EXT_FILEBASE_PATH . 'thumbs/' . $row['thumbnail']);
	
							if ($del_file)
							{
								@unlink(DL_EXT_FILEBASE_PATH. 'downloads/' . $path . $real_file);
	
								if (isset($real_ver_file[$df_id]))
								{
									for ($i = 0; $i < sizeof($real_ver_file[$df_id]); $i++)
									{
										@unlink(DL_EXT_FILEBASE_PATH. 'downloads/' . $path . $real_ver_file[$df_id][$i]);
									}
								}
	
								$sql_real = 'SELECT file_type, real_name FROM ' . DL_VER_FILES_TABLE . '
									WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
								$result_real = $this->db->sql_query($sql_real);
	
								while ($row_real = $this->db->sql_fetchrow($result_real))
								{
									switch ($row_real['file_type'])
									{
										case 1:
											@unlink(DL_EXT_FILEBASE_PATH. 'version/images/' . $row_real['real_name']);
										break;
										default:
											@unlink(DL_EXT_FILEBASE_PATH. 'version/files/' . $row_real['real_name']);
									}
								}
	
								$this->db->sql_freeresult($result_real);
							}
	
							if ($row['dl_topic'])
							{
								$dl_topics[]		= $row['dl_topic'];
								$dl_t_ids[$df_id]	= $row['dl_topic'];
							}
						}

						$this->db->sql_freeresult($result);
	
						$topic_drop_mode = $this->request->variable('topic_drop_mode', 'drop');
	
						$return = $this->dlext_topic->delete_topic($dl_topics, $topic_drop_mode, $dl_t_ids);
	
						$sql = 'DELETE FROM ' . DOWNLOADS_TABLE . '
							WHERE ' . $this->db->sql_in_set('id', $dlo_id) . '
								AND cat = ' . (int) $cat_id;
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_STATS_TABLE . '
							WHERE ' . $this->db->sql_in_set('id', $dlo_id) . '
								AND cat_id = ' . (int) $cat_id;
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_COMMENTS_TABLE . '
							WHERE ' . $this->db->sql_in_set('id', $dlo_id) . '
								AND cat_id = ' . (int) $cat_id;
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_NOTRAF_TABLE . '
							WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_VERSIONS_TABLE . '
							WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_VER_FILES_TABLE . '
							WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_FIELDS_DATA_TABLE . '
							WHERE ' . $this->db->sql_in_set('df_id', $dlo_id);
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_RATING_TABLE . '
							WHERE ' . $this->db->sql_in_set('dl_id', $dlo_id);
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_FAVORITES_TABLE . '
							WHERE ' . $this->db->sql_in_set('fav_dl_id', $dlo_id);
						$this->db->sql_query($sql);
	
						// Purge the files cache
						@unlink(DL_EXT_CACHE_PATH . 'data_dl_cat_counts.' . $this->php_ext);
						@unlink(DL_EXT_CACHE_PATH . 'data_dl_file_p.' . $this->php_ext);
						@unlink(DL_EXT_CACHE_PATH . 'data_dl_file_preset.' . $this->php_ext);
					}
					else
					{
						$s_hidden_fields = array('action' => 'delete', 'cat_id' => $cat_id);

						if (sizeof($dlo_id) == 1)
						{
							$dl_file	= array();
							$dl_file	= $this->dlext_files->all_files(0, '', 'ASC', '', intval($dlo_id[0]), true, '*');
	
							$delete_confirm_text	= $this->language->lang('DL_CONFIRM_DELETE_SINGLE_FILE', $dl_file['description']);
							$s_hidden_fields = array_merge($s_hidden_fields, array('dlo_id[0]' => $dlo_id[0]));
						}
						else
						{
							$delete_confirm_text	= $this->language->lang('DL_CONFIRM_DELETE_MULTIPLE_FILES', sizeof($dlo_id));

							$i = 0;
							foreach($dlo_id as $key => $value)
							{
								$s_hidden_fields = array_merge($s_hidden_fields, array('dlo_id[' . $i . ']' => $value));
								$i++;
							}
						}
	
						$this->template->assign_var('S_DELETE_FILES_CONFIRM', true);
						$this->template->assign_var('S_DELETE_TOPIC_CONFIRM', true);

						confirm_box(false, $delete_confirm_text, build_hidden_fields($s_hidden_fields), 'dl_confirm_body.html');
					}

					$fmove = '';
					$action = '';
				}
		
				if ($fmove && ($this->auth->acl_get('a_') && $this->user->data['is_registered']))
				{
					if ($fmove == 'ABC')
					{
						$sql = 'SELECT id FROM ' . DOWNLOADS_TABLE . '
							WHERE cat = ' . (int) $cat_id . '
							ORDER BY description ASC';
						$result = $this->db->sql_query($sql);
					}
					else
					{
						$sql = 'SELECT sort FROM ' . DOWNLOADS_TABLE . '
							WHERE id = ' . (int) $df_id;
						$result = $this->db->sql_query($sql);
						$sort = $this->db->sql_fetchfield('sort');
						$this->db->sql_freeresult($result);
		
						$sql_move = ($fmove == 1) ? $sort + 15 : $sort - 15;
		
						$sql = 'UPDATE ' . DOWNLOADS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'sort' => $sql_move)) . ' WHERE id = ' . (int) $df_id;
						$this->db->sql_query($sql);
		
						$sql = 'SELECT id FROM ' . DOWNLOADS_TABLE . '
							WHERE cat = ' . (int) $cat_id . '
							ORDER BY sort ASC';
						$result = $this->db->sql_query($sql);
					}
		
					$i = 10;
		
					while($row = $this->db->sql_fetchrow($result))
					{
						$sql_sort = 'UPDATE ' . DOWNLOADS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
							'sort' => $i)) . ' WHERE id = ' . (int) $row['id'];
						$this->db->sql_query($sql_sort);
						$i += 10;
					}
		
					$this->db->sql_freeresult($result);

					$fmove = '';
				}
		
				$total_downloads = $index[$cat_id]['total'];

				if ($sort && ($this->auth->acl_get('a_') && $this->user->data['is_registered']))
				{
					$per_page	= $total_downloads;
					$start		= 0;

					$this->template->assign_var('S_MODCP_BUTTON', true);
				}
				else
				{
					$per_page = $this->config['dl_links_per_page'];

					if ($this->auth->acl_get('a_') && $this->user->data['is_registered'])
					{
						$this->template->assign_var('S_ORDER_BUTTON', true);
					}
				}

				if ($this->auth->acl_get('a_') && $this->user->data['is_registered'])
				{
					$this->template->assign_var('S_SORT_ASC', true);
				}

				$sql = 'SELECT d.id, d.description, d.desc_uid, d.desc_bitfield, d.desc_flags, u.username FROM ' . DOWNLOADS_TABLE . ' d
					LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = d.add_user)
					WHERE d.approve = ' . true . '
						AND d.cat = ' . (int) $cat_id . '
					ORDER BY d.cat, d.sort';
				$result = $this->db->sql_query_limit($sql, $per_page, $start);
				$max_downloads = $this->db->sql_affectedrows($result);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$description	= $row['description'];
					$desc_uid		= $row['desc_uid'];
					$desc_bitfield	= $row['desc_bitfield'];
					$desc_flags		= $row['desc_flags'];
					$username		= $row['username'];

					$description	= generate_text_for_display($description, $desc_uid, $desc_bitfield, $desc_flags);

					$file_id		= $row['id'];

					$mini_icon		= $this->dlext_status->mini_status_file($cat_id, $file_id);

					$this->template->assign_block_vars('manage_row', array(
						'FILE_ID'		=> $file_id,
						'MINI_ICON'		=> $mini_icon,
						'DESCRIPTION'	=> $description,
						'USERNAME'		=> $username,

						'U_UP'			=> ($this->auth->acl_get('a_') && $this->user->data['is_registered']) ? $this->u_action . '&amp;mode=mcp_manage&amp;fmove=-1&amp;sort=1&amp;df_id=' . $file_id . '&amp;cat_id=' . $cat_id : '',
						'U_DOWN'		=> ($this->auth->acl_get('a_') && $this->user->data['is_registered']) ? $this->u_action . '&amp;mode=mcp_manage&amp;fmove=1&amp;sort=1&amp;df_id=' . $file_id . '&amp;cat_id=' . $cat_id : '',
						'U_EDIT'		=> $this->u_action . '&amp;mode=mcp_edit&amp;df_id=' . $file_id . '&amp;cat_id=' . $cat_id,
						'U_DOWNLOAD'	=> $this->helper->route('oxpus_dlext_details', array('df_id' => $file_id)),
					));
				}
				$this->db->sql_freeresult($result);

				if (!isset($file_id))
				{
					$file_id = '';
				}

				$s_cat_select = '<select name="new_cat">';
				$s_cat_select .= $this->dlext_extra->dl_dropdown(0, 0, $cat_id, 'auth_view');
				$s_cat_select .= '</select>';

				$s_hidden_fields = array(
					'view'		=> 'modcp',
					'cat_id'	=> $cat_id,
					'start'		=> $start
				);

				$cat_name = $index[$cat_id]['cat_name'];
				$cat_name = str_replace('&nbsp;&nbsp;|', '', $cat_name);
				$cat_name = str_replace('___&nbsp;', '', $cat_name);

				if ($total_downloads > $per_page)
				{
					$pagination = $this->phpbb_container->get('pagination');
					$pagination->generate_template_pagination(
						$this->u_action . '&amp;mode=mcp_manage&amp;cat_id=' . $cat_id,
						'pagination',
						'start',
						$total_downloads,
						$per_page,
						$page_start
					);

					$this->template->assign_vars(array(
						'PAGE_NUMBER'	=> $pagination->on_page($total_downloads, $per_page, $page_start),
						'TOTAL_DL'		=> $this->language->lang('VIEW_DOWNLOADS', $total_downloads),
					));
				}

				$this->template->assign_vars(array(
					'DL_ABC'			=> ($this->auth->acl_get('a_') && $this->user->data['is_registered']) ? $this->language->lang('SORT_BY') . ' ASC' : '',
					'SORT'				=> $sort,
					'MAX_DOWNLOADS'		=> $max_downloads,

					'U_FIND_USERNAME'	=> append_sid("{$this->root_path}memberlist.{$this->php_ext}", 'mode=searchuser&amp;form=select_user&amp;field=username&amp;select_single=true'),
					'U_SORT_ASC'		=> ($this->auth->acl_get('a_') && $this->user->data['is_registered']) ? $this->u_action . '&amp;mode=mcp_manage&amp;fmove=ABC&amp;sort=' . (($sort) ? 1 : '') . '&amp;df_id=' . $file_id . '&amp;cat_id=' . $cat_id : '',
					'S_CAT_SELECT'		=> $s_cat_select,
					'S_DL_MODCP_ACTION'	=> $this->u_action . '&amp;mode=mcp_manage',
					'S_HIDDEN_FIELDS'	=> build_hidden_fields($s_hidden_fields))
				);
			}
		}

		$s_cat_select = '<form method="post" id="mcp_cat_select" action="' . $this->u_action . '&amp;mode=mcp_manage" onsubmit="if(this.options[this.selectedIndex].value == -1) { return false; }">';
		$s_cat_select .= "\n<fieldset>" . $this->language->lang('DL_GOTO') . $this->language->lang('COLON') . ' <select name="cat_id" onchange="if(this.options[this.selectedIndex].value != -1) { forms[\'mcp_cat_select\'].submit(); }">';
		$s_cat_select .= '<option value="-1">' . $this->language->lang('DL_CAT_NAME') . '</option>';
		$s_cat_select .= $this->dlext_extra->dl_dropdown(0, 0, $cat_id, 'auth_view', -1);
		$s_cat_select .= '</select>&nbsp;<input type="submit" value="' . $this->language->lang('GO') . '" class="button2" /></fieldset></form>';

		$this->template->assign_var('S_SELECT_MCP_CAT', $s_cat_select);
	}
}
