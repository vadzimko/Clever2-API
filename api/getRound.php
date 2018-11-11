<?php
require_once "../engine/commonLongPoll.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];

    checkGameStarted($redis, $userId);
    while (true) {
        if ($redis->sismember('waiting_list', $userId)) {
            sleep(SLEEP_TIME);
            continue;
        }
        $game = getUpdatedGame($redis, $userId, true);
        checkFinished($game);

        if ($game->status !== GameStatus::ROUND) {
            sleep(SLEEP_TIME);
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
                'end_time' => $round->endTime
            )));
        }
    }

} catch (Exception $e) {
    die(toError($e->getMessage()));
}


