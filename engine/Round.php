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
    var $firstPlayerAnswer;
    var $secondPlayerAnswer;

    public function __construct($questionId)
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
            $this->endTime = milliTime() + 1000000000;
            $this->firstPlayerAnswer = 0;
            $this->secondPlayerAnswer = 0;
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    function wereAnswersSent() {
        return $this->firstPlayerAnswer && $this->secondPlayerAnswer;
    }

    function getRoundEndMilliTime() {
        return $this->startTime + Round::ROUND_DURATION_SEC * 1000;
    }

    function getNextRoundMilliTime() {
        return min($this->startTime + (Round::ROUND_DURATION_SEC + Round::TIMEOUT_BETWEEN_ROUNDS_SEC) * 1000,
                    $this->endTime + Round::TIMEOUT_BETWEEN_ROUNDS_SEC * 1000);
    }
}
