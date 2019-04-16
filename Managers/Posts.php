<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Chat\Managers;

/**
 * CApiChatManager class summary
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
		
		$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
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

		$aResults = $this->oEavManager->getEntities(
			\Aurora\Modules\Chat\Module::getNamespace() . '\Classes\Post',
			[
				'UserId', 'Text', 'Timestamp', 'ChannelUUID', 'IsHtml', 'GUID', 'SystemCommandCode'
			],
			$iOffset,
			$iLimit,
			$aSearchFilters
		);

		$aUsers = [];

		if (is_array($aResults))
		{
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			if (count($aResults) > 1)
			{
				//sort result by EntityId
				usort($aResults, function ($a, $b)
					{
						if ($a->EntityId == $b->EntityId) {
							return 0;
						}
						return ($a->EntityId < $b->EntityId) ? -1 : 1;
					}
				);
			}
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
					$oDate = new \DateTime("now", new \DateTimeZone('UTC'));
					$oDate->setTimestamp($oItem->Timestamp);
					$aResult[] = array(
						'id'				=> $oItem->EntityId,
						'userId'			=> $oItem->UserId,
						'name'				=> $aUsers[$oItem->UserId],
						'text'				=> $oItem->Text,
						'date'				=> $oDate->format('Y-m-d H:i:s'),	
						'channelUUID'		=> $oItem->ChannelUUID,
						'isHtml'			=> $oItem->IsHtml,
						'GUID'				=> $oItem->GUID,
						'systemCommandCode'	=> $oItem->SystemCommandCode
					);
				}
			}
		}

		return $aResult;
	}
	
	/**
	 * Creates a new post for user.
	 * 
	 * @param int $iUserId id of user that creates the new post.
	 * @param string $sText text of the new post.
	 * @param string $iTimestamp date of the new post.
	 * @return boolean
	 */
	public function CreatePost($iUserId, $sText, $iTimestamp, $ChannelUUID, $IsHtml = false, $GUID = '', $SystemCommandCode = 0)
	{
		$bResult = true;

		$oNewPost = new \Aurora\Modules\Chat\Classes\Post(\Aurora\Modules\Chat\Module::GetName());
		$oNewPost->UserId = $iUserId;
		$oNewPost->Text = $sText;
		$oNewPost->Timestamp = $iTimestamp;
		$oNewPost->ChannelUUID = $ChannelUUID;
		$oNewPost->IsHtml = $IsHtml;
		$oNewPost->GUID = $GUID;
		$oNewPost->SystemCommandCode = $SystemCommandCode;
		if (!$this->oEavManager->saveEntity($oNewPost))
		{
			throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Chat\Enums\ErrorCodes::PostCreateFailed);
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
				\Aurora\Modules\Chat\Classes\Post::class,
				[
					'$AND' =>
					[
						'ChannelUUID' => $ChannelUUID,
						'Text' => ['NULL', 'IS NOT']
					]
				]
			);
		}

		return $iResult;
	}

	/**
	 * Creates a new post for user.
	 * 
	 * @param int $iUserId id of user that creates the new post.
	 * @param string $sText text of the new post.
	 * @param string $iTimestamp date of the new post.
	 * @return boolean
	 */
	public function CreateSystemPost($sText, $iCommandCode, $ChannelUUID, $IsHtml = false)
	{
		$bResult = true;
		$oDate = new \DateTime();
		$oDate->setTimezone(new \DateTimeZone('UTC'));

		if (!$this->CreatePost(0, $sText, $oDate->getTimestamp(), $ChannelUUID, $IsHtml, /*GUID*/'', $iCommandCode))
		{
			throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Chat\Enums\ErrorCodes::PostCreateFailed);
		}

		return $bResult;
	}
}
