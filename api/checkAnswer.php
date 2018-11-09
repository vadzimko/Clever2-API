<?php
require_once "../engine/commonLongPoll.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];

    checkGameStarted($redis, $userId);
    while (true) {
        $game = getUpdatedGame($redis, $userId);
        checkFinished($game);

        if ($game->status !== GameStatus::ROUND_TIMEOUT) {
            sleep(1);
        } else {
            $round = &$game->round;
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
                'timeout_end_time' => $round->getNextRoundMilliTime()
            )));
        }
    }

} catch (Exception $e) {
    die(toError($e->getMessage()));
}


