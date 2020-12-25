<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\controller\acp;

use Symfony\Component\DependencyInjection\Container;

/**
* @package acp
*/
class acp_permissions_controller implements acp_permissions_interface
{
	public $u_action;
	public $db;
	public $user;
	public $auth;
	public $phpEx;
	public $phpbb_extension_manager;
	public $phpbb_container;
	public $phpbb_path_helper;
	public $phpbb_log;

	public $config;
	public $helper;
	public $language;
	public $request;
	public $template;

	public $ext_path;
	public $ext_path_web;
	public $ext_path_ajax;

	protected $dlext_extra;
	protected $dlext_main;

	/*
	 * @param string								$phpEx
	 * @param Container 							$phpbb_container
	 * @param \phpbb\extension\manager				$phpbb_extension_manager
	 * @param \phpbb\path_helper					$phpbb_path_helper
	 * @param \phpbb\db\driver\driver_interfacer	$db
	 * @param \phpbb\log\log_interface 				$log
	 * @param \phpbb\auth\auth						$auth
	 * @param \phpbb\user							$user
	 */
	public function __construct(
		$phpEx,
		Container $phpbb_container,
		\phpbb\extension\manager $phpbb_extension_manager,
		\phpbb\path_helper $phpbb_path_helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		\phpbb\auth\auth $auth,
		\phpbb\user $user,
		$dlext_extra,
		$dlext_main
	)
	{
		$this->phpEx					= $phpEx;
		$this->phpbb_container			= $phpbb_container;
		$this->phpbb_extension_manager	= $phpbb_extension_manager;
		$this->phpbb_path_helper		= $phpbb_path_helper;
		$this->db						= $db;
		$this->phpbb_log				= $log;
		$this->auth						= $auth;
		$this->user						= $user;

		$this->config					= $this->phpbb_container->get('config');
		$this->helper					= $this->phpbb_container->get('controller.helper');
		$this->language					= $this->phpbb_container->get('language');
		$this->request					= $this->phpbb_container->get('request');
		$this->template					= $this->phpbb_container->get('template');

		$this->ext_path					= $this->phpbb_extension_manager->get_extension_path('oxpus/dlext', true);
		$this->ext_path_web				= $this->phpbb_path_helper->update_web_root_path($this->ext_path);
		$this->ext_path_ajax			= $this->ext_path_web . 'assets/javascript/';

		$this->dlext_extra				= $dlext_extra;
		$this->dlext_main				= $dlext_main;
	}

	public function set_action($u_action)
	{
		$this->u_action = $u_action;
	}

