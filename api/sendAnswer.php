<?php
require_once "../engine/common.php";

try {
    $redis = new Predis\Client();
    $userId = $_REQUEST["user_id"];
    $answerFromRequest = $_REQUEST["answer"];
    $answerRoundNumber = $_REQUEST["round_number"];

    checkUserId($userId);
    if ($answerFromRequest == NULL || !(1 <= $answerFromRequest && $answerFromRequest <= 3)) {
        die(toError('Request must contain parameter \'answer\' - integer from [1; 3]'));
    } elseif ($answerRoundNumber == NULL || !(1 <= $answerRoundNumber && $answerRoundNumber <= Game::ROUNDS_QUANTITY)) {
        die(toError('Request must contain parameter \'round_number\'- integer from [1; ' . Game::ROUNDS_QUANTITY . ']'));
    }

    $game = getUpdatedGame($redis, $userId);
    if (!$game) {
        die(toError('User has not started a game'));
    }

    $status = $game->status;
    if ($status == GameStatus::FINISHED) {
        die(toError('Game has been finished already'));
    } elseif ($status == GameStatus::ROUND_TIMEOUT) {
        die(toError('Round has been finished and new round has not been started'));
    } elseif ($answerRoundNumber != $game->roundNumber) {
        die(toError('Answer was sent too late, new round has been started already'));
    }

    $round = &$game->round;
    if ($userId == $game->firstPlayerId) {
        $userScore = &$game->firstPlayerScore;
    } else {
        $userScore = &$game->secondPlayerScore;
    }

    if (getAnswerByUserId($redis, $userId) != 0) {
        die(toError('This player has sent answer in this round already'));
    } else {
        setAnswerByUserId($redis, $userId, $answerFromRequest);
        if ($answerFromRequest == $round->correctAnswerNumber) {
            $userScore++;
        }

        saveGame($redis, $game);
        die(toResponse(array(
            'comment' => 'answer has been accepted'
        )));
    }
} catch (Exception $e) {
    die(toError($e->getMessage()));
}