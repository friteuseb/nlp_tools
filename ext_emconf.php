<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'NLP Tools',
    'description' => 'Natural Language Processing tools for TYPO3 with language detection, stemming, stop words filtering, and text clustering',
    'category' => 'services',
    'author' => 'Cywolf',
    'author_email' => '',
    'state' => 'beta',
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'php' => '8.1.0-8.99.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];