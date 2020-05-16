<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2002-2020 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace oxpus\dlext\migrations\v710;

class release_7_1_7 extends \phpbb\db\migration\migration
{
	var $dl_ext_version = '7.1.7';

	public function effectively_installed()
	{
		return isset($this->config['dl_ext_version']) && version_compare($this->config['dl_ext_version'], $this->dl_ext_version, '>=');
	}

	static public function depends_on()
	{
		return ['\oxpus\dlext\migrations\v710\release_7_1_6'];
	}

	public function update_data()
	{
		return [
			// Set the current version
			['config.add', ['dl_ext_version', $this->dl_ext_version]],
		];
	}
}
