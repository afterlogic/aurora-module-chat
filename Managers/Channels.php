<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Chat\Managers;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
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
		
		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
	}

	/**
	 * @return int
	 */
	public function GetUserChannelsCount()
	{
		return $this->oEavManager->getEntitiesCount(
			\Aurora\Modules\Chat\Classes\Channel::class, []
		);
	}

	/**
	 * Return UUIDs of User's channels
	 * @param string $UserUUID
	 * @return array
	 */
	public function GetUserChannels($UserUUID)
	{
		$aResult = [];
		if (!empty($UserUUID))
		{
			$aUserChannels = $this->oEavManager->getEntities(
				\Aurora\Modules\Chat\Classes\ChannelUser::class,
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

    /**
     * Return  Channel users
     * @param string $ChannelUUID
     * @return array
     */
    public function GetChannelUsers($ChannelUUID)
    {
        $aResult = [];
        if (!empty($ChannelUUID))
        {
            $aChannelUsers = $this->oEavManager->getEntities(
                \Aurora\Modules\Chat\Classes\ChannelUser::class,
                ['UserUUID'],
                0,
                0,
                ['ChannelUUID' => $ChannelUUID]
            );
            if (is_array($aChannelUsers) && !empty($aChannelUsers))
            {
                foreach ($aChannelUsers as $oChannelUser)
                {
                    $aResult[] = $oChannelUser->UserUUID;
                }
            }
        }
        return $aResult;
    }

	/**
	 * 
	 * @param type $mIdOrUUID
	 * @return \Aurora\Modules\Chat\Classes\Channel|bool
	 * @throws \Aurora\System\Exceptions\BaseException
	 */
	public function GetChannelByIdOrUUID($mIdOrUUID)
	{
		$mChannel = false;
		if ($mIdOrUUID)
		{
			$mChannel = $this->oEavManager->getEntity($mIdOrUUID, \Aurora\Modules\Chat\Classes\Channel::class);
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
				\Aurora\Modules\Chat\Classes\Channel::class,
				$aViewAttributes,
				$iOffset,
				$iLimit,
				$aSearchFilters
			);
	}

	public function CreateChannel(\Aurora\Modules\Chat\Classes\Channel $oChannel)
	{
		$oDate = new \DateTime();
		$oDate->setTimezone(new \DateTimeZone('UTC'));
		$oChannel->Timestamp = $oDate->getTimestamp();
		$mResult = $this->oEavManager->saveEntity($oChannel);
		if (!$mResult)
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelCreateFailed);
		}

		return $mResult;
	}

	public function UpdateChannel(\Aurora\Modules\Chat\Classes\Channel $oChannel)
	{
		$bResult = $this->oEavManager->saveEntity($oChannel);
		if (!$bResult)
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelCreateFailed);
		}

		return $bResult;
	}

	public function AddUserToChannel($mChannelIdOrUUID, $UserUUID)
	{
		$bResult = false;
		if (!$mChannelIdOrUUID || empty($UserUUID) || !is_string($UserUUID))
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		$aUserChannelsUUIDs = $this->GetUserChannels($UserUUID);
		$oChannel = $this->GetChannelByIdOrUUID($mChannelIdOrUUID);
		if (!$oChannel instanceof \Aurora\Modules\Chat\Models\Channel)
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		foreach ($aUserChannelsUUIDs as $UserChannelUUID)
		{
			if ($oChannel->UUID === $UserChannelUUID)
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelUserAlreadyInChannel);
			}
		}
		if ($oChannel instanceof \Aurora\Modules\Chat\Classes\Channel)
		{
			$oNewChannelUser = new \Aurora\Modules\Chat\Classes\ChannelUser(\Aurora\Modules\Chat\Module::GetName());
			$oNewChannelUser->ChannelUUID = $oChannel->UUID;
			$oNewChannelUser->UserUUID = $UserUUID;
			$oDate = new \DateTime();
			$oDate->setTimezone(new \DateTimeZone('UTC'));
			$oNewChannelUser->Timestamp = $oDate->getTimestamp();
			$bResult = $this->oEavManager->saveEntity($oNewChannelUser);
			if (!$bResult)
			{
				throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::ChannelUserCreateFailed);
			}
		}
		return $bResult;
	}

	public function DeleteUserFromChannel($UserUUID, $ChannelUUID)
	{
		$bResult = false;

		if (!$UserUUID || !$UserUUID)
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::Validation_InvalidParameters);
		}
		$aChannelUsers = $this->oEavManager->getEntities(
			\Aurora\Modules\Chat\Classes\ChannelUser::class,
			[],
			0,
			0,
			[
				'UserUUID' => $UserUUID,
				'ChannelUUID' => $ChannelUUID
			]
		);
		if (is_array($aChannelUsers) && !empty($aChannelUsers))
		{
			$oChannelUser = $aChannelUsers[0];
			$bResult = $this->oEavManager->deleteEntity($oChannelUser->EntityId, \Aurora\Modules\Chat\Classes\ChannelUser::class);
		}

		return $bResult;
	}
}
