<?php
require_once "../engine/commonLongPoll.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];

    checkGameStarted($redis, $userId);
    while (true) {
        if ($redis->sismember('waiting_list', $userId)) {
            sleep(1);
            continue;
        }
        $game = getGameByUserId($redis, $userId);
        checkFinished($game);

        $status = &$game->status;
        $round = &$game->round;
        if ($status == GameStatus::ROUND_TIMEOUT && time() > $round->getTimeoutEndTime()) {
            $game->nextRound();
            saveGame($redis, $game);
        }

        if ($status !== GameStatus::ROUND) {
            sleep(1);
        } else {
            $round = &$game->round;
            if ($userId == $game->firstPlayerId) {
                $opponentId = $game->secondPlayerId;
            } else {
                $opponentId = $game->firstPlayerId;
            }
            die(toResponse(array(
                'round_status' => 'started',
                'round_number' => $game->roundNumber,
                'question' => $round->question,
                'answers' => $round->answers,
                'opponent_id' => $opponentId,
                'end_time' => $round->getRoundEndTime()
            )));
        }
    }

} catch (Exception $e) {
    die(toError($e->getMessage()));
}


