<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Models folder
    |--------------------------------------------------------------------------
    |
    | Register the folder you want to use for your models. These models get
    | autoloaded so you won't have register them explicitly.
    |
	*/
    
    'model_folders' => [
        'Models'
    ],


    /*
    |--------------------------------------------------------------------------
    | Custom inserter logs model
    |--------------------------------------------------------------------------
    |
    | Define a custom inserter log model.
    |
	*/
    
    'custom_log_model' => null,

	/*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Write message here
    |
	*/
	
	'default' => [
		'bulkSize' => 100,
	],
];