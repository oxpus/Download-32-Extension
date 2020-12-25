<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\migrations\basics;

class dl_module extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['dl_use_hacklist']);
	}

	static public function depends_on()
	{
		return ['\oxpus\dlext\migrations\basics\dl_schema'];
	}

	public function update_data()
	{
		global $phpbb_container;

		if ($phpbb_container->get('request')->variable('action', '') == 'delete_data')
		{
			$module_manager = $phpbb_container->get('module.manager');

			$module_basenames = ['\oxpus\dlext\ucp\main_module', '\oxpus\dlext\acp\main_module'];

			$sql = 'SELECT module_id, module_class
					FROM ' . MODULES_TABLE . '
					WHERE ' . $this->db->sql_in_set('module_basename', $module_basenames);
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$module_id		= $row['module_id'];
				$module_class	= $row['module_class'];

				$module_manager->delete_module($module_id, $module_class);
			}

			$this->db->sql_freeresult($result);

			return [
				['module.add', [
					'acp',
					'ACP_CAT_DOT_MODS',
					'ACP_DOWNLOADS'
				]],
				['module.add', [
					'ucp',
					false,
					'DOWNLOADS'
				]],

				['config.add', ['dl_use_hacklist', '1']],
			];
		}
		else
		{
			return [
				['module.add', [
					'acp',
					'ACP_CAT_DOT_MODS',
					'ACP_DOWNLOADS'
				]],
				['module.add', [
					'acp',
					'ACP_DOWNLOADS',
					[
						'module_basename'	=> '\oxpus\dlext\acp\main_module',
						'modes'				=> ['overview','config','traffic','categories','files','permissions','stats','banlist','ext_blacklist','toolbox','fields','browser'],
					],
				]],
				['module.add', [
					'ucp',
					false,
					'DOWNLOADS'
				]],
				['module.add', [
					'ucp',
					'DOWNLOADS',
					[
						'module_basename'	=> '\oxpus\dlext\ucp\main_module',
						'modes'				=> ['config','favorite'],
					],
				]],

				['config.add', ['dl_use_hacklist', '1']],
			];
		}
	}
}
