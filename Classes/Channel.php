<?php
/**
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 * @license AGPL-3.0 or Afterlogic Software License
 *
 * This code is licensed under AGPLv3 license or Afterlogic Software License
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
class Channel extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'OwnerUserUUID'	=> array('string', ''),
		'Name'		=> array('string', ''),
		'Timestamp'		=> array('int', 0)
	);
	
}
