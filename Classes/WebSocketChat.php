<?php
namespace Aurora\Modules\Chat\Classes;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
//set_time_limit(0);
class WebSocketChat implements MessageComponentInterface {
    protected $oClients;

	public $oIntegrator;
	public $oChatDecorator;

    public function __construct()
	{
		\Aurora\System\Api::$bUsePing = true;
        $this->oClients = new \SplObjectStorage;
		$this->oIntegrator = new \Aurora\Modules\Core\Managers\Integrator();
		$this->oChatDecorator = \Aurora\System\Api::GetModuleDecorator('Chat');
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
			$this->log("New connection. ({$oConn->resourceId})\n");
		}
		else
		{
			$oConn->send("User is not authenticated");
			$oConn->close();
			$this->log("Connection not established. ({$oConn->resourceId})\n");
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
					&& isset($oMessage->msg['Text']) && isset($oMessage->msg['Date']))
				{
					$bResult = $this->oChatDecorator->CreatePost($oMessage->msg['Text'], $oMessage->msg['Date']) | false;
					if ($bResult)
					{
						foreach ($this->oClients as $oClient)
						{
							if ($oFrom !== $oClient)
							{
								// The sender is not the receiver, send to each client connected
								$oClient->send($oMessage->msg);
							}
						}
					}
					else
					{
						$this->log("Error. Can't create post. UserId: {$aInfo['userId']}, Date: {$oMessage->msg['Date']} Text:\r\n{$oMessage->msg['Text']}");
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

        $this->log("Connection {$oConn->resourceId} has disconnected\n");
    }

    public function onError(ConnectionInterface $oConn, \Exception $e)
	{
        $this->log("An error has occurred: {$e->getMessage()}\n");

        $oConn->close();
    }
	
	private function getUserInfo($sAuthToken)
	{
		return $this->oIntegrator->getAuthenticatedUserInfo((string) $sAuthToken);
	}

	private function log($sMessage)
	{
		echo $sMessage;
		\Aurora\System\Api::Log($sMessage, \Aurora\System\Enums\LogLevel::Full, 'chat-daemon-');
	}
}
