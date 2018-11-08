<?php
require_once "../engine/commonLongPoll.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];

    checkGameStarted($redis, $userId);
    while (true) {
        $game = getGameByUserId($redis, $userId);
        checkFinished($game);

        $status = &$game->status;
        $round = &$game->round;
        if ($status == GameStatus::ROUND && (time() > $round->getRoundEndTime()
                || $round->firstPlayerAnswer !== 0 && $round->secondPlayerAnswer !== 0)) {
            $game->finishRound();
            saveGame($redis, $game);
        }

        if ($status !== GameStatus::ROUND_TIMEOUT) {
            sleep(1);
        } else {
            if ($userId == $game->firstPlayerId) {
                $opponentId = $game->secondPlayerId;
                $userAnswer = $round->firstPlayerAnswer;
                $opponentAnswer = $round->secondPlayerAnswer;
            } else {
                $opponentId = $game->firstPlayerId;
                $userAnswer = $round->secondPlayerAnswer;
                $opponentAnswer = $round->firstPlayerAnswer;
            }
            die(toResponse(array(
                'round_status' => 'finished',
                'round_number' => $game->roundNumber,
                'question' => $round->question,
                'answers' => $round->answers,
                'correct_answer_number' => $round->correctAnswerNumber,
                'user_answer' => $userAnswer,
                'opponent_answer' => $opponentAnswer,
                'opponent_id' => $opponentId,
                'timeout_end_time' => $round->getTimeoutEndTime()
            )));
        }
    }

} catch (Exception $e) {
    die(toError($e->getMessage()));
}


