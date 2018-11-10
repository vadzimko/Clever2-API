<?php
require_once "Round.php";
require_once "GameStatus.php";
require_once "common.php";

class Game
{
    const GAME_INFO_EXPIRE_TIME_SEC = 3600;
    const ROUNDS_QUANTITY = 7;
    const QUESTIONS_FILE_NAME = '../engine/questions.csv';
    const QUESTIONS_COUNTER = 'questions_counter';
    const QUESTIONS_LOADING = 'loading_questions_to_redis_in_process';

    var $firstPlayerId;
    var $firstPlayerScore;
    var $secondPlayerId;
    var $secondPlayerScore;

    var $roundNumber;
    var $gameQuestionsId;
    var $status;
    var $round;

    function __construct($firstPlayerId, $secondPlayerId)
    {
        try {
            $this->firstPlayerId = $firstPlayerId;
            $this->firstPlayerScore = 0;
            $this->secondPlayerId = $secondPlayerId;
            $this->secondPlayerScore = 0;

            $this->roundNumber = 0;
            $this->gameQuestionsId = $this->generateQuestions();
            $this->status = GameStatus::ROUND;
            $this->nextRound();
        } catch (Exception $e) {
            die(toError($e->getMessage()));
        }
    }

    function updateStatus($startRound = false)
    {
        if ($this->status == GameStatus::FINISHED) {
            return false;
        }

        if (milliTime() > $this->round->getNextRoundMilliTime()
            && ($startRound || $this->roundNumber == Game::ROUNDS_QUANTITY)) {

            if ($this->status == GameStatus::ROUND) {
                $this->addPoints();
            }
            $this->nextRound();
            return true;
        }

        $redis = new Predis\Client();
        if ($this->status == GameStatus::ROUND && (milliTime() > $this->round->getRoundEndMilliTime() ||
                getAnswerByUserId($redis, $this->firstPlayerId) && getAnswerByUserId($redis, $this->secondPlayerId))) {

            $this->addPoints();
            $this->finishRound();
            return true;
        }
        return false;
    }

    private function addPoints()
    {
        $redis = new Predis\Client();
        if (getAnswerByUserId($redis, $this->firstPlayerId) == $this->round->correctAnswerNumber) {
            $this->firstPlayerScore++;
        }
        if (getAnswerByUserId($redis, $this->secondPlayerId) == $this->round->correctAnswerNumber) {
            $this->secondPlayerScore++;
        }
    }

    private function finishRound()
    {
        $this->round->endTime = min(milliTime(), $this->round->startTime + Round::ROUND_DURATION_SEC * 1000);
        $this->status = GameStatus::ROUND_TIMEOUT;
    }

    private function nextRound()
    {
        $this->roundNumber++;
        if ($this->roundNumber > Game::ROUNDS_QUANTITY) {
            $this->status = GameStatus::FINISHED;
        } else {
            $this->status = GameStatus::ROUND;
            $this->round = new Round($this->gameQuestionsId[$this->roundNumber - 1], $this->firstPlayerId, $this->secondPlayerId);
        }
    }

    private function generateQuestions()
    {
        $redis = new Predis\Client();
        $this->checkQuestionsExistence($redis);

        $questionsNumber = $redis->get(Game::QUESTIONS_COUNTER);
        $questionIdList = array();

        $step = $questionsNumber / Game::ROUNDS_QUANTITY;
        for ($i = 0; $i < Game::ROUNDS_QUANTITY; $i++) {
            $questionIdList[$i] = rand(
                $step * $i + 1,
                $step * ($i + 1)
            );
        }
        return $questionIdList;
    }

    private function checkQuestionsExistence(Predis\Client &$redis)
    {
        if ($redis->exists(Game::QUESTIONS_COUNTER) && $redis->get(Game::QUESTIONS_COUNTER) > 0) {
            return;
        }

        if ($redis->get(Game::QUESTIONS_LOADING) == true) {
            while ($redis->exists(Game::QUESTIONS_LOADING) == true) {
                sleep(1);
            }
        } else {
            $redis->set(Game::QUESTIONS_LOADING, true);
            $file = fopen(Game::QUESTIONS_FILE_NAME, "r");

            if (!$file) {
                $redis->del(Game::QUESTIONS_LOADING);
                throw new Exception('Can not load questions');
            }

            $index = 0;
            while (($column = fgetcsv($file, 10000, ";")) !== FALSE) {
                if ($index > 0 && sizeof($column) >= 5) {
                    $redis->set("question" . $index, $column[0]);
                    $redis->lpush("answers" . $index, array($column[1], $column[2], $column[3]));
                    $redis->set("theme" . $index, $column[4]);
                }
                $index++;
            }
            if ($index < Game::ROUNDS_QUANTITY) {
                $redis->flushall();
                throw new Exception('Not enough questions to create a game');
            }
            $redis->set(Game::QUESTIONS_COUNTER, $index - 1);
            $redis->del(Game::QUESTIONS_LOADING);
        }
    }
}

