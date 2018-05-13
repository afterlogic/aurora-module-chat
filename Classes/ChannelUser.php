<?php
/**
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */


namespace Aurora\Modules\Chat\Classes;


/**
 * @property int $UserId
 * @property string $Text
 *
 * @package Chat
 * @subpackage Classes
 */
class ChannelUser extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'UserUUID'		=> array('string', ''),
		'ChannelUUID'	=> array('string', ''),
		'Date'			=> array('datetime', ''),
	);
}
