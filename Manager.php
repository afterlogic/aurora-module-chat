<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
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

namespace Aurora\Modules\Chat;

class Manager extends \Aurora\System\Managers\AbstractManager
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
	 * Obtains count of all posts.
	 * 
	 * @return int
	 */
	public function GetPostsCount()
	{
		return $this->oEavManager->getEntitiesCount(
			$this->getModule()->getNamespace() . '\Classes\Post', array()
		);
	}
	
	/**
	 * Obtains posts of Chat Module.
	 * 
	 * @param int $Offset uses for obtaining a partial list.
	 * @param int $Limit uses for obtaining a partial list.
	 * @return array
	 */
	public function GetPosts($Offset = 0, $Limit = 0)
	{
		$aResult = array();
		try
		{
			$aResults = $this->oEavManager->getEntities(
				$this->getModule()->getNamespace() . '\Classes\Post',
				array(
					'UserId', 'Text', 'Date', 'IsHtml'
				),
				$Offset,
				$Limit,
				array()
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
	public function CreatePost($iUserId, $sText, $sDate, $IsHtml = false)
	{
		$bResult = true;
		try
		{
			$oNewPost = new Classes\Post($this->GetModule()->GetName());
			$oNewPost->UserId = $iUserId;
			$oNewPost->Text = $sText;
			$oNewPost->Date = $sDate;
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
}
