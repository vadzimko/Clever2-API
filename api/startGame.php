<?php
require_once "../engine/common.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];

    checkUserId($userId);
    if ($redis->sismember("waiting_list", $userId)) {
        die(toError('This user is already waiting for a game'));
    }
    $game = getGameByUserId($redis, $userId);
    if ($game && $game->status != GameStatus::FINISHED) {
        die(toError('This user is already playing a game'));
    }

    if ($redis->scard('waiting_list') == 0) {
        $redis->sadd('waiting_list', $userId);

        die(toResponse(array(
            'started' => false,
            'comment' => 'Successfully added to queue, waiting for opponent'
        )));
    } else {
        $opponentId = $redis->spop('waiting_list');
        if ($redis->exists('game_id')) {
            $redis->incr('game_id');
            $gameId = $redis->get('game_id');
        } else {
            $redis->set('game_id', 1);
            $gameId = 1;
        }

        $redis->set(toUserGameId($userId), $gameId);
        $redis->set(toUserGameId($opponentId), $gameId);
        $game = new Game($userId, $opponentId);
        saveGame($redis, $game);

        die(toResponse(array(
            'started' => true
        )));
    }
} catch (Exception $e) {
    die(toError($e->getMessage()));
}


