<?php

namespace TimeEstimation;

class Result
{
    public $warnings = [];
    public $log = [];
    /** @var Result\Totals */
    public $totals;
    public $codeReviewStarts = 0;
    public $codeReviewStartsRounded = 0;
    public $codeReviewFinishes = 0;
    public $codeReviewFinishesRounded = 0;
    public $testingStarts = 0;
    public $testingStartsRounded = 0;
    public $betaCheckStarts = 0;
    public $betaCheckStartsRounded = 0;
    public $type = null;
    public $precision = null;

    public function __construct()
    {
        $this->totals = new Result\Totals();
    }
}