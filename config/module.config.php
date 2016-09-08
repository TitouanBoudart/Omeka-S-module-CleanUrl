<?php
return [
    'controllers' => [
        'factories' => [
            'CleanUrl\Controller\Index' => 'CleanUrl\Service\Controller\IndexControllerFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'getResourceFromIdentifier' => 'CleanUrl\Service\ViewHelper\GetResourceFromIdentifierFactory',
            'getResourceFullIdentifier' => 'CleanUrl\Service\ViewHelper\GetResourceFullIdentifierFactory',
            'getResourceTypeIdentifiers' => 'CleanUrl\Service\ViewHelper\GetResourceTypeIdentifiersFactory',
            'getResourceIdentifier' => 'CleanUrl\Service\ViewHelper\GetResourceIdentifierFactory',
        ],
    ],
];
