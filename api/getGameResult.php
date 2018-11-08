<?php
require_once "../engine/common.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];

    checkUserId($userId);
    $game = getGameByUserId($redis, $userId);
    if ($redis->sismember("waiting_list", $userId) || !$game) {
        die(toResponse(array(
            'game_status' => 'not_started'
        )));
    }
    if ($game->status != GameStatus::FINISHED) {
        die(toResponse(array(
            'game_status' => 'not_finished'
        )));
    }

    $game = getGameByUserId($redis, $userId);
    $response = array(
        'game_status' => 'finished',
        'has_winner' => $game->firstPlayerScore != $game->secondPlayerScore
    );
    if ($response['has_winner']) {
        $response['winner_id'] = $game->firstPlayerScore > $game->secondPlayerScore
            ? $game->firstPlayerId : $game->secondPlayerId;
    }
    if ($userId == $game->firstPlayerId) {
        $response['user_score'] = $game->firstPlayerScore;
        $response['opponent_score'] = $game->secondPlayerScore;
        $response['opponent_id'] = $game->secondPlayerId;
    } else {
        $response['user_score'] = $game->secondPlayerScore;
        $response['opponent_score'] = $game->firstPlayerScore;
        $response['opponent_id'] = $game->firstPlayerId;
    }

    die(toResponse($response));
} catch (Exception $e) {
    die(toError($e->getMessage()));
}


