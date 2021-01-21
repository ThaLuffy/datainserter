<?php

namespace ThaLuffy\DataInserter\Traits;

use \Carbon\Carbon;
use Arr;

trait ReceivesData {
    public function getDataInserters() 
    {
        return $this->receivesFrom;
    }
}