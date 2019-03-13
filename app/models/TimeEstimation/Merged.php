<?php

namespace TimeEstimation;

class Merged extends \TimeEstimation
{
    /** @var Merged\Result $result */
    public $result;

    public function __construct(\TimeEstimation $te1, \TimeEstimation $te2)
    {
        $this->_construct('');
        $this->fillInWithZeros();
        foreach ($this->timeLines as $k => &$timeLine) {
            $timeLine['min'] = $te1->timeLines[$k]['min'] + $te2->timeLines[$k]['min'];
            $timeLine['max'] = $te1->timeLines[$k]['max'] + $te2->timeLines[$k]['max'];
        }
        $this->timeLines['already spent']['max'] = $te1->alreadySpent + $te2->alreadySpent;
        $this->calculate();

        $this->result->dev1CodeReviewStarts = $te1->result->codeReviewStarts;
        $this->result->dev1CodeReviewStartsRounded = $this->round($this->result->dev1CodeReviewStarts);
        $this->result->dev2Starts = round(
            $te1->result->codeReviewFinishes + $te1->getLineNorm('transferring to qa'),
            1
        );
        $this->result->dev2StartsRounded = $this->round($this->result->dev2Starts);
        $this->result->dev2CodeReviewStarts = $this->result->dev2Starts + $te2->result->codeReviewStarts;
        $this->result->dev2CodeReviewStartsRounded = $this->round($this->result->dev2CodeReviewStarts);
    }

    protected function _construct($text)
    {
        parent::_construct($text);
        $this->result = new Merged\Result();
    }

}