<?php
/*
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or Afterlogic Software License
 *
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Chat\Enums;

class ErrorCodes
{
	const Validation_InvalidParameters	= 1000;
	const ChannelCreateFailed			= 1001;
	const ChannelUserCreateFailed		= 1002;
	const ChannelUserAlreadyInChannel	= 1003;
	const UserNotFound					= 1004;
	/**
	 * @var array
	 */
	protected $aConsts = [
		'Validation_InvalidParameters'	=> self::Validation_InvalidParameters,
		'ChannelCreateFailed'			=> self::ChannelCreateFailed,
		'ChannelUserCreateFailed'		=> self::ChannelUserCreateFailed,
		'ChannelUserAlreadyInChannel'	=> self::ChannelUserAlreadyInChannel,
		'UserNotFound'					=> self::UserNotFound
	];
}