	public function handle()
	{
		$this->auth->acl($this->user->data);
		if (!$this->auth->acl_get('a_dl_permissions'))
		{
			trigger_error('DL_NO_PERMISSION', E_USER_WARNING);
		}

		include_once($this->ext_path . 'phpbb/includes/acm_init.' . $this->phpEx);

		if ($cancel)
		{
			$action = '';
		}

		$s_hidden_fields = [];

		$index = [];
		$index = $this->dlext_main->full_index();

		if (empty($index))
		{
			redirect($this->u_action . '&amp;mode=categories');
		}

		$cat_id = (isset($s_presel_cats[0])) ? $s_presel_cats[0] : [];

		if ($view_perm > 1)
		{
			$cat_list = '';
			$s_hidden_fields += ['view_perm' => $view_perm];

			if ($view_perm == 2 && $cat_id)
			{
				for ($i = 0; $i < count($s_presel_cats); ++$i)
				{
					$cat_list .= $index[$s_presel_cats[$i]]['cat_name'] . '<br />';
					$s_hidden_fields += ['cat_select[' . $i . ']' => $s_presel_cats[$i]];
				}
			}

			if (confirm_box(true))
			{
				if ($view_perm == 2)
				{
					$cat_ids = [];

					for ($i = 0; $i < count($s_presel_cats); ++$i)
					{
						$cat_ids[] = $s_presel_cats[$i];
					}

					$sql = 'DELETE FROM ' . DL_AUTH_TABLE . '
						WHERE ' . $this->db->sql_in_set('cat_id', $cat_ids);
					$this->db->sql_query($sql);

					$sql = 'UPDATE ' . DL_CAT_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
						'auth_view'		=> 0,
						'auth_dl'		=> 0,
						'auth_up'		=> 0,
						'auth_mod'		=> 0,
						'auth_cread'	=> 3,
						'auth_cpost'	=> 3]) . ' WHERE ' . $this->db->sql_in_set('id', $cat_ids);
					$this->db->sql_query($sql);

					$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'DL_LOG_CAT_PERM_DROP', false, [$cat_list]);
				}
				else
				{
					$sql = 'DELETE FROM ' . DL_AUTH_TABLE;
					$this->db->sql_query($sql);

					$sql = 'UPDATE ' . DL_CAT_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
						'auth_view'		=> 0,
						'auth_dl'		=> 0,
						'auth_up'		=> 0,
						'auth_mod'		=> 0,
						'auth_cread'	=> 3,
						'auth_cpost'	=> 3]);
					$this->db->sql_query($sql);

					$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'DL_LOG_CAT_PERM_ALL');
				}

				// Purge the auth cache
				@unlink(DL_EXT_CACHE_PATH . 'data_dl_auth.' . $this->phpEx);
				@unlink(DL_EXT_CACHE_PATH . 'data_dl_cats.' . $this->phpEx);
			}
			else
			{
				$confirm_text = ($view_perm == 2) ? $this->language->lang('DL_PERM_CATS_DROP_CONFIRM', $cat_list) : $this->language->lang('DL_PERM_ALL_DROP_CONFIRM');

				confirm_box(false, $confirm_text, build_hidden_fields($s_hidden_fields));
			}

			if ($cancel)
			{
				$message = $this->language->lang('DL_PERM_DROP_ABORTED') . '<br /><br />' . $this->language->lang('CLICK_RETURN_DOWNLOADADMIN', '<a href="' . $this->u_action . '">', '</a>') . adm_back_link($this->u_action);
			}
			else
			{
				$message = $this->language->lang('DL_PERM_DROP') . '<br /><br />' . $this->language->lang('CLICK_RETURN_DOWNLOADADMIN', '<a href="' . $this->u_action . '">', '</a>') . adm_back_link($this->u_action);
			}

			trigger_error($message);
		}

		if ($view_perm == 1)
		{
			if (isset($s_presel_cats[0]))
			{
				$sql = 'SELECT a.*, g.group_name, g.group_type FROM ' . DL_AUTH_TABLE . ' a, ' . GROUPS_TABLE . ' g
					WHERE a.cat_id = ' . (int) $cat_id . '
						AND a.group_id = g.group_id
					ORDER BY g.group_type DESC, g.group_name';
				$result = $this->db->sql_query($sql);

				$this->template->assign_var('S_SHOW_PERMS', true);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$auth_view	= ($row['auth_view']) ? '<strong>' . $this->language->lang('YES') . '</strong>' : $this->language->lang('NO');
					$auth_dl	= ($row['auth_dl']) ? '<strong>' . $this->language->lang('YES') . '</strong>' : $this->language->lang('NO');
					$auth_up	= ($row['auth_up']) ? '<strong>' . $this->language->lang('YES') . '</strong>' : $this->language->lang('NO');
					$auth_mod	= ($row['auth_mod']) ? '<strong>' . $this->language->lang('YES') . '</strong>' : $this->language->lang('NO');

					$group_name = ($row['group_type'] == GROUP_SPECIAL) ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'];
					$group_sep = ($row['group_type'] == GROUP_SPECIAL) ? true : false;

					$this->template->assign_block_vars('perm_row', [
						'GROUP_NAME'	=> $group_name,
						'GROUP_SEP'		=> $group_sep,
						'AUTH_VIEW'		=> $auth_view,
						'AUTH_DL'		=> $auth_dl,
						'AUTH_UP'		=> $auth_up,
						'AUTH_MOD'		=> $auth_mod,
					]);
				}

				$this->db->sql_freeresult($result);

				switch ($index[$cat_id]['auth_cread'])
				{
					case 0:
						$l_auth_cread = $this->language->lang('DL_STAT_PERM_ALL');
					break;
					case 1:
						$l_auth_cread = $this->language->lang('DL_STAT_PERM_USER');
					break;
					case 2:
						$l_auth_cread = $this->language->lang('DL_STAT_PERM_MOD');
					break;
					case 3:
						$l_auth_cread = $this->language->lang('DL_STAT_PERM_ADMIN');
					break;
				}

				switch ($index[$cat_id]['auth_cpost'])
				{
					case 0:
						$l_auth_cpost = $this->language->lang('DL_STAT_PERM_ALL');
					break;
					case 1:
						$l_auth_cpost = $this->language->lang('DL_STAT_PERM_USER');
					break;
					case 2:
						$l_auth_cpost = $this->language->lang('DL_STAT_PERM_MOD');
					break;
					case 3:
						$l_auth_cpost = $this->language->lang('DL_STAT_PERM_ADMIN');
					break;
				}

				switch ($index[$cat_id]['auth_view_real'])
				{
					case 1:
						$l_auth_view = $this->language->lang('DL_PERM_ALL');
					break;
					case 2:
						$l_auth_view = $this->language->lang('DL_PERM_REG');
					break;
					default:
						$l_auth_view = $this->language->lang('DL_PERM_GRG');
				}

				switch ($index[$cat_id]['auth_dl_real'])
				{
					case 1:
						$l_auth_dl = $this->language->lang('DL_PERM_ALL');
					break;
					case 2:
						$l_auth_dl = $this->language->lang('DL_PERM_REG');
					break;
					default:
						$l_auth_dl = $this->language->lang('DL_PERM_GRG');
				}

				switch ($index[$cat_id]['auth_up_real'])
				{
					case 1:
						$l_auth_up = $this->language->lang('DL_PERM_ALL');
					break;
					case 2:
						$l_auth_up = $this->language->lang('DL_PERM_REG');
					break;
					default:
						$l_auth_up = $this->language->lang('DL_PERM_GRG');
				}

				switch ($index[$cat_id]['auth_mod_real'])
				{
					case 1:
						$l_auth_mod = $this->language->lang('DL_PERM_ALL');
					break;
					case 2:
						$l_auth_mod = $this->language->lang('DL_PERM_REG');
					break;
					default:
						$l_auth_mod = $this->language->lang('DL_PERM_GRG');
				}

				$this->template->assign_vars([
					'AUTH_VIEW'		=> $l_auth_view,
					'AUTH_DL'		=> $l_auth_dl,
					'AUTH_UP'		=> $l_auth_up,
					'AUTH_MOD'		=> $l_auth_mod,
					'AUTH_CREAD'	=> $l_auth_cread,
					'AUTH_CPOST'	=> $l_auth_cpost,
				]);
			}
			else
			{
				$view_perm = false;
			}
		}
		else
		{
			$view_perm = false;
		}

		if ($action == 'save_perm')
		{
			if (!check_form_key('dl_adm_perm'))
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}

			switch ($auth_view)
			{
				case 1:
					$log_auth_view = $this->language->lang('DL_PERM_ALL');
				break;
				case 2:
					$log_auth_view = $this->language->lang('DL_PERM_REG');
				break;
				default:
					$log_auth_view = $this->language->lang('DL_PERM_GRG');
				break;
			}

			switch ($auth_dl)
			{
				case 1:
					$log_auth_dl = $this->language->lang('DL_PERM_ALL');
				break;
				case 2:
					$log_auth_dl = $this->language->lang('DL_PERM_REG');
				break;
				default:
					$log_auth_dl = $this->language->lang('DL_PERM_GRG');
				break;
			}

			switch ($auth_up)
			{
				case 1:
					$log_auth_up = $this->language->lang('DL_PERM_ALL');
				break;
				case 2:
					$log_auth_up = $this->language->lang('DL_PERM_REG');
				break;
				default:
					$log_auth_up = $this->language->lang('DL_PERM_GRG');
				break;
			}

			switch ($auth_mod)
			{
				case 1:
					$log_auth_mod = $this->language->lang('DL_PERM_ALL');
				break;
				case 2:
					$log_auth_mod = $this->language->lang('DL_PERM_REG');
				break;
				default:
					$log_auth_mod = $this->language->lang('DL_PERM_GRG');
				break;
			}

			switch ($auth_cread)
			{
				case 1:
					$log_auth_cread = $this->language->lang('DL_STAT_PERM_USER');
				break;
				case 2:
					$log_auth_cread = $this->language->lang('DL_STAT_PERM_MOD');
				break;
				case 3:
					$log_auth_cread = $this->language->lang('DL_STAT_PERM_ADMIN');
				break;
				default:
					$log_auth_cread = $this->language->lang('DL_STAT_PERM_ALL');
					$auth_cread = 0;
				break;
			}

			switch ($auth_cpost)
			{
				case 1:
					$log_auth_cpost = $this->language->lang('DL_STAT_PERM_USER');
				break;
				case 2:
					$log_auth_cpost = $this->language->lang('DL_STAT_PERM_MOD');
				break;
				case 3:
					$log_auth_cpost = $this->language->lang('DL_STAT_PERM_ADMIN');
				break;
				default:
					$log_auth_cpost = $this->language->lang('DL_STAT_PERM_ALL');
					$auth_cpost = 0;
				break;
			}

			if (isset($s_presel_groups[0]) && $s_presel_groups[0] == -1)
			{
				for ($i = 0; $i < count($s_presel_cats); ++$i)
				{
					$sql = 'SELECT cat_name FROM ' . DL_CAT_TABLE . '
						WHERE id = ' . (int) $s_presel_cats[$i];
					$result = $this->db->sql_query($sql);
					$cat_name = $this->db->sql_fetchfield('cat_name');
					$this->db->sql_freeresult($result);

					$sql = 'UPDATE ' . DL_CAT_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
						'auth_view'		=> $auth_view,
						'auth_dl'		=> $auth_dl,
						'auth_up'		=> $auth_up,
						'auth_mod'		=> $auth_mod,
						'auth_cread'	=> $auth_cread,
						'auth_cpost'	=> $auth_cpost]) . ' WHERE id = ' . (int) $s_presel_cats[$i];
					$this->db->sql_query($sql);

					$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'DL_LOG_CAT_PERM_ALL', false, [$cat_name, $log_auth_view, $log_auth_dl, $log_auth_up, $log_auth_mod, $log_auth_cread, $log_auth_cpost]);
				}
			}
			else
			{
				for ($i = 0; $i < count($s_presel_cats); ++$i)
				{
					$sql = 'SELECT cat_name FROM ' . DL_CAT_TABLE . '
						WHERE id = ' . (int) $s_presel_cats[$i];
					$result = $this->db->sql_query($sql);
					$cat_name = $this->db->sql_fetchfield('cat_name');
					$this->db->sql_freeresult($result);

					for ($j = 0; $j < count($s_presel_groups); ++$j)
					{
						$sql = 'DELETE FROM ' . DL_AUTH_TABLE . '
							WHERE cat_id = ' . (int) $s_presel_cats[$i] . '
								AND group_id = ' . (int) $s_presel_groups[$j];
						$this->db->sql_query($sql);

						$sql = 'INSERT INTO ' . DL_AUTH_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
							'cat_id'	=> $s_presel_cats[$i],
							'group_id'	=> $s_presel_groups[$j],
							'auth_view'	=> $auth_view,
							'auth_dl'	=> $auth_dl,
							'auth_up'	=> $auth_up,
							'auth_mod'	=> $auth_mod]);
						$this->db->sql_query($sql);

						$sql = 'SELECT group_type, group_name FROM ' . GROUPS_TABLE . '
								WHERE group_id = ' . (int) $s_presel_groups[$j];
						$result = $this->db->sql_query($sql);
						$row = $this->db->sql_fetchrow($result);
						$this->db->sql_freeresult($result);
						$group_name = ($row['group_type'] == GROUP_SPECIAL) ? '<strong>' . $this->language->lang('G_' . $row['group_name']) . '</strong>' : $row['group_name'];

						$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'DL_LOG_CAT_PERM_GRP', false, [$cat_name, $group_name, $log_auth_view, $log_auth_dl, $log_auth_up, $log_auth_mod]);
					}
				}
			}

			// Purge the auth cache
			@unlink(DL_EXT_CACHE_PATH . 'data_dl_auth.' . $this->phpEx);
			@unlink(DL_EXT_CACHE_PATH . 'data_dl_cats.' . $this->phpEx);

			$message = $this->language->lang('CLICK_RETURN_DOWNLOADADMIN', '<a href="' . $this->u_action . '">', '</a>') . adm_back_link($this->u_action);

			trigger_error($message);
		}

		$s_group_select = '';

		if (!empty($s_presel_cats))
		{
			$this->template->assign_var('S_GROUP_SELECT', true);

			for ($i = 0; $i < count($s_presel_cats); ++$i)
			{
				if ($s_presel_cats[$i] <> -1)
				{
					$this->template->assign_block_vars('preselected_cats', [
						'CAT_TITLE' => $index[$s_presel_cats[$i]]['cat_name'],
					]);
		
					$s_hidden_fields += ['cat_select[' . $i . ']' => $s_presel_cats[$i]];
				}
			}

			if (!$view_perm)
			{
				$sql = 'SELECT group_id, group_name, group_type FROM ' . GROUPS_TABLE . '
						ORDER BY group_type DESC, group_name';
				$result = $this->db->sql_query($sql);

				$total_groups = $this->db->sql_affectedrows($result);

				if ($total_groups)
				{
					if ($total_groups < 7)
					{
						$size = $total_groups + 3;
					}
					else
					{
						$size = 10;
					}

					$s_group_select .= '<select name="group_select[]" multiple="multiple" size="' . $size . '" class="selectbox">';
					$s_group_select .= '<optgroup label="' . $this->language->lang('DL_PERMISSIONS_ALL') . '">';
					$s_group_select .= '<option value="-1">' . $this->language->lang('DL_ALL') . '</option>';
					$s_group_select .= '</optgroup>';
					$s_group_select .= '<optgroup label="' . $this->language->lang('USERGROUPS') . '">';

					$group_data = [];
					$group_sepr = [];

					while ($row = $this->db->sql_fetchrow($result))
					{
						$group_id = $row['group_id'];
						$group_name = ($row['group_type'] == GROUP_SPECIAL) ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'];
						$group_sep = ($row['group_type'] == GROUP_SPECIAL) ? ' class="sep"' : '';

						$group_data[$group_id] = $group_name;
						$group_sepr[$group_id] = $group_sep;

						if (in_array($group_id, $s_presel_groups) && (isset($s_presel_groups[0]) && $s_presel_groups[0] != -1))
						{
							$s_group_select .= '<option value="' . $group_id . '" selected="selected"' . $group_sep . '>' . $group_name . '</option>';
						}
						else
						{
							$s_group_select .= '<option value="' . $group_id . '"' . $group_sep . '>' . $group_name . '</option>';
						}
					}

					$s_group_select .= '</optgroup></select>';

					$s_group_select = (isset($s_presel_groups[0]) && $s_presel_groups[0] == -1) ? str_replace('value="-1">', 'value="-1" selected="selected">', $s_group_select) : $s_group_select;
				}

				if (!empty($s_presel_groups))
				{
					add_form_key('dl_adm_perm');

					for ($i = 0; $i < count($s_presel_groups); ++$i)
					{
						if ($s_presel_groups[$i] <> -1)
						{
							$group_name = $group_data[$s_presel_groups[$i]];
							$group_sep = $group_sepr[$s_presel_groups[$i]];
						}
						else
						{
							$group_name = $this->language->lang('DL_ALL');
							$group_sep = '';
						}

						$this->template->assign_block_vars('preselected_groups', [
							'GROUP_NAME'	=> $group_name,
							'GROUP_SEP'		=> $group_sep,
						]);

						$s_hidden_fields += ['group_select[' . $i . ']' => $s_presel_groups[$i]];
					}

					$s_hidden_fields += ['action' => 'save_perm'];

					if ($s_presel_groups[0] == -1)
					{
						$s_auth_view = '<select name="auth_view">';
						$s_auth_dl = '<select name="auth_dl">';
						$s_auth_up = '<select name="auth_up">';
						$s_auth_mod = '<select name="auth_mod">';

						$s_auth_all = '<option value="-1">' . $this->language->lang('SELECT_OPTION') . '</option>';
						$s_auth_all .= '<option value="1">'.$this->language->lang('DL_PERM_ALL').'</option>';
						$s_auth_all .= '<option value="2">'.$this->language->lang('DL_PERM_REG').'</option>';
						$s_auth_all .= '<option value="0">'.$this->language->lang('DL_PERM_GRG').'</option>';
						$s_auth_all .= '</select>';

						$s_auth_view .= $s_auth_all;
						$s_auth_dl .= $s_auth_all;
						$s_auth_up .= $s_auth_all;
						$s_auth_mod .= $s_auth_all;

						$s_auth_cread = '<select name="auth_cread">';
						$s_auth_cpost = '<select name="auth_cpost">';

						$s_auth_all = '<option value="-1">' . $this->language->lang('SELECT_OPTION') . '</option>';
						$s_auth_all .= '<option value="0">' . $this->language->lang('DL_STAT_PERM_ALL') . '</option>';
						$s_auth_all .= '<option value="1">' . $this->language->lang('DL_STAT_PERM_USER') . '</option>';
						$s_auth_all .= '<option value="2">' . $this->language->lang('DL_STAT_PERM_MOD') . '</option>';
						$s_auth_all .= '<option value="3">' . $this->language->lang('DL_STAT_PERM_ADMIN') . '</option>';
						$s_auth_all .= '</select>';

						$s_auth_cread .= $s_auth_all;
						$s_auth_cpost .= $s_auth_all;

						if (count($s_presel_cats) == 1)
						{
							$s_cat_auth_view	= $index[$s_presel_cats[0]]['auth_view_real'];
							$s_cat_auth_dl		= $index[$s_presel_cats[0]]['auth_dl_real'];
							$s_cat_auth_up		= $index[$s_presel_cats[0]]['auth_up_real'];
							$s_cat_auth_mod		= $index[$s_presel_cats[0]]['auth_mod_real'];
							$s_cat_auth_cread	= $index[$s_presel_cats[0]]['auth_cread'];
							$s_cat_auth_cpost	= $index[$s_presel_cats[0]]['auth_cpost'];

							$s_auth_view = str_replace('value="' . $s_cat_auth_view . '">', 'value="' . $s_cat_auth_view . '" selected="selected">', $s_auth_view);
							$s_auth_dl = str_replace('value="' . $s_cat_auth_dl . '">', 'value="' . $s_cat_auth_dl . '" selected="selected">', $s_auth_dl);
							$s_auth_up = str_replace('value="' . $s_cat_auth_up . '">', 'value="' . $s_cat_auth_up . '" selected="selected">', $s_auth_up);
							$s_auth_mod = str_replace('value="' . $s_cat_auth_mod . '">', 'value="' . $s_cat_auth_mod . '" selected="selected">', $s_auth_mod);
							$s_auth_cread = str_replace('value="' . $s_cat_auth_cread . '">', 'value="' . $s_cat_auth_cread . '" selected="selected">', $s_auth_cread);
							$s_auth_cpost = str_replace('value="' . $s_cat_auth_cpost . '">', 'value="' . $s_cat_auth_cpost . '" selected="selected">', $s_auth_cpost);
						}

						$this->template->assign_var('S_AUTH_ALL_USERS', true);
						$this->template->assign_vars([
							'L_AUTH_EXPL'	=> (count($s_presel_cats) == 1) ? $this->language->lang('DL_AUTH_SINGLE_EXPLAIN') : $this->language->lang('DL_AUTH_MULTI_EXPLAIN'),
							'L_OPTIONS'		=> $this->language->lang('SELECT_OPTION'),
							'S_AUTH_VIEW'	=> $s_auth_view,
							'S_AUTH_DL'		=> $s_auth_dl,
							'S_AUTH_UP'		=> $s_auth_up,
							'S_AUTH_MOD'	=> $s_auth_mod,
							'S_AUTH_CREAD'	=> $s_auth_cread,
							'S_AUTH_CPOST'	=> $s_auth_cpost,
						]);
					}
					else
					{
						$this->template->assign_var('S_AUTH_GROUPS', true);

						if ($s_presel_cats[0] != -1 && $s_presel_groups[0] != -1)
						{
							$sql = 'SELECT auth_view, auth_dl, auth_up, auth_mod FROM ' . DL_AUTH_TABLE . '
								WHERE ' . $this->db->sql_in_set('cat_id', $s_presel_cats) . '
									AND ' . $this->db->sql_in_set('group_id', $s_presel_groups) . '
								GROUP BY auth_view, auth_dl, auth_up, auth_mod';
							$result = $this->db->sql_query($sql);

							$total_auths = $this->db->sql_affectedrows($result);

							if ($total_auths == 1)
							{
								while ($row = $this->db->sql_fetchrow($result))
								{
									$auth_view = $row['auth_view'];
									$auth_dl = $row['auth_dl'];
									$auth_up = $row['auth_up'];
									$auth_mod = $row['auth_mod'];
								}

								$this->template->assign_vars([
									'S_AUTH_VIEW_YES'	=> ($auth_view) ? 'checked="checked"' : '',
									'S_AUTH_VIEW_NO'	=> (!$auth_view) ? 'checked="checked"' : '',
									'S_AUTH_DL_YES'		=> ($auth_dl) ? 'checked="checked"' : '',
									'S_AUTH_DL_NO'		=> (!$auth_dl) ? 'checked="checked"' : '',
									'S_AUTH_UP_YES'		=> ($auth_up) ? 'checked="checked"' : '',
									'S_AUTH_UP_NO'		=> (!$auth_up) ? 'checked="checked"' : '',
									'S_AUTH_MOD_YES'	=> ($auth_mod) ? 'checked="checked"' : '',
									'S_AUTH_MOD_NO'		=> (!$auth_mod) ? 'checked="checked"' : '',
								]);
							}

							$this->db->sql_freeresult($result);
						}
					}
				}
			}
		}
		else
		{
			$this->template->assign_var('S_VIEW_PERM', true);
		}

		if (count($index) < 10)
		{
			$size = count($index);
		}
		else
		{
			$size = 10;
		}

		$s_cat_select = '<select name="cat_select[]" multiple="multiple" size="' . $size . '" class="selectbox">';
		$s_cat_select .= $this->dlext_extra->dl_cat_select(0, 0, $s_presel_cats);
		$s_cat_select .= '</select>';

		$this->template->assign_vars([
			'S_CAT_SELECT'		=> (isset($s_cat_select)) ? $s_cat_select : '',
			'S_GROUP_SELECT'	=> (isset($s_group_select)) ? $s_group_select : '',
			'S_HIDDEN_FIELDS'	=> (isset($s_hidden_fields) && !$view_perm) ? build_hidden_fields($s_hidden_fields) : '',
			'S_PERM_ACTION'		=> $this->u_action,

			'U_BACK'			=> (!empty($s_presel_cats)) ? $this->u_action : '',
		]);
	}
}
