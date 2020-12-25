<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\controller\classes;

use Symfony\Component\DependencyInjection\Container;

class dlext_auth implements dlext_auth_interface
{
	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	protected $dlext_cache;
	protected $dlext_init;
	protected $dl_auth_perm;
	protected $dl_index;

	/**
	* Constructor
	*
	* @param Container 								$phpbb_container
	* @param \phpbb\extension\manager				$phpbb_extension_manager

	* @param \phpbb\user							$user
	* @param \phpbb\auth\auth						$auth
	* @param \phpbb\config\config					$config
	* @param \phpbb\db\driver\driver_interfacer		$db
	*/
	public function __construct(
		Container $phpbb_container,
		\phpbb\extension\manager $phpbb_extension_manager,

		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		$dlext_cache,
		$dlext_init
	)
	{
		$this->user 		= $user;
		$this->auth			= $auth;
		$this->config 		= $config;
		$this->db 			= $db;

		$this->ext_path		= $phpbb_extension_manager->get_extension_path('oxpus/dlext', true);

		$this->dlext_cache	= $dlext_cache;
		$this->dlext_init	= $dlext_init;

		$this->dl_auth_perm	= $this->dlext_cache->obtain_dl_auth();
		$this->dl_index		= $this->dlext_cache->obtain_dl_cats();
	}

	public function dl_auth()
	{
		$cat_auth_array = [];
		$group_ids = [];

		$auth_cat = (isset($this->dl_auth_perm['auth_cat'])) ? $this->dl_auth_perm['auth_cat'] : [];
		$group_perm_ids = (isset($this->dl_auth_perm['group_perm_ids'])) ? $this->dl_auth_perm['group_perm_ids'] : [];
		$auth_perm = (isset($this->dl_auth_perm['auth_perm'])) ? $this->dl_auth_perm['auth_perm'] : [];

		$user_id = ($this->user->data['user_perm_from']) ? $this->user->data['user_perm_from'] : $this->user->data['user_id'];

		if (!empty($group_perm_ids))
		{
			$cat_auth_array = $this->dlext_cache->obtain_dl_access_groups($auth_cat, $group_perm_ids, $user_id, $auth_perm);
		}

		return $cat_auth_array;
	}

	public function dl_index()
	{
		$dl_auth		= $this->dl_auth();
		$user_logged_in	= $this->user_logged_in();

		if (!empty($this->dl_index))
		{
			foreach ($this->dl_index as $key => $value)
			{
				// check the default cat permissions
				if (isset($this->dl_index[$key]['auth_view']) && ($this->dl_index[$key]['auth_view'] == 1 || ($this->dl_index[$key]['auth_view'] == 2 && $user_logged_in)))
				{
					$this->dl_index[$key]['auth_view'] = true;
				}
				else
				{
					$this->dl_index[$key]['auth_view'] = false;
				}

				if (isset($this->dl_index[$key]['auth_dl']) && ($this->dl_index[$key]['auth_dl'] == 1 || ($this->dl_index[$key]['auth_dl'] == 2 && $user_logged_in)))
				{
					$this->dl_index[$key]['auth_dl'] = true;
				}
				else
				{
					$this->dl_index[$key]['auth_dl'] = false;
				}

				if (isset($this->dl_index[$key]['auth_up']) && ($this->dl_index[$key]['auth_up'] == 1 || ($this->dl_index[$key]['auth_up'] == 2 && $user_logged_in)))
				{
					$this->dl_index[$key]['auth_up'] = true;
				}
				else
				{
					$this->dl_index[$key]['auth_up'] = false;
				}

				if (isset($this->dl_index[$key]['auth_mod']) && ($this->dl_index[$key]['auth_mod'] == 1 || ($this->dl_index[$key]['auth_mod'] == 2 && $user_logged_in)))
				{
					$this->dl_index[$key]['auth_mod'] = true;
				}
				else
				{
					$this->dl_index[$key]['auth_mod'] = false;
				}
			}
		}
		else
		{
			$this->dl_index = [];
		}

		$this->cat_counts = $this->dlext_cache->obtain_dl_cat_counts();

		if (!empty($this->cat_counts))
		{
			foreach ($this->cat_counts as $key => $value)
			{
				$this->dl_index[$key]['total'] = $value;
			}
		}

		return $this->dl_index;
	}

	public function dl_cat_auth($cat_id)
	{
		$dl_auth = $this->dl_auth();

		$cat_perm = [];

		$cat_perm['auth_view']	= (isset($dl_auth[$cat_id]['auth_view'])) ? intval($dl_auth[$cat_id]['auth_view']) : 0;
		$cat_perm['auth_dl']	= (isset($dl_auth[$cat_id]['auth_dl'])) ? intval($dl_auth[$cat_id]['auth_dl']) : 0;
		$cat_perm['auth_mod']	= (isset($dl_auth[$cat_id]['auth_mod'])) ? intval($dl_auth[$cat_id]['auth_mod']) : 0;
		$cat_perm['auth_up']	= (isset($dl_auth[$cat_id]['auth_up'])) ? intval($dl_auth[$cat_id]['auth_up']) : 0;
		$cat_perm['auth_cread']	= (isset($dl_auth[$cat_id]['auth_cread'])) ? intval($dl_auth[$cat_id]['auth_cread']) : 0;
		$cat_perm['auth_cpost']	= (isset($dl_auth[$cat_id]['auth_cpost'])) ? intval($dl_auth[$cat_id]['auth_cpost']) : 0;

		return $cat_perm;
	}

	public function user_admin()
	{
		return ($this->auth->acl_get('a_') && $this->user->data['is_registered'] && !$this->user->data['user_perm_from']) ? true : false;
	}

	public function user_banned()
	{
		return DL_USER_IS_BANNED;
	}

