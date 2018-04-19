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
	public $oApiChatManager = null;
	
	public function init() 
	{
		$this->oApiChatManager = new Manager($this);
		
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
	
	public function GetPostsCount()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		return $this->oApiChatManager->GetPostsCount();
	}
	
	/**
	 * Obtains posts of Chat Module.
	 * 
	 * @param int $Offset uses for obtaining a partial list.
	 * @param int $Limit uses for obtaining a partial list.
	 * @return array
	 */
	public function GetPosts($Offset, $Limit)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$aPosts = $this->oApiChatManager->GetPosts($Offset, $Limit);
		$this->broadcastEvent('Chat::GetPosts', $aPosts);
		return array(
			'Offset' => $Offset,
			'Limit' => $Limit,
			'Collection' => $aPosts
		);
	}

	/**
	 * Creates a new post for authenticated user.
	 * 
	 * @param string $Text text of the new post.
	 * @return boolean
	 */
	public function CreatePost($Text)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$oDate = new \DateTime();
		$oDate->setTimezone(new \DateTimeZone('UTC'));
		$sDate = $oDate->format('Y-m-d H:i:s');
		$this->oApiChatManager->CreatePost($iUserId, $Text, $sDate);
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
	
	protected function getPostsByDate($Date)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$aPosts = $this->oApiChatManager->GetPosts(0, 0, ['Date' => [(string) $Date, '>=']]);
		$this->broadcastEvent('Chat::GetPosts', $aPosts);
		return $aPosts;
	}
}
