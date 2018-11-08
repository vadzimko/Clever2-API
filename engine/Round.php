<?php
require_once "../predis/autoload.php";
Predis\Autoloader::register();

class Round
{
    static $ROUND_DURATION = 12;
    static $TIMEOUT_BETWEEN_ROUNDS = 5;

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

            $this->startTime = time();
            $this->endTime = time() + 1000000;
            $this->firstPlayerAnswer = 0;
            $this->secondPlayerAnswer = 0;
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    function getRoundEndTime() {
        return $this->startTime + Round::$ROUND_DURATION;
    }

    function getTimeoutEndTime() {
        return $this->endTime + Round::$TIMEOUT_BETWEEN_ROUNDS;
    }
}