	public function user_logged_in()
	{
		return $this->user->data['is_registered'];
	}

	public function user_posts()
	{
		return $this->user->data['user_posts'];
	}

	public function user_traffic()
	{
		return $this->user->data['user_traffic'];
	}

	public function get_ext_blacklist()
	{
		$this->config->set('dl_enable_blacklist', 0);
		$ext_blacklist = [];

		if ($this->config['dl_use_ext_blacklist'])
		{
			$blacklist_ary = $this->dlext_cache->obtain_dl_blacklist();

			if (!empty($blacklist_ary))
			{
				$ext_blacklist = array_unique($blacklist_ary);
				$this->config->set('dl_enable_blacklist', 1);
			}
		}

		return $ext_blacklist;
	}

	public function user_auth($cat_id, $perm)
	{
		$dl_auth	= $this->dl_auth();

		if ((isset($dl_auth[$cat_id][$perm]) && $dl_auth[$cat_id][$perm]) || (isset($this->dl_index[$cat_id][$perm]) && $this->dl_index[$cat_id][$perm]) || $this->user_admin())
		{
			return true;
		}

		return false;
	}

	public function stats_perm()
	{
		$stats_view = false;

		switch ($this->config['dl_stats_perm'])
		{
			case 0:
				$stats_view = true;
			break;

			case 1:
				if ($this->user_logged_in())
				{
					$stats_view = true;
				}
			break;

			case 2:
				foreach ($this->dl_index as $key => $value)
				{
					if ($this->user_auth($this->dl_index[$key]['id'], 'auth_mod'))
					{
						$stats_view = true;
						break;
					}
				}
			break;

			case 3:
				if ($this->user_admin())
				{
					$stats_view = true;
				}
			break;

			case 9:
				$stats_view = false;
			break;

			default:
				$stats_view = false;
		}

		return $stats_view;
	}

	public function cat_auth_comment_read($cat_id)
	{
		$auth_cread = false;

		switch ($this->dl_index[$cat_id]['auth_cread'])
		{
			case 0:
				$auth_cread = true;
				break;

			case 1:
				if ($this->user_logged_in())
				{
					$auth_cread = true;
				}
				break;

			case 2:
				if ($this->user_auth($cat_id, 'auth_mod'))
				{
					$auth_cread = true;
				}
				break;

			case 3:
				if ($this->user_admin())
				{
					$auth_cread = true;
				}
				break;

			default:
				$auth_cread = false;
		}

		return $auth_cread;
	}

	public function cat_auth_comment_post($cat_id)
	{
		$auth_cpost = false;

		switch ($this->dl_index[$cat_id]['auth_cpost'])
		{
			case 0:
				$auth_cpost = true;
				break;

			case 1:
				if ($this->user_logged_in())
				{
					$auth_cpost = true;
				}
				break;

			case 2:
				if ($this->user_auth($cat_id, 'auth_mod'))
				{
					$auth_cpost = true;
				}
				break;

			case 3:
				if ($this->user_admin())
				{
					$auth_cpost = true;
				}
				break;

			default:
				$auth_cpost = false;
		}

		return $auth_cpost;
	}

	public function dl_auth_users($cat_id, $perm)
	{
		$user_ids = [];

		if (!is_array($this->dl_index) || empty($this->dl_index))
		{
			return $user_ids;
		}

		if ($this->dl_index[$cat_id][$perm])
		{
			$sql = 'SELECT user_id FROM ' . USERS_TABLE . '
				WHERE user_id <> ' . ANONYMOUS . '
					AND user_type <> ' . USER_IGNORE . '
					AND user_id <> ' . (int) $this->user->data['user_id'];
			$result = $this->db->sql_query($sql);
		}
		else
		{
			$sql = 'SELECT group_id FROM ' . DL_AUTH_TABLE . '
				WHERE cat_id = ' . (int) $cat_id . '
					AND ' . $this->db->sql_escape($perm) . ' = ' . true . '
				GROUP BY group_id';
			$result = $this->db->sql_query($sql);
			$total_group_perms = $this->db->sql_affectedrows($result);

			if (!$total_group_perms)
			{
				$this->db->sql_freeresult($result);
				return $user_ids;
			}

			$group_ids = [];

			while ($row = $this->db->sql_fetchrow($result))
			{
				$group_ids[] = $row['group_id'];
			}

			$this->db->sql_freeresult($result);

			if (empty($group_ids))
			{
				return $user_ids;
			}

			$sql = 'SELECT user_id FROM ' . USER_GROUP_TABLE . '
				WHERE user_id <> ' . (int) $this->user->data['user_id'] . '
					AND ' . $this->db->sql_in_set('group_id', array_map('intval', $group_ids)) . '
					AND user_pending <> ' . true;
			$result = $this->db->sql_query($sql);
		}

		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = $row['user_id'];
		}

		$this->db->sql_freeresult($result);

		return array_unique($user_ids);
	}

	public function bug_tracker()
	{
		if (!is_array($this->dl_index) || empty($this->dl_index))
		{
			return false;
		}

		$bug_tracker = false;

		foreach ($this->dl_index as $cat_id => $value)
		{
			if (isset($this->dl_index[$cat_id]['bug_tracker']) && $this->dl_index[$cat_id]['bug_tracker'])
			{
				$bug_tracker = true;
				break;
			}
		}

		if ($bug_tracker)
		{
			$sql = 'SELECT count(d.id) as total
					FROM ' . DOWNLOADS_TABLE . ' d, ' . DL_CAT_TABLE . ' c
					WHERE c.id = d.cat
						AND c.bug_tracker = 1';
			$result = $this->db->sql_query($sql);
			$total = $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($result);

			if ($total == 0)
			{
				$bug_tracker = false;
			}
		}

		return $bug_tracker;
	}
}
