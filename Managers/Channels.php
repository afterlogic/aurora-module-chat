<?php
/**
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

/**
 * CApiChatManager class summary
 *
 * @package Chat
 */

namespace Aurora\Modules\Chat\Managers;

class Channels extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;

	/**
	 * 
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);
		
		$this->oEavManager = new \Aurora\System\Managers\Eav();
	}

	/**
	 * @return int
	 */
	public function GetUserChannelsCount()
	{
		return $this->oEavManager->getEntitiesCount(
			$this->getModule()->getNamespace() . '\Classes\Channel', []
		);
	}

	/**
	 * @param string $UserUUID
	 * @return array
	 */
	public function GetUserChannels($UserUUID)
	{
		$aResult = [];
		if (!empty($UserUUID))
		{
			$aUserChannels = $this->oEavManager->getEntities(
				$this->getModule()->getNamespace() . '\Classes\ChannelUser',
				['ChannelUUID'],
				0,
				0,
				['UserUUID' => $UserUUID]
			);
			if (is_array($aUserChannels) && !empty($aUserChannels))
			{
				foreach ($aUserChannels as $oChannel)
				{
					$aResult[] = $oChannel->ChannelUUID;
				}
			}
		}
		return $aResult;
	}

	public function GetChannelByIdOrUID($mIdOrUUID)
	{
		$mChannel = false;
		if ($mIdOrUUID)
		{
			$mChannel = $this->oEavManager->getEntity($mIdOrUUID, $this->getModule()->getNamespace() . '\Classes\Channel');
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Sales\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		return $mChannel;
	}

	public function GetChannels($iLimit = 0, $iOffset = 0, $aSearchFilters = [], $aViewAttributes = [])
	{
		return	$this->oEavManager->getEntities(
				$this->getModule()->getNamespace() . '\Classes\Channel',
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters
			);
	}

	/**
	 * @param string $UserUUID
	 * @param string $sName
	 * @return integer|boolean
	 */
	public function CreateChannel(\Aurora\Modules\Chat\Classes\Channel $oChannel)
	{
		if (!$this->validate($oChannel))
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelAlreadyExists);
		}
		$oChannel->Date = date('Y-m-d H:i:s');
		$mResult = $this->oEavManager->saveEntity($oChannel);
		if (!$mResult)
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelCreateFailed);
		}
//		$this->AddUserToChannel($iChannelId, $UserUUID);
//		$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
//		foreach ($aMembers as $sPublicId)
//		{
//			$oUser = $oCoreDecorator->GetUserByPublicId($sPublicId);
//			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
//			{
//				$this->AddUserToChannel($iChannelId, $oUser->UUID);
//			}
//		}
		return $mResult;
	}

	public function AddUserToChannel($iChannelId, $UserUUID)
	{
		$bResult = false;
		if (!$iChannelId || empty($UserUUID) || !is_string($UserUUID))
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		$oChannel = $this->GetChannelByIdOrUID($iChannelId);
		if ($oChannel instanceof \Aurora\Modules\Chat\Classes\Channel)
		{
			$oNewChannelUser = new \Aurora\Modules\Chat\Classes\ChannelUser($this->GetModule()->GetName());
			$oNewChannelUser->ChannelUUID = $oChannel->UUID;
			$oNewChannelUser->UserUUID = $UserUUID;
			$bResult = $this->oEavManager->saveEntity($oNewChannelUser);
			if (!$bResult)
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelUserCreateFailed);
			}
		}
		return $bResult;
	}

	public function validate(\Aurora\Modules\Chat\Classes\Channel $oChannel)
	{
		$mResult = $this->oEavManager->getEntities(
			$this->getModule()->getNamespace() . '\Classes\Channel',
			[],
			0,
			0,
			['Name' => $oChannel->Name]
		);

		return !$mResult;
	}
}
