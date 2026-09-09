<?php
return [
    'routes' => [
        [
            'name' => 'page#index',
            'url' => '/',
            'verb' => 'GET',
        ],
        [
            'name' => 'access#me',
            'url' => '/api/access',
            'verb' => 'GET',
        ],
        [
            'name' => 'submission#index',
            'url' => '/api/submissions',
            'verb' => 'GET',
        ],
        [
            'name' => 'submission#create',
            'url' => '/api/submissions',
            'verb' => 'POST',
        ],
        [
            'name' => 'submission#show',
            'url' => '/api/submissions/{id}',
            'verb' => 'GET',
        ],
        [
            'name' => 'submission#update',
            'url' => '/api/submissions/{id}',
            'verb' => 'PUT',
        ],
        [
            'name' => 'submission#submit',
            'url' => '/api/submissions/{id}/submit',
            'verb' => 'POST',
        ],
    ],
];