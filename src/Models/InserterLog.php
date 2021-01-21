<?php

namespace ThaLuffy\DataInserter\Models;

use Illuminate\Database\Eloquent\Model;

class InserterLog extends Model
{
    protected $fillable = [
        'status',
        'type',
        'receiving_model',
        'inserting_model',
        'job_id',
    ];
}
