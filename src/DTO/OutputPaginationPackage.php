<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\CommunicationClientPackage;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class OutputPaginationPackage
{
    #[ApiProperty(
        default: 0,
        openapiContext: [
            'type' => 'integer',
        ]
    )]
    #[Groups(['comPackage:read'])]
    #[Assert\PositiveOrZero]
    public int $page;

    #[ApiProperty(
        default: 10,
        openapiContext: [
            'type' => 'integer',
            'enum' => [10, 25, 50, 100],
        ]
    )]
    #[Groups(['comPackage:read'])]
    #[Assert\PositiveOrZero]
    public int $itemsPerPage;

    #[ApiProperty(
        default: 0,
        openapiContext: [
            'type' => 'integer',
        ]
    )]
    #[Groups(['comPackage:read'])]
    #[Assert\PositiveOrZero]
    public int $pages;

    #[ApiProperty(
        default: 0,
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' =>[
                        'readOnly' => true,
                        'type' => 'integer',
                        'summary' => 'Package ID',
                        'require' => true,
                    ],
                    'name' => [
                        'type' => 'string',
                        'summary' => 'Package Name',
                    ],
                    'description' => [
                        'type' => 'string',
                        'summary' => 'Package Description',
                    ],
                    'activeStartAt' => [
                        'type' => 'string',
                        'format' => 'date-time',
                    ],
                    'activeEndAt' => [
                        'type' => 'string',
                        'format' => 'date-time',
                        'default' => null,
                        'nullable' => true,
                    ],
                    'benefits' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'additional_information' => [
                                    'type' => 'string',
                                ],
                                'amount' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'base' => [
                                            'type' => 'integer',
                                        ],
                                        'promotion_bonus' => [
                                            'type' => 'integer',
                                        ],
                                        'total_excluding_tax' => [
                                            'type' => 'integer',
                                        ],
                                        'total_including_tax' => [
                                            'type' => 'integer',
                                        ]
                                    ]
                                ],
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['CREDITS', 'TALKTIME', 'DATA', 'SMS']
                                ],
                                'unit' => [
                                    'type' => 'string',
                                    'enum' => ['CUP', 'USD', 'UNITS', 'MINUTES', 'GB', 'ILIM']
                                ],
                                'unit_type' => [
                                    'type' => 'string',
                                    'enum' => ['CURRENCY', 'QUANTITY', 'DATA', 'TIME']
                                ],
                                'schedule' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'start' => [
                                            'type' => 'string',
                                        ],
                                        'end' => [
                                            'type' => 'string',
                                            'nullable' => true,
                                            'default' => null,
                                        ]
                                    ],
                                    'default' => null,
                                    'nullable' => true,
                                ]
                            ]
                        ]
                    ],
                    'tags' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET']
                        ],
                    ],
                    'service' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'enum' => ['Mobile', 'uSIM', 'Devices']
                            ],
                            'subservice' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'enum' => ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'uSIM']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'destination' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'format' => 'currency-number',
                            ],
                            'unit' => [
                                'type' => 'string',
                                'enum' => ['CUP', 'MLC', 'USD'],
                                'types' => ['https://schema.org/priceCurrency']
                            ],
                            'unit_type' => [
                                'type' => 'string',
                                'enum' => ['CURRENCY']
                            ]
                        ]
                    ],
                    'validity' => [
                        'type' => 'object',
                        'properties' => [
                            'quantity' => [
                                'type' => 'integer',
                            ],
                            'unit' => [
                                'type' => 'string',
                                'enum' => ['DAYS', 'MONTH', 'YEAR'],
                            ]
                        ],
                        'nullable' => true,
                        'default' => null,
                    ]
                ]
            ],
        ],

    )]
    #[Groups(['comPackage:read'])]
    public array $results;

    /**
     * @param int $page
     * @param int $itemsPerPage
     * @param int $pages
     * @param array $results
     */
    public function __construct(int $page, int $itemsPerPage, int $pages, array $results)
    {
        $this->page = $page;
        $this->itemsPerPage = $itemsPerPage;
        $this->pages = $pages;
        $this->results = $results;
    }
}
