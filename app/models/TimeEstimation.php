<?php

class TimeEstimation
{
    protected $config = [
        'transferring' => [
            'beforeTesting' => 1,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'platform learning' => [
            'beforeTesting' => 1,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'implementation' => [
            'beforeTesting' => 1,
            'beforeBetaCheck' => 1,
            'min' => 0.5,
            'allowPercent' => false,
            'percFor' => [
                'internal reworks' => 1,
                'external reworks' => 1,
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'code review' => [
            'beforeTesting' => 1,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'code review reworks' => [
            'beforeTesting' => 1,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'transferring to qa' => [
            'beforeTesting' => 1,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'assistance to qa' => [
            'beforeTesting' => 0,
            'beforeBetaCheck' => 1,
            'min' => 0.1,
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'internal reworks' => [
            'beforeTesting' => 0,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'allowPercent' => true,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'pass to beta' => [
            'beforeTesting' => 0,
            'beforeBetaCheck' => 1,
            'min' => 0,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'external reworks' => [
            'beforeTesting' => 0,
            'beforeBetaCheck' => 0,
            'min' => 0,
            'allowPercent' => true,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'deployments' => [
            'beforeTesting' => 0.4, // several alpha deployments where the first is most heavy
            'beforeBetaCheck' => 0.7,
            'min' => 0,// e.g. internal modules, there deployments are estimated for a version
            'allowPercent' => false,
            'percFor' => [
                'overhead' => 1,
                'missed points' => 1,
            ],
        ],
        'overhead' => [
            'beforeTesting' => 0.6,
            'beforeBetaCheck' => 0.9,
            'min' => 0,
            'allowPercent' => true,
            'percFor' => [
                'missed points' => 1,
            ],
        ],
        'missed points' => [
            'beforeTesting' => 0.5,
            'beforeBetaCheck' => 0.6,
            'min' => 0,
            'allowPercent' => true,
        ],
        'already spent' => [
            'beforeTesting' => 0,
            'beforeBetaCheck' => 0,
            'min' => 0,
            'allowPercent' => false,
        ],
    ];
    protected $floatRx = '[0-9]+(\.[0-9]+)?';
    protected $timeExprRx = null; // see construct
    protected $percExprRx = '~(?:\s)\+([0-9]+)%(?:\s|$)~';
    public $text = null;
    public $timeLines = [];
    public $alreadySpent = [];
    /** @var TimeEstimation\Result $result */
    public $result;
    protected $precisionThreshold = 1.5;
    protected $defaultTimeLine = ['min' => 0, 'max' => 0, 'perc' => 0];

    public function __construct($text)
    {
        $this->_construct($text);
        $this->parse();
        $this->validate();
        $this->calculate();
    }

    protected function _construct($text)
    {
        $this->timeExprRx = "~(?:\s)(?:(({$this->floatRx})(m|h|d))|(($this->floatRx)-({$this->floatRx})(m|h|d)))(?:\s|$)~";
        $this->text = $text;
        $this->result = new TimeEstimation\Result();
    }

    protected function calculate()
    {
        $this->fillInWithZeros();
        // excludeAlreadySpent from time lines to simplify further calculations
        $this->excludeAlreadySpent();
        $this->calculateMinMax();
        $this->calculateTotals();
        $this->calculateMilestones();
        $this->defineType();
        $this->roundResults();
    }

    protected function parse()
    {
        $text = $this->text;
        $text = preg_replace('~ +~', ' ', $text);
        $text = strtolower($text);
        $lines = explode("\n", $text);
        $topLine = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            $line = preg_replace('~#.*$~', '', $line);
            $lineKey = trim(preg_replace([$this->timeExprRx, $this->percExprRx], '', $line));
            $isSubLine = false;
            if (preg_match('~^[^\s]~', $line)) {
                $topLine = $lineKey;
            } else {
                $isSubLine = true;
            }
            $matches = [];
            if (!preg_match($this->timeExprRx, $line, $matches)) {
                if (preg_match($this->percExprRx, $line, $matches)) {
                    if ($isSubLine) {
                        error('Percentages aren\'t supported for sublines, see "' . $line . '"');
                    }
                    $this->timeLines[$topLine] = $this->defaultTimeLine;
                    $this->timeLines[$topLine]['perc'] = $matches[1];
                }
                continue;
            }

            if (preg_match($this->percExprRx, $line)) {
                error('Percentage and time range can\'t be set together, see "' . $line . '"');
            }

            if (isset($matches[8])) {
                $min = $matches[6];
                $max = $matches[8];
                if ($min > $max) {
                    error("Max can't be less than min, please fix it for line: \"$line\"");
                }
                $unit = $matches[10];
            } else {
                $min = $max = $matches[2];
                $unit = $matches[4];
            }

            switch ($unit) {
                case 'm':
                    $rate = 1 / 60;
                    break;
                case 'h':
                    $rate = 1;
                    break;
                case 'd':
                    $rate = 8;
                    break;
                default:
                    error('Unknown unit "' . $unit . '"');
            }
            $min *= $rate;
            $max *= $rate;

            if ($isSubLine && empty($topLine)) {
                error("Parent line for \"" . html2text($line) . "\" not found");
            }
            if (!isset($this->timeLines[$topLine])) {
                $this->timeLines[$topLine] = $this->defaultTimeLine;
            }
            $this->timeLines[$topLine]['min'] += $min;
            $this->timeLines[$topLine]['max'] += $max;
        }
    }

    protected function validate()
    {
        foreach ($this->config as $key => $line) {
            if (!isset($this->timeLines[$key])) {
                $this->result->warnings[] = "Missed time line \"$key\"";
            } else {
                if ($this->timeLines[$key]['min'] < $line['min']) {
                    $this->result->warnings[] = "Time for \"$key\" can't be less than {$line['min']}h";
                }
                if (empty($this->timeLines[$key]['min']) && !empty($this->timeLines[$key]['perc']) && !$line['allowPercent']) {
                    error("Percent isn't allowed for \"$key\"");
                }
            }
        }
        $maxCodeReview = $maxCodeReviewReworks = 0;
        if (isset($this->timeLines['code review'])) {
            $maxCodeReview = $this->timeLines['code review']['max'];
        }
        if (isset($this->timeLines['code review reworks'])) {
            $maxCodeReviewReworks = $this->timeLines['code review reworks']['max'];
        }
        if ((bool)$maxCodeReview !== (bool)$maxCodeReviewReworks) {
            error('Both "Code review" and "Code review reworks" should be zero or have value');
        }
    }

    protected function fillInWithZeros()
    {
        foreach ($this->config as $key => $line) {
            if (!isset($this->timeLines[$key])) {
                $this->timeLines[$key] = $this->defaultTimeLine;
            }
        }
    }

    protected function excludeAlreadySpent()
    {
        $this->alreadySpent = $this->timeLines['already spent']['max'];
        $this->timeLines['already spent'] = $this->defaultTimeLine;
    }

    protected function calculateMinMax()
    {
        $baseMin = $baseMax = 0;
        foreach ($this->timeLines as $key => $line) {
            if (empty($line['perc'])) {
                $baseMin += $line['min'];
                $baseMax += $line['max'];
            }
        }
        $this->result->log[] = "Base range, excluding percentages and already spent is "
            . round($baseMin, 1)
            . '-' . round($baseMax, 1) . 'h';

        foreach ($this->timeLines as $timeLineKey => &$timeLine) {
            if (empty($timeLine['perc'])) {
                continue;
            }
            $min = $max = 0;
            $guilty = [];
            foreach ($this->config as $configKey => $lineConfig) {
                if (empty($lineConfig['percFor'][$timeLineKey])) {
                    continue;
                }
                $min += $this->timeLines[$configKey]['min'];
                $max += $this->timeLines[$configKey]['max'];
                $guilty[] = $configKey;
            }
            $timeLine['min'] = $min * $timeLine['perc'] / 100;
            $timeLine['max'] = $max * $timeLine['perc'] / 100;

            $guiltyExcept = [];
            foreach ($this->timeLines as $k => $v) {
                if (!in_array($k, $guilty) && $k !== $timeLineKey) {
                    $guiltyExcept[] = $k;
                }
            }
            if (count($guilty) < count($guiltyExcept)) {
                $guiltyStr = 'percent taken from "' . implode('", "', $guilty) . '"';
            } else {
                if (count($guiltyExcept) !== 0) {
                    $guiltyStr = 'percent taken from all the points except "' . implode('", "', $guiltyExcept) . '"';
                } else {
                    $guiltyStr = 'percent taken from all the points';
                }
            }
            if (!empty($min)) {
                $this->result->log[] = "+{$timeLine['perc']}% for $timeLineKey = +"
                    . round($timeLine['min'], 1) . '-' . round($timeLine['max'], 1) . 'h'
                    . ", $guiltyStr";
            }
        }
    }

    protected function calculateTotals()
    {
        $totals = $this->result->totals;
        foreach ($this->timeLines as $key => $line) {
            $totals->min += $line['min'];
            $totals->max += $line['max'];
        }
        $totals->min = round($totals->min, 1);
        $totals->max = round($totals->max, 1);
        $totals->norm = round($this->getNorm($totals->min, $totals->max), 1);
        $totals->grand = round($totals->norm + $this->alreadySpent, 1);
    }

    protected function calculateMilestones()
    {
        $reviewStarts = $reviewFinishes = $testingStarts = $betaCheckStarts = 0;
        foreach ($this->config as $key => $lineConf) {
            $beforeTestingHours = $this->getLineNorm($key) * $lineConf['beforeTesting'];
            $beforeBetaCheckHours = $this->getLineNorm($key) * $lineConf['beforeBetaCheck'];
            if (!in_array($key, ['code review', 'code review reworks', 'transferring to qa', 'deployments'])) {
                $reviewStarts += $beforeTestingHours;
            }
            if (!in_array($key, ['transferring to qa', 'deployments'])) {
                $reviewFinishes += $beforeTestingHours;
            }
            $testingStarts += $beforeTestingHours;
            $betaCheckStarts += $beforeBetaCheckHours;
        }
        $this->result->codeReviewStarts = round($reviewStarts, 1);
        $this->result->codeReviewFinishes = round($reviewFinishes, 1);
        $this->result->testingStarts = round($testingStarts, 1);
        $this->result->betaCheckStarts = round($betaCheckStarts, 1);
    }

    protected function round($float)
    {
        if ($float < 0.5) {
            return 0.5;
        }
        if ($float < 5) {
            $roundTo = 0.2;
        } elseif ($float < 10) {
            $roundTo = 0.5;
        } elseif ($float < 20) {
            $roundTo = 1;
        } elseif ($float < 50) {
            $roundTo = 2;
        } elseif ($float < 100) {
            $roundTo = 5;
        } elseif ($float < 200) {
            $roundTo = 10;
        } elseif ($float < 500) {
            $roundTo = 20;
        } else {
            $roundTo = 100;
        }
        return floor(($float + $roundTo - 0.00001) / $roundTo) * $roundTo;
    }

    protected function getNorm($min, $max)
    {
        return ($min + $max * 2) / 3;
    }

    protected function getLineNorm($lineKey)
    {
        return $this->getNorm($this->timeLines[$lineKey]['min'], $this->timeLines[$lineKey]['max']);
    }

    protected function defineType()
    {
        $min = $this->result->totals->min;
        $max = $this->result->totals->max;
        if ((int)$min === 0) {
            error("Min TE total can't be zero");
        }
        $precision = $max / $min;
        $isPrecise = $precision <= $this->precisionThreshold;
        $this->result->type = $isPrecise ? 'Complete TE' : 'Rough TE';
        $this->result->precision = round($precision, 2);
        $this->result->log[] = "TE type = \"{$this->result->type}\" because of precision "
            . round($precision, 2)
            . ($isPrecise ? " <= $this->precisionThreshold" : " > $this->precisionThreshold");
    }

    protected function roundResults()
    {
        $result = $this->result;
        $result->totals->minRounded = $this->round($result->totals->min);
        $result->log[] = "Total min {$result->totals->min} is rounded to {$result->totals->minRounded}";
        $result->totals->maxRounded = $this->round($result->totals->max);
        $result->log[] = "Total max {$result->totals->max} is rounded to {$result->totals->maxRounded}";
        $result->totals->normRounded = $this->round($result->totals->norm);
        $result->log[] = "Total {$result->totals->norm} is rounded to {$result->totals->normRounded}";
        $result->totals->grandRounded = $this->round($result->totals->grand);
        $result->log[] = "Total incl. already spent {$result->totals->grand} is rounded to {$result->totals->grandRounded}";
        if ($this->timeLines['code review']['max'] > 0) {
            $result->codeReviewStartsRounded = $this->round($result->codeReviewStarts);
            $result->log[] = "Code review starts at {$result->codeReviewStarts} is rounded to {$result->codeReviewStartsRounded}";
            $result->codeReviewFinishesRounded = $this->round($result->codeReviewFinishes);
        }
        $result->testingStartsRounded = $this->round($result->testingStarts);
        $result->log[] = "Alpha test starts at {$result->testingStarts} is rounded to {$result->testingStartsRounded}";
        $result->betaCheckStartsRounded = $this->round($result->betaCheckStarts);
        $result->log[] = "Beta check starts at {$result->betaCheckStarts} is rounded to {$result->betaCheckStartsRounded}";
    }

}