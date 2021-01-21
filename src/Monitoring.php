<?php

namespace ThaLuffy\DataInserter;

class Monitoring
{
    protected $__runningTimers = [];
    protected $__outputData    = [];

    public function __construct()
    {
    }

    public function startTimer(string $name)
    {
        if (isset($this->__runningTimers[$name])) throw new \Exception("The $name timer has already been started");

        $this->__runningTimers[$name] = microtime(true);
    }

    public function endTimer(string $name)
    {
        if (!isset($this->__runningTimers[$name])) throw new \Exception("Please start the $name timer first");

        $this->__outputData[$name] = round(microtime(true) - $this->__runningTimers[$name], 3);

        unset($this->__runningTimers[$name]);
    }

    public function getData(string $name)
    {
        if (!isset($this->__outputData[$name])) throw new \Exception("Please add data first");

        return $this->__outputData[$name];
    }

    public function getOutput()
    {
        return $this->__outputData;
    }

    public function clearTimers()
    {
        $this->__runningTimers = [];
        $this->__outputData    = [];
    }
}