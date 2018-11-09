<?php
require_once "../predis/autoload.php";
require_once "../engine/Game.php";
Predis\Autoloader::register();
header('Content-Type: application/json');

function checkUserId($userId) {
    if (!$userId) {
        die(toError('Request must contain parameter \'user_id\''));
    }
}

function toError($message)
{
    return json_encode(array(
        'status' => 'Failed',
        'comment' => $message
    ), JSON_UNESCAPED_UNICODE);
}

function toResponse(array $members)
{
    $members['status'] = 'OK';
    return json_encode($members, JSON_UNESCAPED_UNICODE);
}

function toUserGameId($userId)
{
    return 'user' . $userId . '_game';
}

function toGameId($gameId)
{
    return 'game' . $gameId;
}

function getGameByUserId(Predis\Client &$redis, $userId)
{
    $gameId = $redis->get(toUserGameId($userId));
    return unserialize($redis->get(toGameId($gameId)));
}

function userIdToGameId(Predis\Client &$redis, $userId) {
    return toGameId($redis->get(toUserGameId($userId)));
}

function saveGame(\Predis\Client &$redis, Game $game) {
    $redis->setex(userIdToGameId($redis, $game->firstPlayerId), Game::$GAME_INFO_EXPIRE_TIME, serialize($game));
}

?>