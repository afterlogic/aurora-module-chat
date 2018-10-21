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

		$aResults = $this->oEavManager->getEntities(
			$this->getModule()->getNamespace() . '\Classes\Post',
			[
				'UserId', 'Text', 'Timestamp', 'ChannelUUID', 'IsHtml', 'GUID', 'IsSystem'
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
					$aResult[] = array(
						'id'			=> $oItem->EntityId,
						'userId'		=> $oItem->UserId,
						'name'			=> $aUsers[$oItem->UserId],
						'text'			=> $oItem->Text,
						'date'			=> date('Y-m-d H:i:s', $oItem->Timestamp),	
						'channelUUID'	=> $oItem->ChannelUUID,
						'is_html'		=> $oItem->IsHtml,
						'GUID'			=> $oItem->GUID,
						'is_system'		=> $oItem->IsSystem
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
	public function CreatePost($iUserId, $sText, $iTimestamp, $ChannelUUID, $IsHtml = false, $GUID = '', $IsSystem = false)
	{
		$bResult = true;

		$oNewPost = new \Aurora\Modules\Chat\Classes\Post($this->GetModule()->GetName());
		$oNewPost->UserId = $iUserId;
		$oNewPost->Text = $sText;
		$oNewPost->Timestamp = $iTimestamp;
		$oNewPost->ChannelUUID = $ChannelUUID;
		$oNewPost->IsHtml = $IsHtml;
		$oNewPost->GUID = $GUID;
		$oNewPost->IsSystem = $IsSystem;
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
				$this->getModule()->getNamespace() . '\Classes\Post',
				['ChannelUUID' => $ChannelUUID]
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
		$sText = json_encode([
			'Text'			=> $sText ? $sText : '',
			'CommandCode'	=> $iCommandCode ? $iCommandCode : 0
		]);

		if (!$this->CreatePost(0, $sText, $oDate->getTimestamp(), $ChannelUUID, $IsHtml, /*GUID*/'', /*IsSystem*/true))
		{
			throw new \Aurora\System\Exceptions\ManagerException(\Aurora\Modules\Chat\Enums\ErrorCodes::PostCreateFailed);
		}

		return $bResult;
	}
}
