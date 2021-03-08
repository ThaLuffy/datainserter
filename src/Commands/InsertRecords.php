<?php

namespace ThaLuffy\DataInserter\Commands;

use Illuminate\Console\Command;

use ThaLuffy\DataInserter\Monitoring;
use ThaLuffy\DataInserter\Helpers;

use \MongoDB\Driver\WriteConcern;
use \MongoDB\Driver\Manager;

class InsertRecords extends Command
{
    protected $currentAllRecordsCount;
    protected $currentTotalCount;
    protected $currentTotalDuration;
    protected $currentRecordsInserted;
    protected $jobId;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'datainserter:insert {model} {--limit=} {--from=} {--monitor} {--linked_model_limit=} {--dump-errors} {--easy-count}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert merged records into the database';
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit','1G');
        
        if (app()->runningInConsole()) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'handleCancel']);
            pcntl_signal(SIGTERM, [$this, 'handleCancel']);
        }

        $isMonitoring     = $this->option('monitor');
        $linkedModelLimit = $this->option('linked_model_limit') ? intval($this->option('linked_model_limit')) : null;
        $easyCount        = $this->option('easy-count');
        $limit            = $this->option('limit') ? intval($this->option('limit')) : 100;
        $receivingModel   = Helpers::getModelByName($this->argument('model'));
        $monitor          = new Monitoring();

        [$connectionString, 
        $connectionOptions, 
        $connectionDatabase] = Helpers::getConnectionVariables($receivingModel);
        $writeConcern        = new WriteConcern(WriteConcern::MAJORITY, 100);
        $mongoManager        = new Manager($connectionString, $connectionOptions);

        foreach ($receivingModel->getDataInserters() as $model) {
            $model     = new $model();
            $modelName = class_basename($model);
            $from      = $this->option('from') ? $this->option('from') : 0;

            $this->line("");
            $this->info("Inserting $modelName model");

            $this->info("Getting total number of records...");
            $queryBuilder                 = $model->getInserterQueryBuilder();
            $this->currentAllRecordsCount = $easyCount ? $model->count() : $queryBuilder->count();
            $this->currentTotalCount      = $easyCount ? $model->count() : $queryBuilder->when($from, fn ($q) => $q->where($model->getKeyName(), '>', $from))->count();
            $this->currentTotalDuration   = 0;
            $this->currentRecordsInserted = 0;
            $this->jobId                  = (string) \Str::uuid();

            $this->info("Total number of records: {$this->currentTotalCount}");

            do 
            {
                $monitor->clearTimers();
                $monitor->startTimer('duration');

                if ($linkedModelLimit && ($this->currentRecordsInserted >= $linkedModelLimit)) break;

                $adjustedLimit     = ($linkedModelLimit && (($this->currentRecordsInserted + $limit) > $linkedModelLimit)) ? $linkedModelLimit - $this->currentRecordsInserted : $limit;
                $params            = [ 'insert' => [], 'update' => [], 'delete' => [] ];

                $isMonitoring && $monitor->startTimer('getRecords');
                $queryBuilder = $model->getInserterQueryBuilder();

                $records = $queryBuilder
                    ->when($from, fn ($q) => $q->where($model->getKeyName(), '>', $from))
                    ->limit($limit ?? $model->getBulkSize())
                    ->orderBy($model->getKeyName(), 'asc')
                    ->get();

                $meta = $model->addMetaData($records);

                $count = count($records);
                
                $isMonitoring && $monitor->endTimer('getRecords');

                if (!$count) break;

                $isMonitoring && $monitor->startTimer('createInsertData');
                foreach ($records as $record) {
                    $insertData = $record->sendInsertData($params);
                    
                    foreach ($insertData as $type => $values) {
                        $params[$type] = array_merge($params[$type], $values);
                    }
                };
                $isMonitoring && $monitor->endTimer('createInsertData');

                $from = $records->last()->{ $model->getKeyName() };

                unset($records);
                unset($meta);

                if (!empty($params)) {
                    $isMonitoring && $monitor->startTimer('writeToDatabase');
                    $writeResults = Helpers::bulkWriteData($params, $connectionDatabase, $mongoManager, $writeConcern);
                    $isMonitoring && $monitor->endTimer('writeToDatabase');

                    unset($params);
                }
                
                $monitor->endTimer('duration');
                $duration = $monitor->getData('duration');
                
                $this->currentRecordsInserted += $count;
                $this->currentTotalDuration   += $duration;

                $actionCountString = collect($writeResults)->map(function ($value, $key) {
                    if ($value) return "$key: $value";
                    else return null;
                })->reject(fn ($value) => !$value)->implode(', ');

                $timeRemaningString = $this->__getTimeRemaningString();

                $this->comment("$modelName: $count records inserted in {$duration}s (total: {$this->currentRecordsInserted}, $actionCountString, last ID: $from, $timeRemaningString)");

                if ($isMonitoring) {
                    $this->line('<fg=white>' . collect($monitor->getOutput())->map(fn ($v, $k) => "$k: {$v}s")->implode(', ') . '</>');
                }
            }
            while ($count);
        }

        $this->line("");
        $this->info(": {$this->currentRecordsInserted} records inserted in {$this->currentTotalDuration}s");
        $this->line("");
    }

    public function handleCancel()
    {
        $multiplier        = $this->currentAllRecordsCount / $this->currentRecordsInserted;
        $timeForAllRecords = $this->currentTotalDuration * $multiplier;

        [$tHours, $tMinutes, $tSeconds] = $this->__calcTimeHMS($timeForAllRecords);
        $this->info("Estimated duration for all records: $tHours:$tMinutes:$tSeconds");

        dd("Command cancelled");
    }

    private function __getTimeRemaningString() : string
    {
        if (!$this->currentRecordsInserted)
            return "Time remaning: calculating...";

        $avgTimePerRecord  = $this->currentTotalDuration / $this->currentRecordsInserted;
        $remaningRecords   = $this->currentTotalCount - $this->currentRecordsInserted;
        $timeRemaning      = $avgTimePerRecord * $remaningRecords;

        [$hours, $minutes, $seconds] = $this->__calcTimeHMS($timeRemaning);

        return "Time remaning: $hours:$minutes:$seconds";
    }

    private function __calcTimeHMS($secToCalc) : array
    {
        $seconds    = str_pad($secToCalc % 60, 2, "0", STR_PAD_LEFT);
        $minutes    = str_pad(($secToCalc / 60) % 60, 2, "0", STR_PAD_LEFT);
        $hours      = str_pad(floor($secToCalc / 3600), 2, "0", STR_PAD_LEFT);

        return [$hours, $minutes, $seconds];
    }
}
