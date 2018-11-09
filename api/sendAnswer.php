<?php
require_once "../engine/common.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];
    $answerFromRequest = $_REQUEST["answer"];

    checkUserId($userId);
    if (!$answerFromRequest || !(1 <= $answerFromRequest && $answerFromRequest <= 3)) {
        die(toError('Request must contain parameter \'answer\' - integer from [1; 3]'));
    } elseif (!$redis->exists(toUserGameId($userId))) {
        die(toError('User has not started a game'));
    }

    $game = getGameByUserId($redis, $userId);
    $status = $game->status;
    if ($status == GameStatus::FINISHED) {
        die(toError('Game has been finished already'));
    } elseif ($status == GameStatus::ROUND_TIMEOUT) {
        die(toError('Round has been finished and new round has not been started'));
    }

    $round = &$game->round;
    if (milliTime() > $round->getRoundEndMilliTime()) {
        die(toError('Round has been finished, too late to send answer'));
    }

    if ($userId == $game->firstPlayerId) {
        $userAnswer = &$round->firstPlayerAnswer;
        $userScore = &$game->firstPlayerScore;
    } else {
        $userAnswer = &$round->secondPlayerAnswer;
        $userScore = &$game->secondPlayerScore;
    }

    if ($userAnswer !== 0) {
        die(toError('This player has sent answer in this round already'));
    } else {
        $userAnswer = $answerFromRequest;
        if ($userAnswer == $round->correctAnswerNumber) {
            $userScore += 1;
        }

        saveGame($redis, $game);
        die(toResponse(array(
            'comment' => 'answer have been accepted'
        )));
    }
} catch (Exception $e) {
    die(toError($e->getMessage()));
}