<?php
namespace Aurora\Modules\Chat\Classes;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
set_time_limit(0);
class WebSocketChat implements MessageComponentInterface {
    protected $oClients;

	public $oIntegrator;
	public $oChatModule;

    public function __construct()
	{
		$this->log("Process is started.");
		\Aurora\System\Api::$bUsePing = true;
        $this->oClients = new \SplObjectStorage;
		$this->oIntegrator = new \Aurora\Modules\Core\Managers\Integrator();
		$this->oChatModule = \Aurora\System\Api::GetModule('Chat');
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
					$aInfo = $this->getUserInfo($sToken);
					if (isset($aInfo['userId']) && $aInfo['userId'] > 0)
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
				$aInfo = $this->getUserInfo($oMessage->token);

				if (isset($aInfo['userId']) && $aInfo['userId'] > 0
					&& isset($oMessage->msg->Text) && isset($oMessage->msg->Date))
				{
					$bResult = $this->oChatModule->oApiChatManager->CreatePost($aInfo['userId'], (string) $oMessage->msg->Text, (string) $oMessage->msg->Date);
					if ($bResult)
					{
						foreach ($this->oClients as $oClient)
						{
							if ($oFrom !== $oClient)
							{
								// The sender is not the receiver, send to each client connected
								$oClient->send($oMessage->msg->Text);
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
	
	private function getUserInfo($sAuthToken)
	{
		return $this->oIntegrator->getAuthenticatedUserInfo((string) $sAuthToken);
	}

	private function log($sMessage)
	{
		echo $sMessage . "\n";
		\Aurora\System\Api::Log($sMessage, \Aurora\System\Enums\LogLevel::Full, 'chat-daemon-');
	}
}
