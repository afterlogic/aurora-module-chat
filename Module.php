<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Chat;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiPostsManager = null;
	public $oApiChannelsManager = null;

	public function init() 
	{
		$this->oApiPostsManager = new Managers\Posts($this);
		$this->oApiChannelsManager = new Managers\Channels($this);
		$this->aErrors = [
			Enums\ErrorCodes::UserNotFound	=> $this->i18N('ERROR_USER_NOT_FOUND')
		];
		$this->extendObject(
			'Aurora\Modules\Core\Classes\User', 
			[
				'EnableModule' => ['bool', true],
				'LastShowPostsDate'	=> ['datetime', date('Y-m-d H:i:s', 0)]
			]
		);
	}

	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			return array(
				'EnableModule' => $oUser->{$this->GetName().'::EnableModule'}
			);
		}

		return null;
	}

	/**
	 * Updates settings of the Chat Module.
	 * 
	 * @param boolean $EnableModule indicates if user turned on Chat Module.
	 * @return boolean
	 */
	public function UpdateSettings($EnableModule)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		if (0 < $iUserId)
		{
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			$oUser = $oCoreDecorator->GetUser($iUserId);
			$oUser->{$this->GetName().'::EnableModule'} = $EnableModule;
			$oCoreDecorator->UpdateUserObject($oUser);
		}
		return true;
	}

	/**
	 * Obtains posts of Chat Module.
	 * 
	 * @param int $Offset uses for obtaining a partial list.
	 * @param int $Limit uses for obtaining a partial list.
	 * @return array
	 */
	public function GetPosts($Offsets, $Limit, $ChannelUUID = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aPosts = [];
		$aUserChannels = $this->GetUserChannels();

		foreach ($aUserChannels as $oChannel)
		{
			if ((isset($ChannelUUID) && $oChannel->UUID === $ChannelUUID) || !isset($ChannelUUID))
			{
				$iOffset = isset($Offsets[$oChannel->UUID]) ? $Offsets[$oChannel->UUID] : 0;
				$aPosts = array_merge(
					$aPosts,
					$this->oApiPostsManager->GetPosts(
						$iOffset,
						$Limit,
						['ChannelUUID' =>$oChannel->UUID]
					)
				);
			}
		}
		$this->broadcastEvent('Chat::GetPosts', $aPosts);
		return [
			'Limit' => $Limit,
			'Collection' => $aPosts
		];
	}

	/**
	 * Creates a new post for authenticated user.
	 * 
	 * @param string $Text text of the new post.
	 * @return boolean
	 */
	public function CreatePost($Text, $ChannelUUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$oDate = new \DateTime();
		$oDate->setTimezone(new \DateTimeZone('UTC'));
		$sDate = $oDate->format('Y-m-d H:i:s');
		$this->oApiPostsManager->CreatePost($iUserId, $Text, $sDate, $ChannelUUID);
		return true;
	}

	public function GetLastPosts($IsUpdateLastShowPostsDate = false)
	{
		$mResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$iEndTime = time() + 29;
			$oNow = new \DateTime("-1 seconds");
			$oNow->setTimezone(new \DateTimeZone('UTC'));
			$sCheckTime = $oNow->format('Y-m-d H:i:s');
			if ($IsUpdateLastShowPostsDate || $oUser->{$this->GetName() . '::LastShowPostsDate'} === date('Y-m-d H:i:s', 0))
			{
				$oUser->{$this->GetName() . '::LastShowPostsDate'} = $sCheckTime;
				$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
				if ($oCoreDecorator)
				{
					$oCoreDecorator->UpdateUserObject($oUser);
				}
			}

			while (time() < $iEndTime)
			{
				usleep(500000);
				$aPosts = $this->getPostsByDate($sCheckTime);
				if(is_array($aPosts) && !empty($aPosts))
				{
					$mResult = ['Collection' => $aPosts];
					break;
				}
			}
		}
		return $mResult;
	}

	public function IsHaveUnseen()
	{
		$bResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$aUnseenPosts = $this->getPostsByDate($oUser->{$this->GetName() . '::LastShowPostsDate'});
			if (is_array($aUnseenPosts) && count($aUnseenPosts) > 0)
			{
				$bResult = true;
			}
		}
		return $bResult;
	}

	public function CreateChannel($Name)
	{
		$bResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$oChannel = new \Aurora\Modules\Chat\Classes\Channel($this->GetName());
			$oChannel->Name = $Name;
			$iChannelId = $this->oApiChannelsManager->CreateChannel($oChannel);
			if ($iChannelId)
			{
				$bResult = !!$this->oApiChannelsManager->AddUserToChannel($iChannelId, $oUser->UUID);
			}
			if ($bResult)
			{
				$oNewChannel = $this->oApiChannelsManager->GetChannelByIdOrUUID($iChannelId);
			}
		}
		return $oNewChannel ? $oNewChannel->UUID : $bResult;
	}

	public function GetUserChannels()
	{
		$aResult = [];
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$aChannelUUIDs = $this->oApiChannelsManager->GetUserChannels($oUser->UUID);
			$aResult = $this->oApiChannelsManager->GetChannels(0, 0,
				['UUID' => [\array_unique($aChannelUUIDs), 'IN']],
				['Name']
			);
		}
		return $aResult;
	}

	protected function getPostsByDate($Date)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$aPosts = $this->oApiPostsManager->GetPosts(0, 0, ['Date' => [(string) $Date, '>=']]);
		$this->broadcastEvent('Chat::GetPosts', $aPosts);
		return $aPosts;
	}

	public function GetUserChannelsWithPosts($Limit)
	{
		$aResult = [];
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			$aChannelsUUIDs = $this->oApiChannelsManager->GetUserChannels($oUser->UUID);
			foreach ($aChannelsUUIDs as $ChanneUUID)
			{
				$oChannel = $this->oApiChannelsManager->GetChannelByIdOrUUID($ChanneUUID);
				if ($oChannel instanceof \Aurora\Modules\Chat\Classes\Channel)
				{
					$aChannelUsersUUIDs = $this->oApiChannelsManager->GetChannelUsers($oChannel->UUID);
					$aChannelUsers = [];
					foreach ($aChannelUsersUUIDs as $UserUUID)
					{
						$oUser = $oCoreDecorator->GetUserByUUID($UserUUID);
						if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
						{
							$aChannelUsers[] = [
								'UUID' => $oUser->UUID,
								'PublicId' => $oUser->PublicId
							];
						}
					}
					$iPostsCount = $this->oApiPostsManager->GetChannelPostsCount($oChannel->UUID);
					$aResult[$oChannel->UUID]['PostCount'] = $iPostsCount;
					$aResult[$oChannel->UUID]['PostsCollection'] = $this->oApiPostsManager->GetPosts(
						($iPostsCount - $Limit) > 0 ? $iPostsCount - $Limit : 0,
						$Limit,
						['ChannelUUID' => $oChannel->UUID]
					);
					$aResult[$oChannel->UUID]['Name'] = $oChannel->Name;
					$aResult[$oChannel->UUID]['UsersCollection'] = $aChannelUsers;
				}
			}
		}
		return [
			'Collection' => $aResult
		];
	}
	
	public function AddUserToChannel($ChannelUUID, $UserPublicId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$bResult = false;
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($UserPublicId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$bResult = !!$this->oApiChannelsManager->AddUserToChannel($ChannelUUID, $oUser->UUID);
		}
		else
		{
			throw new \Aurora\System\Exceptions\BaseException(\Aurora\Modules\Chat\Enums\ErrorCodes::UserNotFound);
		}

		return $bResult;
	}
	
	public function RenameChannel($ChannelUUID, $Name)
	{
		$bResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$aChannelsUUIDs = $this->oApiChannelsManager->GetUserChannels($oUser->UUID);
			if (in_array($ChannelUUID, $aChannelsUUIDs))
			{
				$oChannel = $this->oApiChannelsManager->GetChannelByIdOrUUID($ChannelUUID);
				$oChannel->Name = $Name;
				$bResult = $this->oApiChannelsManager->UpdateChannel($oChannel);
			}
		}
		
		return $bResult;
	}
	
	public function DeleteUserFromChannel($UserPublicId, $ChannelUUID)
	{
		$bResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$aChannelsUUIDs = $this->oApiChannelsManager->GetUserChannels($oUser->UUID);
			if (in_array($ChannelUUID, $aChannelsUUIDs))
			{
				$oUserForDeletion = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($UserPublicId);
				if ($oUserForDeletion instanceof \Aurora\Modules\Core\Classes\User)
				{
					$bResult = $this->oApiChannelsManager->DeleteUserFromChannel($oUserForDeletion->UUID, $ChannelUUID);
				}
			}
		}

		return $bResult;
	}
}
