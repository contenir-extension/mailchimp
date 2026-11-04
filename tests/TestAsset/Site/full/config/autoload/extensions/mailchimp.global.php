<?php

declare(strict_types=1);

return [
    'extensions' => [
        'mailchimp' => [
            'bindings' => [
                'newsletter' => ['list' => 'a1b2c3d4e5f6', 'fields' => ['Name' => 'name'], 'consent' => 'Signing up'],
                'members'    => [
                    'list'    => 'members-list',
                    'source'  => 'library.members.member',
                    'consent' => 'Members who tick Newsletter',
                    'options' => ['consent_field' => 'newsletter'],
                ],
            ],
        ],
    ],
];
