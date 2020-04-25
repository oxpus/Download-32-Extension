<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\migrations\v800;

class release_8_0_8 extends \phpbb\db\migration\migration
{
	var $dl_ext_version = '8.0.8';

	public function effectively_installed()
	{
		return isset($this->config['dl_ext_version']) && version_compare($this->config['dl_ext_version'], $this->dl_ext_version, '>=');
	}

	static public function depends_on()
	{
		return array('\oxpus\dlext\migrations\v800\release_8_0_7');
	}

	public function update_data()
	{
		return array(
			// Set the current version
			array('config.update', array('dl_ext_version', $this->dl_ext_version)),

			array('config.add', array('dl_nav_link_main', 'OHNA')),
			array('config.add', array('dl_nav_link_hacks', 'OHNA')),
			array('config.add', array('dl_nav_link_tracker', 'OHNA')),
		);
	}
}