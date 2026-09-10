<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'access#me', 'url' => '/api/access', 'verb' => 'GET'],
        ['name' => 'audit#index', 'url' => '/api/audit', 'verb' => 'GET'],
        ['name' => 'report#overview', 'url' => '/api/reports/overview', 'verb' => 'GET'],
        ['name' => 'formAdmin#index', 'url' => '/api/forms/admin', 'verb' => 'GET'],
        ['name' => 'formAdmin#update', 'url' => '/api/forms/admin/{formId}', 'verb' => 'PUT'],
        ['name' => 'patient#index', 'url' => '/api/patients', 'verb' => 'GET'],
        ['name' => 'patient#create', 'url' => '/api/patients', 'verb' => 'POST'],
        ['name' => 'patient#show', 'url' => '/api/patients/{id}', 'verb' => 'GET'],
        ['name' => 'submission#index', 'url' => '/api/submissions', 'verb' => 'GET'],
        ['name' => 'submission#create', 'url' => '/api/submissions', 'verb' => 'POST'],
        ['name' => 'submission#show', 'url' => '/api/submissions/{id}', 'verb' => 'GET'],
        ['name' => 'submission#update', 'url' => '/api/submissions/{id}', 'verb' => 'PUT'],
        ['name' => 'submission#submit', 'url' => '/api/submissions/{id}/submit', 'verb' => 'POST'],
    ],
];