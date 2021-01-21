<?php

namespace ThaLuffy\DataInserter;

use ThaLuffy\DataInserter\Models\InserterLog;
use Symfony\Component\Finder\Finder;
use \MongoDB\Driver\BulkWrite;

use Str;

class Helpers
{
    public static function getInserterLogModel()
    {
        $model = InserterLog::class;

        if ($customModel = config('datainserter.custom_log_model'))
            $model = $customModel;

        return $model;
    }

    public static function getModelByName($modelName)
    {
        $modelFolders = config('datainserter.model_folders');

        foreach ($modelFolders as $folder) {
            $modelsInFolder = self::modelsIn(app_path($folder));

            foreach ($modelsInFolder as $modelPath) {
                if ($model = self::__matchModel($modelName, $modelPath)) {
                    $classTraits = array_map(fn ($value) => class_basename($value), class_uses($model));
                            
                    if (!in_array('ReceivesData', $classTraits)) {
                        throw new \Exception("Model doesn't use the ReceivesData trait.");
                    }

                    return $model;
                }
            }
        }

        throw new \Exception('Model not found');
    }

    public static function getConnectionVariables($model)
    {
        $config  = $model->getConnection()->getConfig();
        $options = [];
        
        !empty($config['username'])            && ($options['username'] = $config['username']);
        !empty($config['password'])            && ($options['password'] = $config['password']);
        !empty($config['options']['database']) && ($options['authSource'] = $config['options']['database']);
        
        return [
            "mongodb://{$config['host']}:{$config['port']}",
            $options,
            "{$config['database']}.{$model->getTable()}"
        ];
    }

    public static function bulkWriteData($params, $connectionDatabase, $mongoManager, $writeConcern)
    {
        try {   
            $bulk   = new BulkWrite(['ordered' => true]);
            $counts = [             
                'documents' => 0,
                'inserted'  => 0,
                'updated'   => 0,
                'deleted'   => 0,
                'skipped'   => 0,
            ];
                    
            foreach ($params as $actionType => $documents) {
                foreach ($documents as $doc) {
                    switch ($actionType) {
                        case 'insert':
                            $bulk->insert($doc);
                            break;

                        case 'update':
                            $bulk->update(['_id' => $doc['_id'] ], [ '$set' => $doc ]);
                            break;
                    }
                }
            }

            if ($bulk->count()) {
                $result              = $mongoManager->executeBulkWrite($connectionDatabase, $bulk, $writeConcern);     

                $counts['documents'] = $bulk->count();
                $counts['inserted']  = $result->getInsertedCount();
                $counts['updated']   = $result->getModifiedCount() + $result->getUpsertedCount();
                $counts['deleted']   = $result->getDeletedCount();
                $counts['skipped']   = ($result->getMatchedCount() - $counts['updated']);
            }

            return $counts;
        } 
        catch (\Throwable $th) {
            throw $th;
        }
    }

    public static function modelsIn($directory)
    {
        $namespace = app()->getNamespace();

        $models = [];

        foreach ((new Finder)->in($directory)->files() as $model) {
            $models[] = $namespace.str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($model->getPathname(), app_path().DIRECTORY_SEPARATOR)
            );
        }

        return $models;
    }

    private static function __matchModel($modelName, $modelPath)
    {
        $model = new $modelPath();

        if ($modelName == class_basename($model))
            return $model;

        return null;
    }
}