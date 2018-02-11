<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Aurora\Modules\Chat\Classes\WebSocketChat;

require dirname(__DIR__) . '/../system/autoload.php';

\Aurora\System\Api::Init();

$sPort = 8080;

$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			new WebSocketChat()
		)
	),
	$sPort
);

$server->run();