<?php
require_once "../engine/common.php";

session_write_close();
ignore_user_abort(false);
set_time_limit(Game::$GAME_INFO_EXPIRE_TIME);

function checkFinished(Game $game) {
    if (!$game || $game->status == GameStatus::FINISHED) {
        die(toError('Game has been finished already'));
    }
}

function checkGameStarted(Predis\Client $redis, $userId) {
    checkUserId($userId);
    if (!$redis->exists(toUserGameId($userId)) && !$redis->sismember('waiting_list', $userId)) {
        die(toError('User has not started a game'));
    }
}


?>