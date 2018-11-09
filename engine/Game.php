<?php
require_once "Round.php";
require_once "GameStatus.php";
require_once "common.php";

class Game
{
    static $GAME_INFO_EXPIRE_TIME = 3600;
    static $ROUNDS_QUANTITY = 7;
    static $QUESTIONS_FILE_NAME = '../engine/questions.csv';
    static $QUESTIONS_COUNTER_NAME = 'questions_counter';

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

    /*
     * Check if game status must be updated and return true if it was
     * parameter $startRound - to start new Round of game or not
     */
    function updateStatus($startRound = false)
    {
        if ($this->status == GameStatus::FINISHED) {
            return false;
        }
        if ($this->status == GameStatus::ROUND_TIMEOUT) {
            if (milliTime() > $this->round->getNextRoundMilliTime() && $startRound
                    || $this->roundNumber == Game::$ROUNDS_QUANTITY) {

                $this->nextRound();
                return true;
            } else {
                return false;
            }
        }

        if ($this->status == GameStatus::ROUND) {
            if (milliTime() > $this->round->getNextRoundMilliTime() && $startRound) {
                $this->nextRound();
                return true;
            } elseif (milliTime() > $this->round->getRoundEndMilliTime() || $this->round->wereAnswersSent()) {
                $this->finishRound();
                return true;
            }
        }
        return false;
    }

    function finishRound()
    {
        $this->round->endTime = milliTime();
        $this->status = GameStatus::ROUND_TIMEOUT;
    }

    function nextRound()
    {
        $this->roundNumber++;
        if ($this->roundNumber > Game::$ROUNDS_QUANTITY) {
            $this->status = GameStatus::FINISHED;
            $this->round = NULL;
        } else {
            $this->status = GameStatus::ROUND;
            $this->round = new Round($this->gameQuestionsId[$this->roundNumber - 1]);
        }
    }

    private function generateQuestions()
    {
        $redis = new Predis\Client();
        $this->checkQuestionsExistence($redis);

        $questions_counter = $redis->get(Game::$QUESTIONS_COUNTER_NAME);
        $questionIdList = array();

        $step = $questions_counter / Game::$ROUNDS_QUANTITY;
        for ($i = 0; $i < Game::$ROUNDS_QUANTITY; $i++) {
            $questionIdList[$i] = rand(
                $step * $i + 1,
                $step * ($i + 1)
            );
        }
        return $questionIdList;
    }

    private function checkQuestionsExistence(Predis\Client &$redis)
    {
        if (!$redis->exists(Game::$QUESTIONS_COUNTER_NAME) || $redis->get(Game::$QUESTIONS_COUNTER_NAME) == 0) {
            $file = fopen(Game::$QUESTIONS_FILE_NAME, "r");

            if (!$file) {
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
            $redis->set(Game::$QUESTIONS_COUNTER_NAME, $index - 1);
        }

        if ($redis->get(Game::$QUESTIONS_COUNTER_NAME) < Game::$ROUNDS_QUANTITY) {
            throw new Exception('Not enough questions to create a game');
        }
    }
}

