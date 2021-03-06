<?php
require_once "common.php";

class Round
{
    const ROUND_DURATION_SEC = 12;
    const TIMEOUT_BETWEEN_ROUNDS_SEC = 5;

    var $question;
    var $answers;
    var $theme;
    var $correctAnswerNumber;

    var $startTime;
    var $endTime;

    public function __construct($questionId, $firstPlayerId, $secondPlayerId)
    {
        try {
            $redis = new Predis\Client();

            $this->question = $redis->get("question" . $questionId);
            $this->answers = $redis->lrange("answers" . $questionId, 0, -1);
            $this->theme = $redis->get("theme" . $questionId);

            $correctAnswer = $this->answers[0];
            shuffle($this->answers);
            for ($i = 0; $correctAnswer !== $this->answers[$i]; $i++) ;
            $this->correctAnswerNumber = $i + 1;

            $this->startTime = milliTime();
            $this->endTime = $this->startTime + Round::ROUND_DURATION_SEC * 1000;

            setAnswerByUserId($redis, $firstPlayerId, 0);
            setAnswerByUserId($redis, $secondPlayerId, 0);
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    function getNextRoundMilliTime() {
        return $this->endTime + Round::TIMEOUT_BETWEEN_ROUNDS_SEC * 1000;
    }
}
