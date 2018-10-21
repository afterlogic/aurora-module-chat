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
			Enums\ErrorCodes::UserNotFound	=> $this->i18N('ERROR_USER_NOT_FOUND'),
			Enums\ErrorCodes::ChannelUserAlreadyInChannel	=> $this->i18N('ERROR_USER_ALREADY_IN_CHANNEL')
		];
		$this->extendObject(
			'Aurora\Modules\Core\Classes\User', 
			[
				'EnableModule'				=> ['bool', true],
				'LastShowPostsTimestamp'	=> ['int', 0], //used to check if user have unseen posts
				'LastRespondedPostId'		=> ['int', 0]  //used to find posts that were created after the last request GetLastPosts
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
	public function GetPosts($Offset = 0, $Limit = 0, $ChannelUUID = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$aPosts = [];
		$aUserChannels = $this->GetUserChannels();

		foreach ($aUserChannels as $oChannel)
		{
			if ((isset($ChannelUUID) && $oChannel->UUID === $ChannelUUID) || !isset($ChannelUUID))
			{
				$aPosts = array_merge(
					$aPosts,
					$this->oApiPostsManager->GetPosts(
						$Offset,
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
	public function CreatePost($Text, $ChannelUUID, $GUID = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$oDate = new \DateTime();
		$oDate->setTimezone(new \DateTimeZone('UTC'));
		$this->oApiPostsManager->CreatePost($iUserId, $Text, $oDate->getTimestamp(), $ChannelUUID, /*$IsHtml*/false, $GUID);
		return true;
	}

	public function GetLastPosts($IsUpdateLastShowPostsTimestamp = false)
	{
		$mResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
		if (!empty($oUser) && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$iEndTime = time() + 29;
			$oNow = new \DateTime("-1 seconds");
			$oNow->setTimezone(new \DateTimeZone('UTC'));
			$iCheckTime = $oNow->getTimestamp();
			if ($IsUpdateLastShowPostsTimestamp || $oUser->{$this->GetName() . '::LastShowPostsTimestamp'} === 0)
			{
				$oUser->{$this->GetName() . '::LastShowPostsTimestamp'} = $iCheckTime;
				if ($oCoreDecorator)
				{
					$oCoreDecorator->UpdateUserObject($oUser);
				}
			}

			while (time() < $iEndTime)
			{
				usleep(500000);
				$aPosts = $this->getPostsById($oUser->{$this->GetName() . '::LastRespondedPostId'});
				if(is_array($aPosts) && !empty($aPosts))
				{
					if ($oCoreDecorator)
					{
						$oUser->{$this->GetName() . '::LastRespondedPostId'} = $aPosts[count($aPosts) - 1]['id'];
						$oCoreDecorator->UpdateUserObject($oUser);
					}
					$mResult = ['Collection' => $aPosts];
					//add information about posts count in channels
					$aPostsCount = [];
					$aChannelsUUIDs = array_unique(
						array_map(
							function($value) {
								return $value['channelUUID'];
							},
							$aPosts
						)
					);
					foreach ($aChannelsUUIDs as $ChannelUUID)
					{
						$iPostsCount = $this->oApiPostsManager->GetChannelPostsCount($ChannelUUID);
						$aPostsCount[$ChannelUUID] = $iPostsCount;
					}
					$mResult['PostsCount'] = $aPostsCount;
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
			$aUnseenPosts = $this->getPostsByTimestamp($oUser->{$this->GetName() . '::LastShowPostsTimestamp'});
			if (is_array($aUnseenPosts) && count($aUnseenPosts) > 0)
			{
				$bResult = true;
			}
		}
		return $bResult;
	}

	public function CreateChannel($Name = '')
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

	protected function getPostsByTimestamp($iTimestamp)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$aUserChannelsUUIDs = $this->oApiChannelsManager->GetUserChannels($oUser->UUID);
		if (is_array($aUserChannelsUUIDs) && !empty($aUserChannelsUUIDs))
		{
			$aPosts = $this->oApiPostsManager->GetPosts(0, 0,
				[
					'Timestamp' => [$iTimestamp, '>'],
					'ChannelUUID' => [\array_unique($aUserChannelsUUIDs), 'IN']
				]
			);
			$this->broadcastEvent('Chat::GetPosts', $aPosts);
		}
		return $aPosts;
	}

	/*
	 * Returns posts with ID larger than specified
	 */
	protected function getPostsById($Id)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$aUserChannelsUUIDs = $this->oApiChannelsManager->GetUserChannels($oUser->UUID);
		if (is_array($aUserChannelsUUIDs) && !empty($aUserChannelsUUIDs))
		{
			$aPosts = $this->oApiPostsManager->GetPosts(0, 0,
				[
					'EntityId' => [$Id, '>'],
					'ChannelUUID' => [\array_unique($aUserChannelsUUIDs), 'IN']
				]
			);
			$this->broadcastEvent('Chat::GetPosts', $aPosts);
		}
		return $aPosts;
	}

	public function GetUserChannelsWithPosts($Limit)
	{
		$aResult = [];
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if (!empty($oAuthenticatedUser) && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			$aChannelsUUIDs = $this->oApiChannelsManager->GetUserChannels($oAuthenticatedUser->UUID);
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
					$aResult[$oChannel->UUID]['PostsCount'] = $iPostsCount;
					$aPosts = $this->oApiPostsManager->GetPosts(
						($iPostsCount - $Limit) > 0 ? $iPostsCount - $Limit : 0,
						$Limit,
						['ChannelUUID' => $oChannel->UUID]
					);
					$this->broadcastEvent('Chat::GetPosts', $aPosts);
					$aResult[$oChannel->UUID]['PostsCollection'] = $aPosts;
					if ($oChannel->Name)
					{
						$aResult[$oChannel->UUID]['Name'] = $oChannel->Name;
					}
					else
					{
						//if Channel name is empty - use list of users publicIDs as channel name
						//except authenticated user publicID
						$aChannelUserPublicIds = [$this->i18N('LABEL_CHANNEL_OWN_NAME')];
						foreach ($aChannelUsers as $aChannelUser)
						{
							if ($oAuthenticatedUser->UUID !== $aChannelUser['UUID'])
							{
								$aChannelUserPublicIds[] = $aChannelUser['PublicId'];
							}
						}
						$aResult[$oChannel->UUID]['Name'] = implode(", ", $aChannelUserPublicIds);
					}
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
