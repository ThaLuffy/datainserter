<?php

namespace ThaLuffy\DataInserter\Traits;

use \Carbon\Carbon;
use Arr;

trait InsertsData {
    
    public function getBulkSize()
    {
        return $this->bulkSize ?? config('datainserter.default.bulkSize');
    }

    public function getInserterQueryBuilder()
    {
        return self::select('*');
    }

    public function addMetaData($iterationResults)
    {
        return null;
    }

    public function toInsertStructure($meta)
    {
        return $this;
    }


    public function sendInsertData($meta, &$currentParams)
    {
        $document = $this->toInsertStructure($meta);

        return [
            'insert'        => [ $document ],
            'update'        => [],
        ];
    }
}