<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Chat\Enums;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class ErrorCodes
{
	const Validation_InvalidParameters	= 1000;
	const ChannelCreateFailed			= 1001;
	const ChannelUserCreateFailed		= 1002;
	const ChannelUserAlreadyInChannel	= 1003;
	const UserNotFound					= 1004;
	const PostCreateFailed				= 1005;
	const PermissionDenied				= 1006;
	/**
	 * @var array
	 */
	protected $aConsts = [
		'Validation_InvalidParameters'	=> self::Validation_InvalidParameters,
		'ChannelCreateFailed'			=> self::ChannelCreateFailed,
		'ChannelUserCreateFailed'		=> self::ChannelUserCreateFailed,
		'ChannelUserAlreadyInChannel'	=> self::ChannelUserAlreadyInChannel,
		'UserNotFound'					=> self::UserNotFound,
		'PostCreateFailed'				=> self::PostCreateFailed,
		'PermissionDenied'				=> self::PermissionDenied
	];
}
