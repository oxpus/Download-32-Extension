<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\controller;

use Symfony\Component\DependencyInjection\Container;

class tracker
{
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

	protected $dlext_auth;
	protected $dlext_main;

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
		$dlext_main
	)
	{
		$this->root_path				= $root_path;
		$this->php_ext 					= $php_ext;
		$this->phpbb_container 			= $phpbb_container;
		$this->db 						= $db;
		$this->config 					= $config;
		$this->helper 					= $helper;
		$this->auth						= $auth;
		$this->request					= $request;
		$this->template 				= $template;
		$this->user 					= $user;
		$this->language					= $language;

		$this->ext_path					= $phpbb_extension_manager->get_extension_path('oxpus/dlext', true);
		$this->ext_path_web				= $phpbb_path_helper->update_web_root_path($this->ext_path);
		$this->ext_path_ajax			= $this->ext_path_web . 'assets/javascript/';

		$this->dlext_auth				= $dlext_auth;
		$this->dlext_main				= $dlext_main;
	}

	public function handle()
	{
		/*
		* open the bug tracker, if choosen and possible
		*/
		if ($this->user->data['is_registered'])
		{
			$nav_view = 'tracker';

			// Include the default base init script
			include_once($this->ext_path . 'phpbb/includes/base_init.' . $this->php_ext);

			// Delete notifications

			$bug_tracker = $this->dlext_auth->bug_tracker();

			if ($cancel)
			{
				$action = '';
			}

			if ($bug_tracker)
			{
				$notification = $this->phpbb_container->get('notification_manager');

				page_header($this->language->lang('DL_BUG_TRACKER'));

				/*
				* clean up bug tracker for unset categories
				* hard stuff to do this, but we must be sure to track downloads only in the choosen categories...
				*/
				$sql = 'SELECT d.id FROM ' . DL_CAT_TABLE . ' c, ' . DOWNLOADS_TABLE . ' d
					WHERE c.bug_tracker = 0
						AND c.id = d.cat';
				$result = $this->db->sql_query($sql);

				$dl_ids = [];

				while ($row = $this->db->sql_fetchrow($result))
				{
					$dl_ids[] = $row['id'];
				}
				$this->db->sql_freeresult($result);

				if (isset($fav_id) && $fav_id <> 0)
				{
					$sql = 'SELECT * FROM ' . DL_BUGS_TABLE . '
						WHERE report_id = ' . (int) $fav_id;
					$result = $this->db->sql_query($sql);
					$dl_exists = $this->db->sql_affectedrows($result);
					$this->db->sql_freeresult($result);

					if (!$dl_exists)
					{
						$fav_id = 0;
						$action = '';
					}
				}

				if (!empty($dl_ids))
				{
					$sql = 'SELECT report_id FROM ' . DL_BUGS_TABLE . '
							WHERE ' . $this->db->sql_in_set('df_id', $dl_ids);
					$result = $this->db->sql_query($sql);

					$item_ids = [];

					while ($row = $this->db->sql_fetchrow($result))
					{
						$item_ids[] = $row['report_id'];
					}
					$this->db->sql_freeresult($result);

					$sql = 'DELETE FROM ' . DL_BUGS_TABLE . '
						WHERE ' . $this->db->sql_in_set('df_id', $dl_ids);
					$this->db->sql_query($sql);

					$sql = 'DELETE FROM ' . DL_BUG_HISTORY_TABLE . '
						WHERE ' . $this->db->sql_in_set('df_id', $dl_ids);
					$this->db->sql_query($sql);

					if (!empty($item_ids))
					{
						$notification->delete_notifications([
							'oxpus.dlext.notification.type.bt_assign',
							'oxpus.dlext.notification.type.bt_status',
						], $item_ids);
					}

					unset($dl_ids);
					unset($item_ids);
				}

				/*
				* check the current user for mod permissions
				*/
				$bt_access_cats = [];
				$bt_access_cats = $this->dlext_main->full_index(0, 0, 0, 2);

				if ($this->user->data['is_registered'] && ($this->auth->acl_get('a_') || $this->auth->acl_get('m_')))
				{
					$allow_bug_mod = true;
				}
				else
				{
					if (!empty($bt_access_cats))
					{
						$allow_bug_mod = true;
					}
					else
					{
						$allow_bug_mod = false;
					}
				}

				/*
				* check the user permissions for all download categories
				*/
				$bug_access_cats = [];
				$bug_access_cats = $this->dlext_main->full_index(0, 0, 0, 1);

				$report_title		= $this->request->variable('report_title', '', true);
				$report_text		= $this->request->variable('message', '', true);
				$report_file_ver	= $this->request->variable('report_file_ver', '', true);
				$report_php			= $this->request->variable('report_php', '', true);
				$report_db			= $this->request->variable('report_db', '', true);
				$report_forum		= $this->request->variable('report_forum', '', true);
				$new_user_id		= $this->request->variable('user_assign', 0);

				$allow_bbcode	= ($this->config['allow_bbcode']) ? true : false;
				$allow_urls		= true;
				$allow_smilies	= ($this->config['allow_smilies']) ? true : false;
				$bug_uid		=
				$bug_bitfield	= '';
				$bug_flags		= 0;

				$error_txt = [];
				$error = false;

				if ($preview || $action == 'save')
				{
					if (!check_form_key('bt_tracker'))
					{
						$error_txt[] = $this->language->lang('FORM_INVALID');
						$error = true;
					}

					if (!$report_title)
					{
						$error_txt[] = $this->language->lang('DL_BUG_REPORT_NO_TITLE');
						$error = true;
					}

					if (!$report_text)
					{
						$error_txt[] = $this->language->lang('DL_BUG_REPORT_NO_TEXT');
						$error = true;
					}
				}
				else if ($action == 'status' || $action == 'assign')
				{
					if (!check_form_key('bt_tracker'))
					{
						$error_txt[] = $this->language->lang('FORM_INVALID');
						$error = true;
					}

					if ($action == 'assign' && !$new_user_id)
					{
						$error_txt[] = $this->language->lang('DL_NO_PERMISSIONS');
						$error = true;
					}
				}

				if ($error)
				{
					if ($fav_id)
					{
						$action = 'edit';
					}
					else
					{
						$action = 'add';
					}
				}

				if (!$error && $preview && $this->user->data['is_registered'])
				{
					$preview_title	= $report_title;
					$preview_text	= $report_text;

					generate_text_for_storage($preview_text, $bug_uid, $bug_bitfield, $bug_flags, $allow_bbcode, $allow_urls, $allow_smilies);
					$preview_text	= generate_text_for_display($preview_text, $bug_uid, $bug_bitfield, $bug_flags);

					$this->template->assign_var('S_PREVIEW', true);

					$this->template->assign_vars([
						'PREVIEW_TITLE'	=> $preview_title,
						'PREVIEW_TEXT'	=> $preview_text,
					]);

					$action = ($fav_id && $this->dlext_auth->user_admin()) ? 'edit' : 'add';
				}

				/*
				* save new or edited bug report
				*/
				if (!$error && $action == 'save' && $this->user->data['is_registered'])
				{
					generate_text_for_storage($report_text, $bug_uid, $bug_bitfield, $bug_flags, $allow_bbcode, $allow_urls, $allow_smilies);

					if ($fav_id && $this->dlext_auth->user_admin())
					{
						$sql = 'UPDATE ' . DL_BUGS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
							'df_id'					=> $df_id,
							'report_title'			=> $report_title,
							'report_text'			=> $report_text,
							'bug_uid'				=> $bug_uid,
							'bug_bitfield'			=> $bug_bitfield,
							'bug_flags'				=> $bug_flags,
							'report_file_ver'		=> $report_file_ver,
							'report_date'			=> time(),
							'report_author_id'		=> $this->user->data['user_id'],
							'report_status_date'	=> time(),
							'report_php'			=> $report_php,
							'report_db'				=> $report_db,
							'report_forum'			=> $report_forum]) . ' WHERE report_id = ' . (int) $fav_id;
						$this->db->sql_query($sql);
					}
					else
					{
						$sql = 'INSERT INTO ' . DL_BUGS_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
							'df_id'					=> $df_id,
							'report_title'			=> $report_title,
							'report_text'			=> $report_text,
							'bug_uid'				=> $bug_uid,
							'bug_bitfield'			=> $bug_bitfield,
							'bug_flags'				=> $bug_flags,
							'report_file_ver'		=> $report_file_ver,
							'report_date'			=> time(),
							'report_author_id'		=> $this->user->data['user_id'],
							'report_status_date'	=> time(),
							'report_php'			=> $report_php,
							'report_db'				=> $report_db,
							'report_forum'			=> $report_forum]);
						$this->db->sql_query($sql);

						$fav_id = $this->db->sql_nextid();

						$sql = 'INSERT INTO ' . DL_BUG_HISTORY_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
							'df_id'				=> $df_id,
							'report_id'			=> $fav_id,
							'report_his_type'	=> 'status',
							'report_his_date'	=>  time(),
							'report_his_value'	=> '0:' . $this->user->data['username']]);
						$this->db->sql_query($sql);
					}

					$link_array = ['df_id' => $df_id];

					if ($fav_id && $this->dlext_auth->user_admin())
					{
						$link_array += ['action' => 'detail', 'fav_id' => $fav_id];
					}

					$message = $this->language->lang('DL_BUG_REPORT_ADDED') . '<br /><br />' . $this->language->lang('CLICK_RETURN_BUG_TRACKER', '<a href="' . $this->helper->route('oxpus_dlext_tracker', $link_array) . '">', '</a>');

					trigger_error($message);
				}

				/*
				* add new status to report
				*/
				if (!$error && $action == 'status' && $fav_id && $allow_bug_mod)
				{
					$new_status			= $this->request->variable('new_status', '', true);
					$new_status_text	= $this->request->variable('new_status_text', '', true);
					$new_status_text	= str_replace(':', '', $new_status_text);

					$sql = 'SELECT b.df_id, b.report_status, b.report_author_id, b.report_title, d.cat FROM ' . DL_BUGS_TABLE . ' b, ' . DOWNLOADS_TABLE . ' d
						WHERE d.id = b.df_id
							AND b.report_id = ' . (int) $fav_id;
					$result = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($result);

					$df_id				= $row['df_id'];
					$cat_id				= $row['cat'];
					$report_status		= $row['report_status'];
					$report_author_id	= $row['report_author_id'];
					$report_title		= $row['report_title'];

					$this->db->sql_freeresult($result);

					$sql = 'INSERT INTO ' . DL_BUG_HISTORY_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
						'df_id'				=> $df_id,
						'report_id'			=> $fav_id,
						'report_his_type'	=> 'status',
						'report_his_date'	=> time(),
						'report_his_value'	=> $new_status . ':' . $this->user->data['username'] . ':' . $new_status_text]);
					$this->db->sql_query($sql);

					$sql = 'UPDATE ' . DL_BUGS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
						'report_status'			=> $new_status,
						'report_status_date'	=> time()]) . ' WHERE report_id = ' . (int) $fav_id;
					$this->db->sql_query($sql);

					// Send email to report author about new status if it will not be the current one
					if ($report_author_id <> $this->user->data['user_id'])
					{
						$notification_data = [
							'user_ids'			=> [$report_author_id],
							'status_text'		=> $new_status_text,
							'report_title'		=> $report_title,
							'report_status'		=> $report_status,
							'fav_id'			=> $fav_id,
						];

						$notification->add_notifications('oxpus.dlext.notification.type.bt_status', $notification_data);
					}

					$action = 'detail';
				}

				/*
				* assign bug report to team member
				*/
				if (!$error && $action == 'assign' && $df_id && $fav_id && $allow_bug_mod)
				{
					$sql = 'SELECT b.report_title, d.cat FROM ' . DL_BUGS_TABLE . ' b, ' . DOWNLOADS_TABLE . ' d
						WHERE d.id = b.df_id
						AND b.report_id = ' . (int) $fav_id;
					$result = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($result);

					$report_title	= $row['report_title'];
					$cat_id			= $row['cat'];

					$this->db->sql_freeresult($result);

					$sql = 'SELECT username FROM ' . USERS_TABLE . '
						WHERE user_id = ' . (int) $new_user_id;
					$result = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($result);

					$report_user = $row['username'];

					$this->db->sql_freeresult($result);

					$sql = 'INSERT INTO ' . DL_BUG_HISTORY_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
						'df_id'				=> $df_id,
						'report_id'			=> $fav_id,
						'report_his_type'	=> 'assign',
						'report_his_date'	=> time(),
						'report_his_value'	=> $new_user_id . ':' . $report_user]);
					$this->db->sql_query($sql);

					$sql = 'UPDATE ' . DL_BUGS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
						'report_assign_id'		=> $new_user_id,
						'report_assign_date'	=> time()]) . ' WHERE report_id = ' . (int) $fav_id;
					$this->db->sql_query($sql);

					// Send notification to new assigned user if it will not be the current one
					if ($new_user_id <> $this->user->data['user_id'])
					{
						$notification_data = [
							'user_ids'		=> [$new_user_id],
							'fav_id'		=> $fav_id,
							'report_title'	=> $report_title,
						];

						$notification->add_notifications('oxpus.dlext.notification.type.bt_assign', $notification_data);
					}

					$action = 'detail';
				}

				/*
				* view current details from bug report
				*/
				if (!$error && $action == 'detail' && $fav_id)
				{
					unset($sql_array);

					$sql_array = [
						'SELECT'	=> 'b.*, d.description AS report_file, u1.username AS report_author, u1.user_colour AS report_colour, u2.username AS report_assign, u2.user_colour AS report_assign_col',
						'FROM'		=> [DL_BUGS_TABLE => 'b']
					];
					$sql_array['LEFT_JOIN'] = [];
					$sql_array['LEFT_JOIN'][] = [
						'FROM'		=> [DOWNLOADS_TABLE => 'd'],
						'ON'		=> 'b.df_id = d.id'
					];
					$sql_array['LEFT_JOIN'][] = [
						'FROM'		=> [USERS_TABLE => 'u1'],
						'ON'		=> 'u1.user_id = b.report_author_id'
					];
					$sql_array['LEFT_JOIN'][] = [
						'FROM'		=> [USERS_TABLE => 'u2'],
						'ON'		=> 'u2.user_id = b.report_assign_id'
					];

					$sql_array['WHERE'] = 'b.report_id = ' . (int) $fav_id . ' AND ' . $this->db->sql_in_set('d.cat', $bug_access_cats);

					$sql = $this->db->sql_build_query('SELECT', $sql_array);
					$result = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($result);

					$report_id			= $fav_id;
					$report_file_id		= $row['df_id'];
					$report_file		= $row['report_file'];
					$report_title		= $row['report_title'];
					$report_text		= $row['report_text'];
					$bug_uid			= $row['bug_uid'];
					$bug_bitfield		= $row['bug_bitfield'];
					$bug_flags			= $row['bug_flags'];
					$report_text		= generate_text_for_display($report_text, $bug_uid, $bug_bitfield, $bug_flags);
					$report_file_ver	= $row['report_file_ver'];
					$report_date		= $row['report_date'];
					$report_author_id	= $row['report_author_id'];
					$report_assign_id	= $row['report_assign_id'];
					$report_assign_date	= $row['report_assign_date'];
					$report_status		= $row['report_status'];
					$report_status_date	= $row['report_status_date'];
					$report_php			= $row['report_php'];
					$report_db			= $row['report_db'];
					$report_forum		= $row['report_forum'];
					$report_author		= $row['report_author'];
					$report_author_col	= $row['report_colour'];
					$report_assign		= $row['report_assign'];
					$report_assign_col	= $row['report_assign_col'];

					$this->db->sql_freeresult($result);

					// Change status in the report was new and a team member will open the details
					if (!$report_status && $allow_bug_mod)
					{
						$sql = 'INSERT INTO ' . DL_BUG_HISTORY_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
							'df_id'				=> $report_file_id,
							'report_id'			=> $report_id,
							'report_his_type'	=> 'status',
							'report_his_date'	=> time(),
							'report_his_value'	=> '1:' . $this->user->data['username']]);
						$this->db->sql_query($sql);

						$report_status = 1;
						$report_status_date = time();

						$sql = 'UPDATE ' . DL_BUGS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', [
							'report_status'			=> $report_status,
							'report_status_date'	=> $report_status_date]) . ' WHERE report_id = ' . (int) $report_id;
						$this->db->sql_query($sql);
					}

					$u_report_file_link		= $this->helper->route('oxpus_dlext_details', ['df_id' => $report_file_id]);
					$report_author_link		= get_username_string('full', $report_author_id, $report_author, $report_author_col);

					if ($report_assign_id)
					{
						$this->template->assign_block_vars('assign', [
							'ASSIGN_TO'			=> get_username_string('full', $report_assign_id, $report_assign, $report_assign_col),
							'ASSIGN_DATE'		=> $this->user->format_date($report_assign_date),
							'ASSIGN_DATE_RFC'	=> gmdate(DATE_RFC3339, $report_assign_date),
							'U_ASSIGN_TO'		=> append_sid($this->root_path . 'memberlist.' . $this->php_ext, "mode=viewprofile&amp;u=$report_assign_id"),
						]);
					}
					else
					{
						$this->template->assign_var('S_NO_ASSIGN', true);
					}

					$report_date 		= $this->user->format_date($report_date);
					$report_date_rfc	= gmdate(DATE_RFC3339, $row['report_date']);

					$report_title	= censor_text($report_title);
					$report_text	= censor_text($report_text);

					$this->template->set_filenames(['body' => 'dl_bt_detail.html']);

					$this->template->assign_vars([
						'REPORT_ID'			=> $report_id,
						'REPORT_FILE'		=> $report_file,
						'REPORT_TITLE'		=> $report_title,
						'REPORT_TEXT'		=> $report_text,
						'REPORT_FILE_VER'	=> $report_file_ver,
						'REPORT_DATE'		=> $report_date,
						'REPORT_DATE_RFC'	=> $report_date_rfc,
						'REPORT_PHP'		=> $report_php,
						'REPORT_DB'			=> $report_db,
						'REPORT_FORUM'		=> $report_forum,
						'REPORT_AUTHOR'		=> $report_author_link,
						'REPORT_STATUS'		=> $this->language->lang('DL_REPORT_STATUS_' . $report_status),
						'STATUS_DATE'		=> $this->user->format_date($report_status_date),
						'STATUS_DATE_RFC'	=> gmdate(DATE_RFC3339, $row['report_status_date']),

						'U_DOWNLOAD_FILE'	=> $u_report_file_link,
						'U_DOWNLOAD'		=> $this->helper->route('oxpus_dlext_index'),
						'U_BUG_TRACKER'		=> $this->helper->route('oxpus_dlext_tracker'),
					]);

					// Begin report history
					$sql = 'SELECT * FROM ' . DL_BUG_HISTORY_TABLE . '
						WHERE report_id = ' . (int) $fav_id . '
						ORDER BY report_his_id DESC';
					$result = $this->db->sql_query($sql);

					$total_history = $this->db->sql_affectedrows($result);

					if ($total_history)
					{
						$this->template->assign_var('S_HISTORY', true);

						while ($row = $this->db->sql_fetchrow($result))
						{
							$report_his_type = $row['report_his_type'];
							$report_his_value = $row['report_his_value'];

							$output_date		= $this->user->format_date($row['report_his_date']);
							$output_date_rfc	= gmdate(DATE_RFC3339, $row['report_his_date']);

							if ($report_his_type == 'assign')
							{
								$output_value = $this->language->lang('DL_BUG_REPORT_ASSIGN');
								$output_data = explode(':', $report_his_value);

								$output_text = $this->language->lang('DL_BUG_REPORT_ASSIGNED') . ' -> ' . $output_data[1];
							}
							else if ($report_his_type == 'status')
							{
								$output_value = $this->language->lang('DL_BUG_REPORT_STATUS');
								$output_data = explode(':', $report_his_value);

								$output_status = intval($output_data[0]);
								$output_text = $this->language->lang('DL_REPORT_STATUS_' . $output_status) . ' -> ' . $output_data[1];
								$output_text .= (isset($output_data[2])) ? '</span><hr /><span>' . str_replace("\n", "<br />", $output_data[2]) : '';
							}

							$this->template->assign_block_vars('history_row', [
								'VALUE'		=> $output_value,
								'DATE'		=> $output_date,
								'DATE_RFC'	=> $output_date_rfc,
								'TEXT'		=> $output_text,
							]);
						}
					}

					$this->db->sql_freeresult($result);

					if ($allow_bug_mod)
					{
						$this->template->assign_block_vars('delete', [
							'U_DELETE' => $this->helper->route('oxpus_dlext_tracker', ['df_id' => $report_file_id, 'fav_id' => $report_id, 'action' => 'delete']),
						]);

						if ($report_status < 4)
						{
							$sql = 'SELECT ug.user_id FROM ' . DL_AUTH_TABLE . ' dl, ' . USER_GROUP_TABLE . ' ug
								WHERE dl.auth_mod = ' . true .'
									AND dl.group_id = ug.group_id
									AND ug.user_pending <> ' . true . '
								GROUP BY ug.user_id';
							$result = $this->db->sql_query($sql);

							$user_ids = [0];

							while ($row = $this->db->sql_fetchrow($result))
							{
								$user_ids[] = $row['user_id'];
							}
							$this->db->sql_freeresult($result);

							// Codeblock to assign the report to a team member
							$sql = 'SELECT user_id, username_clean FROM ' . USERS_TABLE . '
								WHERE ((' . $this->db->sql_in_set('user_id', $user_ids) . '
									AND user_id <> ' . ANONYMOUS . ')
									OR user_type = ' . USER_FOUNDER . ')
									AND user_id <> ' . (int) $report_assign_id . '
								ORDER BY username';
							$result = $this->db->sql_query($sql);

							if ($this->db->sql_affectedrows($result))
							{
								$this->template->assign_var('S_ASSIGN_MOD', true);

								$s_select_assign_member = '<select name="user_assign">';

								while ($row = $this->db->sql_fetchrow($result))
								{
									$s_select_assign_member .= '<option value="' . $row['user_id'] . '">' . $row['username_clean'] . '</option>';
								}

								$s_select_assign_member .= '</select>';
								$s_select_assign_member = str_replace('<option value="'.$this->user->data['user_id'].'">', '<option value="'.$this->user->data['user_id'].'" selected="selected">', $s_select_assign_member);

								$this->template->assign_vars([
									'S_FORM_ASSIGN_ACTION' => $this->helper->route('oxpus_dlext_tracker', ['action' => 'assign', 'df_id' => $report_file_id, 'fav_id' => $fav_id]),
									'S_SELECT_ASSIGN_USER' => $s_select_assign_member,
								]);
							}

							$this->db->sql_freeresult($result);
						}

						// Create status select
						$s_select_status = '';

						switch ($report_status)
						{
							case 0:
							case 1:
							case 2:
							case 3:
								$s_select_status .= '<option value="2">'.$this->language->lang('DL_REPORT_STATUS_2').'</option>';
								$s_select_status .= '<option value="3">'.$this->language->lang('DL_REPORT_STATUS_3').'</option>';
								$s_select_status .= '<option value="4">'.$this->language->lang('DL_REPORT_STATUS_4').'</option>';
								$s_select_status .= '<option value="5">'.$this->language->lang('DL_REPORT_STATUS_5').'</option>';
							break;
							case 4:
								$s_select_status .= '<option value="2">'.$this->language->lang('DL_REPORT_STATUS_2').'</option>';
								$s_select_status .= '<option value="3">'.$this->language->lang('DL_REPORT_STATUS_3').'</option>';
							break;
							case 5:
								$s_select_status .= '<option value="2">'.$this->language->lang('DL_REPORT_STATUS_2').'</option>';
								$s_select_status .= '<option value="3">'.$this->language->lang('DL_REPORT_STATUS_3').'</option>';
							break;
						}

						if ($s_select_status)
						{
							$this->template->assign_var('S_SELECT_STATUS', true);

							$s_select_status = '<select name="new_status">' . $s_select_status . '</select>';

							$this->template->assign_vars([
								'S_FORM_STATUS_ACTION' => $this->helper->route('oxpus_dlext_tracker', ['action' => 'status', 'df_id' => $report_file_id, 'fav_id' => $fav_id]),
								'S_SELECT_STATUS' => $s_select_status,
							]);
						}
					}

					if ($this->dlext_auth->user_admin())
					{
						$this->template->assign_vars([
							'I_BUG_REPORT'		=> $this->user->img('icon_post_edit', 'EDIT_POST'),
							'U_BUG_REPORT_EDIT'	=> $this->helper->route('oxpus_dlext_tracker', ['action' => 'edit', 'fav_id' => $fav_id]),
						]);
					}

					$notification->delete_notifications([
						'oxpus.dlext.notification.type.bt_assign',
						'oxpus.dlext.notification.type.bt_status',
					], $fav_id, false, $this->user->data['user_id']);

					add_form_key('bt_tracker');
				}

				/*
				* display form to add a bug report or let an admin edit an existing report
				*/
				if (($action == 'add' && $this->user->data['is_registered']) || ($action == 'edit' && $this->dlext_auth->user_admin() && $fav_id))
				{
					$this->template->set_filenames(['body' => 'dl_bt_add.html']);

					$s_hidden_fields = ['action' => 'save'];

					if ($action == 'edit')
					{
						$s_hidden_fields += ['fav_id' => $fav_id];
					}

					$sql = 'SELECT c.cat_name, d.id, d.description, d.desc_uid, d.desc_bitfield, d.desc_flags FROM ' . DOWNLOADS_TABLE . ' d, ' . DL_CAT_TABLE . ' c
						WHERE d.cat = c.id
							AND c.bug_tracker = ' . true . '
							AND ' . $this->db->sql_in_set('c.id', $bug_access_cats) . '
						ORDER BY c.sort ASC, d.sort ASC';
					$result = $this->db->sql_query($sql);

					$s_select_download = '';

					$cur_cat = '';

					while ($row = $this->db->sql_fetchrow($result))
					{
						$cat_name = $row['cat_name'];
						if ($cat_name <> $cur_cat)
						{
							if ($s_select_download != '')
							{
								$s_select_download .= '</optgroup>';
							}
							$s_select_download .= '<optgroup label="' . $cat_name . '">';
							$cur_cat = $cat_name;
						}

						$download_name = $row['description'];
						$desc_uid = $row['desc_uid'];
						$desc_bitfield = $row['desc_bitfield'];
						$desc_flags = $row['desc_flags'];
						$description = generate_text_for_display($download_name, $desc_uid, $desc_bitfield, $desc_flags);

						if ($df_id == $row['id'])
						{
							$s_select_download .= '<option value="' . $row['id'] . '" selected="selected">' . $description . '</option>';
						}
						else
						{
							$s_select_download .= '<option value="' . $row['id'] . '">' . $description . '</option>';
						}
					}

					$s_select_download = '<select name="df_id">' . $s_select_download . '</optgroup></select>';

					$this->db->sql_freeresult($result);

					add_form_key('bt_tracker');

					// Status for HTML, BBCode, Smilies, Images and Flash
					$bbcode_status	= ($this->config['allow_bbcode']) ? true : false;
					$smilies_status	= ($bbcode_status && $this->config['allow_smilies']) ? true : false;
					$img_status		= ($bbcode_status) ? true : false;
					$url_status		= ($this->config['allow_post_links']) ? true : false;
					$flash_status	= ($bbcode_status && $this->config['allow_post_flash']) ? true : false;
					$quote_status	= true;

					// Smilies Block
					include($this->root_path . 'includes/functions_posting.' . $this->php_ext);
					generate_smilies('inline', 0);

					// BBCode-Block
					$this->language->add_lang('posting');
					display_custom_bbcodes();

					if ($action == 'edit' && !$preview)
					{
						$preview = true;

						$sql = 'SELECT * FROM ' . DL_BUGS_TABLE . '
							WHERE report_id = ' . (int) $fav_id;
						$result = $this->db->sql_query($sql);
						$row = $this->db->sql_fetchrow($result);
						$this->db->sql_freeresult($result);

						$report_title		= $row['report_title'];
						$report_text		= $row['report_text'];
						$report_file_ver	= $row['report_file_ver'];
						$report_php			= $row['report_php'];
						$report_db			= $row['report_db'];
						$report_forum		= $row['report_forum'];

						$bug_uid			= $row['bug_uid'];
						$bug_flags			= $row['bug_flags'];

						$text_ary			= generate_text_for_edit($report_text, $bug_uid, $bug_flags);
						$report_text		= $text_ary['text'];
					}

					$this->template->assign_vars([
						'ERROR'				=> ($error) ? implode('<br />', $error_txt) : false,

						'REPORT_TITLE'		=> ($preview) ? $report_title : '',
						'REPORT_TEXT'		=> ($preview) ? $report_text : '',
						'REPORT_FILE_VER'	=> ($preview) ? $report_file_ver : '',
						'REPORT_PHP'		=> ($preview) ? $report_php : '',
						'REPORT_DB'			=> ($preview) ? $report_db : '',
						'REPORT_FORUM'		=> ($preview) ? $report_forum : '',

						'S_BBCODE_ALLOWED'	=> $bbcode_status,
						'S_BBCODE_IMG'		=> $img_status,
						'S_BBCODE_URL'		=> $url_status,
						'S_BBCODE_FLASH'	=> $flash_status,
						'S_BBCODE_QUOTE'	=> $quote_status,

						'S_FORM_ACTION'		=> $this->helper->route('oxpus_dlext_tracker'),
						'S_HIDDEN_FIELDS'	=> build_hidden_fields($s_hidden_fields),
						'S_SELECT_DOWNLOAD'	=> $s_select_download,

						'U_MORE_SMILIES'	=> $this->helper->route('oxpus_dlext_details', ['action' => 'smilies']),
						'U_DOWNLOAD'		=> $this->helper->route('oxpus_dlext_index'),
						'U_BUG_TRACKER'		=> $this->helper->route('oxpus_dlext_tracker'),
					]);

					page_footer();
				}

				/*
				* delete bug report - only if the report really will not be a bug ;)
				*/
				if ($action == 'delete' && $df_id && $fav_id && $allow_bug_mod && !$cancel)
				{
					if (confirm_box(true))
					{
						$sql = 'DELETE FROM ' . DL_BUGS_TABLE . '
							WHERE report_id = ' . (int) $fav_id;
						$this->db->sql_query($sql);

						$sql = 'DELETE FROM ' . DL_BUG_HISTORY_TABLE . '
							WHERE report_id = ' . (int) $fav_id;
						$this->db->sql_query($sql);

						$notification->delete_notifications([
							'oxpus.dlext.notification.type.bt_assign',
							'oxpus.dlext.notification.type.bt_status',
						], $fav_id);

						$fav_id = 0;
					}
					else
					{
						$s_hidden_fields = [
							'df_id'		=> $df_id,
							'fav_id'	=> $fav_id,
							'action'	=> 'delete'
						];

						confirm_box(false, $this->language->lang('DL_CONFIRM_DELETE_BUG_REPORT'), build_hidden_fields($s_hidden_fields));
					}

					$df_id		= 0;
					$action		= '';
					$confirm	= '';
					$cancel		= '';
				}

				if (!$action)
				{
					// Load board header and send default values to template
					$this->template->set_filenames(['body' => 'dl_bt_list.html']);

					unset($sql_array);

					$sql = 'SELECT b.report_status, COUNT(b.report_id) AS total FROM ' . DL_BUGS_TABLE . ' b
							LEFT JOIN ' . DOWNLOADS_TABLE . ' d ON d.id = b.df_id
						WHERE ' . $this->db->sql_in_set('d.cat', $bug_access_cats) . '
						GROUP BY report_status
						ORDER BY report_status';
					$result = $this->db->sql_query($sql);

					$job_status_count = [];

					while ($row = $this->db->sql_fetchrow($result))
					{
						$bug_status_count[$row['report_status']] = $row['total'];
					}

					$this->db->sql_freeresult($result);

					$bug_status_count[0] = (!isset($bug_status_count[0])) ? '' : ' ('.$bug_status_count[0].')';
					$bug_status_count[1] = (!isset($bug_status_count[1])) ? '' : ' ('.$bug_status_count[1].')';
					$bug_status_count[2] = (!isset($bug_status_count[2])) ? '' : ' ('.$bug_status_count[2].')';
					$bug_status_count[3] = (!isset($bug_status_count[3])) ? '' : ' ('.$bug_status_count[3].')';
					$bug_status_count[4] = (!isset($bug_status_count[4])) ? '' : ' ('.$bug_status_count[4].')';
					$bug_status_count[5] = (!isset($bug_status_count[5])) ? '' : ' ('.$bug_status_count[5].')';

					$s_select_filter = '<select name="bt_filter">';
					$s_select_filter .= '<option value="0">'.$this->language->lang('DL_NO_FILTER').'</option>';
					$s_select_filter .= '<option value="-1">'.$this->language->lang('DL_FILTER_OPEN').'</option>';
					$s_select_filter .= '<option value="1">'.$this->language->lang('DL_REPORT_STATUS_0').$bug_status_count[0].'</option>';
					$s_select_filter .= '<option value="2">'.$this->language->lang('DL_REPORT_STATUS_1').$bug_status_count[1].'</option>';
					$s_select_filter .= '<option value="3">'.$this->language->lang('DL_REPORT_STATUS_2').$bug_status_count[2].'</option>';
					$s_select_filter .= '<option value="4">'.$this->language->lang('DL_REPORT_STATUS_3').$bug_status_count[3].'</option>';
					$s_select_filter .= '<option value="5">'.$this->language->lang('DL_REPORT_STATUS_4').$bug_status_count[4].'</option>';
					$s_select_filter .= '<option value="6">'.$this->language->lang('DL_REPORT_STATUS_5').$bug_status_count[5].'</option>';
					$s_select_filter .= '</select>';
					$s_select_filter = str_replace('value="'.$bt_filter.'">', 'value="'.$bt_filter.'" selected="selected">', $s_select_filter);

					$this->template->assign_vars([
						'S_SELECT_FILTER'			=> $s_select_filter,
						'S_FORM_ACTION'				=> $this->helper->route('oxpus_dlext_tracker'),
						'S_FORM_ADD_ACTION'			=> $this->helper->route('oxpus_dlext_tracker', ['action' => 'add', 'df_id' => 0, 'fav_id' => 0]),
						'S_FORM_FILTER_ACTION'		=> $this->helper->route('oxpus_dlext_tracker', ['df_id' => $df_id]),
						'S_FORM_OWN_ACTION'			=> $this->helper->route('oxpus_dlext_tracker', ['df_id' => $df_id, 'bt_show' => 'own']),
						'S_FORM_ASSIGN_ACTION'		=> $this->helper->route('oxpus_dlext_tracker', ['df_id' => $df_id, 'bt_show' => 'assign']),

						'U_DOWNLOAD'				=> $this->helper->route('oxpus_dlext_index'),
						'U_BUG_TRACKER'				=> $this->helper->route('oxpus_dlext_tracker'),
					]);

					/*
					* view bug tracker - detail overview for given download
					*/
					if ($bt_filter == -1)
					{
						$sql_where = ' AND report_status < 3 ';
					}
					else if ($bt_filter > 0)
					{
						--$bt_filter;
						$sql_where = ' AND report_status = ' . (int) $bt_filter . ' ';
					}
					else
					{
						$sql_where = '';
					}

					if ($bt_show == 'own')
					{
						$sql_where .= ' AND report_author_id = ' . (int) $this->user->data['user_id'] . ' ';
					}
					else
					{
						$this->template->assign_var('S_OWN_REPORT', true);
					}

					if ($bt_show == 'assign')
					{
						$sql_where .= ' AND report_assign_id = ' . (int) $this->user->data['user_id'] . ' ';
					}
					else
					{
						$this->template->assign_var('S_ASSIGN_REPORT', true);
					}

					if ($df_id)
					{
						$sql_first_where = ' AND df_id = ' . (int) $df_id . ' ';
						$this->template->assign_var('S_REPORT_TEXT', true);
					}
					else
					{
						$sql_first_where = ' AND df_id <> 0 ';
					}

					$sql = 'SELECT b.* FROM ' . DL_BUGS_TABLE . ' b
						LEFT JOIN ' . DOWNLOADS_TABLE . ' d ON d.id = b.df_id
						WHERE ' . $this->db->sql_in_set('d.cat', $bug_access_cats) . "
						$sql_first_where
							$sql_where";
					$result = $this->db->sql_query($sql);
					$total_reports = $this->db->sql_affectedrows($result);
					$this->db->sql_freeresult($result);

					if ($total_reports > $this->config['dl_links_per_page'])
					{
						$pagination = $this->phpbb_container->get('pagination');
						$pagination->generate_template_pagination(
							$this->helper->route('oxpus_dlext_tracker', ['df_id' => $df_id]),
							'pagination',
							'start',
							$total_reports,
							$this->config['dl_links_per_page'],
							$page_start
						);

						$this->template->assign_vars([
							'PAGE_NUMBER'	=> $pagination->on_page($total_reports, $this->config['dl_links_per_page'], $page_start),
							'TOTAL_DL'		=> $this->language->lang('VIEW_BUG_REPORTS', $total_reports),
						]);
					}

					if ($total_reports)
					{
						if ($df_id)
						{
							$sql_where .= " AND b.df_id = $df_id ";
						}

						unset($sql_array);

						$sql_array = [
							'SELECT'	=> 'b.*, d.id, d.description AS report_file, u1.username AS report_author, u1.user_colour AS report_colour, u2.username AS report_assign, u2.user_colour AS assign_colour',
							'FROM'		=> [DL_BUGS_TABLE => 'b']
						];
						$sql_array['LEFT_JOIN'] = [];
						$sql_array['LEFT_JOIN'][] = [
							'FROM'		=> [DOWNLOADS_TABLE => 'd'],
							'ON'		=> 'b.df_id = d.id'
						];
						$sql_array['LEFT_JOIN'][] = [
							'FROM'		=> [USERS_TABLE => 'u1'],
							'ON'		=> 'u1.user_id = b.report_author_id'
						];
						$sql_array['LEFT_JOIN'][] = [
							'FROM'		=> [USERS_TABLE => 'u2'],
							'ON'		=> 'u2.user_id = b.report_assign_id'
						];

						if ($sql_where)
						{
							$sql_array['WHERE'] = str_replace('# AND', '', '#' . (string) $sql_where);
						}

						if (isset($sql_array['WHERE']))
						{
							$sql_array['WHERE'] .= ' AND ' . $this->db->sql_in_set('d.cat', $bug_access_cats);
						}
						else
						{
							$sql_array['WHERE'] = $this->db->sql_in_set('d.cat', $bug_access_cats);
						}

						$sql_array['ORDER_BY'] = 'b.report_date DESC';

						$sql = $this->db->sql_build_query('SELECT', $sql_array);

						$result = $this->db->sql_query_limit($sql, $this->config['dl_links_per_page'], $start);

						$reports_num = $this->db->sql_affectedrows($result);
					}
					else
					{
						$reports_num = 0;
					}

					if ($reports_num)
					{
						while ($row = $this->db->sql_fetchrow($result))
						{
							$report_dl_id		= $row['id'];
							$report_id			= $row['report_id'];
							$report_title		= $row['report_title'];
							$report_text		= $row['report_text'];
							$bug_uid			= $row['bug_uid'];
							$bug_bitfield		= $row['bug_bitfield'];
							$bug_flags			= $row['bug_flags'];
							$report_text		= generate_text_for_display($report_text, $bug_uid, $bug_bitfield, $bug_flags);
							$report_file_ver	= $row['report_file_ver'];
							$report_file		= $row['report_file'];
							$report_date		= $row['report_date'];
							$report_author_id	= $row['report_author_id'];
							$report_colour		= $row['report_colour'];
							$report_assign_id	= $row['report_assign_id'];
							$report_author		= $row['report_author'];
							$report_assign		= $row['report_assign'];
							$report_assign_col	= $row['assign_colour'];
							$report_assign_date	= $row['report_assign_date'];
							$report_status		= $row['report_status'];
							$report_status_date	= $row['report_status_date'];
							$report_php			= $row['report_php'];
							$report_db			= $row['report_db'];
							$report_forum		= $row['report_forum'];

							$report_title		= censor_text($report_title);
							$report_text		= censor_text($report_text);

							$this->template->assign_block_vars('bug_tracker_row', [
								'REPORT_ID'				=> $report_id,
								'REPORT_TITLE'			=> $report_title,
								'REPORT_TEXT'			=> $report_text,
								'REPORT_DATE'			=> $this->user->format_date($report_date),
								'REPORT_DATE_RFC'		=> gmdate(DATE_RFC3339, $report_date),

								'REPORT_PHP'			=> $report_php,
								'REPORT_DB'				=> $report_db,
								'REPORT_FORUM'			=> $report_forum,

								'REPORT_FILE'			=> $report_file,
								'REPORT_FILE_VER'		=> $report_file_ver,
								'REPORT_FILE_LINK'		=> $this->helper->route('oxpus_dlext_details', ['df_id' => $report_dl_id]),

								'REPORT_AUTHOR_LINK'	=> get_username_string('full', $report_author_id, $report_author, $report_colour),

								'REPORT_STATUS'				=> $this->language->lang('DL_REPORT_STATUS_' . $report_status),
								'REPORT_STATUS_DATE'		=> $this->user->format_date($report_status_date),
								'REPORT_STATUS_DATE_RFC'	=> gmdate(DATE_RFC3339, $report_status_date),

								'REPORT_DETAIL'			=> $this->helper->route('oxpus_dlext_tracker', ['fav_id' => $report_id, 'action' => 'detail']),
							]);

							if ($report_assign_id)
							{
								$this->template->assign_block_vars('bug_tracker_row.assign', [
									'REPORT_ASSIGN_LINK'		=> get_username_string('full', $report_assign_id, $report_assign, $report_assign_col),
									'REPORT_ASSIGN_DATE'		=> $this->user->format_date($report_assign_date),
									'REPORT_ASSIGN_DATE_RFC'	=> gmdate(DATE_RFC3339, $report_assign_date),
								]);
							}
							else
							{
								$this->template->assign_var('S_NO_ASSIGN', true);
							}

							if ($allow_bug_mod)
							{
								$this->template->assign_block_vars('bug_tracker_row.modext', [
									'U_DELETE' => $this->helper->route('oxpus_dlext_tracker', ['df_id' => $report_dl_id, 'fav_id' => $report_id, 'action' => 'delete']),
								]);

								$this->template->assign_block_vars('bug_tracker_row.status_mod', [
									'U_STATUS' => $this->helper->route('oxpus_dlext_tracker', ['df_id' => $report_dl_id, 'fav_id' => $report_id, 'action' => 'status']),
								]);
							}
							else
							{
								$this->template->assign_block_vars('bug_tracker_row.no_status_mod', []);
							}
						}

						$this->db->sql_freeresult($result);
					}
					else
					{
						$this->template->assign_var('S_NO_BUG_TRACKER', true);
					}
				}

				if ($this->user->data['is_registered'])
				{
					$this->template->assign_var('S_ADD_NEW_REPORT', true);
				}

				/*
				* include the mod footer
				*/
				$dl_footer = $this->phpbb_container->get('oxpus.dlext.footer');
				$dl_footer->set_parameter($nav_view, 0, 0, $index);
				$dl_footer->handle();
			}
		}

		redirect($this->helper->route('oxpus_dlext_index'));
	}
}
