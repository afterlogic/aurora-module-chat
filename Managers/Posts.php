<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Chat\Managers;

/**
 * CApiChatManager class summary
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @package Chat
 */
class Posts extends \Aurora\System\Managers\AbstractManager
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
	 * Obtains posts of Chat Module.
	 * 
	 * @param int $iOffset uses for obtaining a partial list.
	 * @param int $iLimit uses for obtaining a partial list.
	 * @param array $aSearchFilters
	 * @return array
	 */
	public function GetPosts($iOffset = 0, $iLimit = 0, $aSearchFilters = [])
	{
		$aResult = [];
		try
		{
			$aResults = $this->oEavManager->getEntities(
				$this->getModule()->getNamespace() . '\Classes\Post',
				array(
					'UserId', 'Text', 'Date', 'ChannelUUID', 'IsHtml'
				),
				$iOffset,
				$iLimit,
				$aSearchFilters
			);
			
			$aUsers = array();

			if (is_array($aResults))
			{
				$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
				foreach($aResults as $oItem)
				{
					if (!isset($aUsers[$oItem->UserId]))
					{
						$oUser = $oCoreDecorator->GetUser($oItem->UserId);
						if ($oUser)
						{
							$aUsers[$oItem->UserId] = $oUser->PublicId;
						}
						else
						{
							$aUsers[$oItem->UserId] = '';
						}
					}
					if (isset($aUsers[$oItem->UserId]))
					{
						$aResult[] = array(
							'userId' => $oItem->UserId,
							'name' => $aUsers[$oItem->UserId],
							'text' => $oItem->Text,
							'date' => $oItem->Date,
							'channelUUID' => $oItem->ChannelUUID,
							'is_html' => $oItem->IsHtml
						);
					}
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$aResult = false;
			$this->setLastException($oException);
		}
		return $aResult;
	}
	
	/**
	 * Creates a new post for user.
	 * 
	 * @param int $iUserId id of user that creates the new post.
	 * @param string $sText text of the new post.
	 * @param string $sDate date of the new post.
	 * @return boolean
	 */
	public function CreatePost($iUserId, $sText, $sDate, $ChannelUUID, $IsHtml = false)
	{
		$bResult = true;
		try
		{
			$oNewPost = new \Aurora\Modules\Chat\Classes\Post($this->GetModule()->GetName());
			$oNewPost->UserId = $iUserId;
			$oNewPost->Text = $sText;
			$oNewPost->Date = $sDate;
			$oNewPost->ChannelUUID = $ChannelUUID;
			$oNewPost->IsHtml = $IsHtml;
			if (!$this->oEavManager->saveEntity($oNewPost))
			{
				throw new \Aurora\System\Exceptions\ManagerException(Errs::UsersManager_UserCreateFailed);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}
	
	/**
	 * Obtains count of posts in channel.
	 * 
	 * @return int
	 */
	public function GetChannelPostsCount($ChannelUUID)
	{
		$iResult = 0;
		if (is_string($ChannelUUID))
		{
			$iResult = (int) $this->oEavManager->getEntitiesCount(
				$this->getModule()->getNamespace() . '\Classes\Post',
				['ChannelUUID' => $ChannelUUID]
			);
		}

		return $iResult;
	}
}