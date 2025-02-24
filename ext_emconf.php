<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'NLP Tools',
    'description' => 'Natural Language Processing tools for TYPO3',
    'category' => 'services',
    'author' => 'Cywolf',
    'author_email' => '',
    'state' => 'alpha',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
            'php' => '8.2.0-8.99.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
