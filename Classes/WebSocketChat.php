<?php
namespace Aurora\Modules\Chat\Classes;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketChat implements MessageComponentInterface {
    protected $oClients;

	public $oIntegrator;
	public $oChatModule;

    public function __construct()
	{
		$this->log("Process is started.");
		\Aurora\System\Db\Pdo\MySql::$bUseReconnect = true;
        $this->oClients = new \SplObjectStorage;
		$this->oIntegrator = new \Aurora\Modules\Core\Managers\Integrator();
		$this->oChatModule = \Aurora\System\Api::GetModule('Chat');
		
		//ignore warnings "mysql has gone away"
		set_error_handler(function($iErrno, $sErrstr) {
				if(strpos($sErrstr, "MySQL server has gone away") !== false ||
					strpos($sErrstr, "Error while sending STMT_PREPARE packet") !== false
				)
				{
					return true;
				}
				return false;
			},
			E_WARNING
		);
	}

    public function onOpen(ConnectionInterface $oConn)
	{
        // Store the new connection to send messages to later
		$bResult = false;
		try
		{
			$oHttpRequest = $oConn->httpRequest;
			if ($oHttpRequest instanceof \GuzzleHttp\Psr7\Request)
			{
				$oUri = $oHttpRequest->getUri();
				if ($oUri instanceof \GuzzleHttp\Psr7\Uri)
				{
					$sToken = $oUri->getQuery() | '';
					$oUser = $this->getUser($sToken);
					if ($oUser)
					{
						$this->oClients->attach($oConn);
						$bResult = true;
					}
				}
			}
		}
		catch (Exception $e)
		{}
		if ($bResult)
		{
			$this->log("New connection. ({$oConn->resourceId})");
		}
		else
		{
			$oConn->send("User is not authenticated");
			$oConn->close();
			$this->log("Connection not established. ({$oConn->resourceId})");
		}
    }

    public function onMessage(ConnectionInterface $oFrom, $sMsg)
	{
		try
		{
			$oMessage = json_decode($sMsg);
			if (isset($oMessage) && isset($oMessage->token) && isset($oMessage->msg))
			{
				$oUser = $this->getUser($oMessage->token);

				if ($oUser && isset($oMessage->msg->Text) && isset($oMessage->msg->Date))
				{
					$bResult = $this->oChatModule->oApiChatManager->CreatePost($oUser->EntityId, (string) $oMessage->msg->Text, (string) $oMessage->msg->Date);
					if ($bResult)
					{
						foreach ($this->oClients as $oClient)
						{
							if ($oFrom !== $oClient)
							{
								// The sender is not the receiver, send to each client connected
								$oClient->send(json_encode([
									'UserId' => $oUser->EntityId,
									'PublicId' => $oUser->PublicId,
									'Text' => $oMessage->msg->Text,
									'Date' => $oMessage->msg->Date
								]));
							}
						}
					}
					else
					{
						$this->log("Error. Can't create post. UserId: {$aInfo['userId']}, Date: {$oMessage->msg->Dated} Text:\r\n{$oMessage->msg->Text}");
						$oFrom->send("Can't create message");
					}
				}
				else
				{
					$this->log("Error. Non-authenticated user. Token: {$oMessage->token}");
					$oFrom->send("User is not authenticated");
				}
			}
		}
		catch (Exception $e)
		{}
    }

    public function onClose(ConnectionInterface $oConn)
	{
        // The connection is closed, remove it, as we can no longer send it messages
		$this->oClients->detach($oConn);

        $this->log("Connection {$oConn->resourceId} has disconnected");
    }

    public function onError(ConnectionInterface $oConn, \Exception $e)
	{
        $this->log("An error has occurred: {$e->getMessage()}");

        $oConn->close();
    }
	
	private function getUser($sAuthToken)
	{
		$aAccountHashTable = \Aurora\System\Api::UserSession()->Get($sAuthToken);
		if (is_array($aAccountHashTable) && isset($aAccountHashTable['token']) &&
			'auth' === $aAccountHashTable['token'] && 0 < strlen($aAccountHashTable['id'])) {
			
			$oCoreModule = \Aurora\System\Api::GetModule('Core');
			if ($oCoreModule)
			{
				$oUser = $oCoreModule->oApiUsersManager->getUser((int) $aAccountHashTable['id']);				
				if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
				{
					return $oUser;
				}
			}
		}
		return false;
	}

	private function log($sMessage)
	{
//		echo $sMessage . "\n";
		\Aurora\System\Api::Log($sMessage, \Aurora\System\Enums\LogLevel::Full, 'chat-daemon-');
	}
}
