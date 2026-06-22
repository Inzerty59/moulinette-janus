<?php

return [
    'territory' => 'idf',
    'required_period' => true,
    'expected_establishment' => 'GEIQ Eco Activités IDF',
    'establishment_patterns' => [
        'geiq eco activites idf',
    ],
    'control_row_rules' => [
        [
            'match_label' => 'Frais professionnels',
            'include_codes' => ['8100', '8200', '8220', '8221', '8230', '8241', '8280', '8281', '8282', '8283', '8310', '8311', '8401', '8410', '8411', '8412', '8414', '8420'],
            'exclude_codes' => ['8210'],
        ],
        [
            'match_label' => 'Réduction cotisations',
            'include_codes' => ['6005', '6010', '6015', '6020', '6025', '6027', '6026', '6035'],
            'value_field' => 'part_patronale',
        ],
        [
            'match_label' => 'Cplt Bases Retraite',
            'include_codes' => ['3360'],
            'skip_totalisation' => true,
        ],
        [
            'match_label' => 'Montant PAS',
            'include_codes' => ['8920'],
            'skip_totalisation' => true,
        ],
    ],
];
