<?php

return [
    'territory' => 'nord',
    'required_period' => true,
    'expected_establishment' => 'GEIQ Eco Activités',
    'establishment_patterns' => [
        'geiq eco activites',
    ],
    'control_row_rules' => [
        [
            'match_label' => 'Frais professionnels',
            // Codes 8230, 8411, 8412, 8414 ajoutés (transport de repas, hébergement, etc.)
            'include_codes' => ['8100', '8200', '8220', '8221', '8230', '8241', '8280', '8281', '8282', '8283', '8310', '8311', '8401', '8410', '8411', '8412', '8414', '8420'],
            'exclude_codes' => ['8210'],
        ],
        [
            'match_label' => 'Partic, Patronale (TRANSPORT)',
            'include_codes' => ['8210'],
        ],
        [
            'match_label' => 'Brut soumis',
            'include_codes' => ['3200'],
        ],
        [
            'match_label' => 'Brut - fin de contrat',
            'include_codes' => ['2980', '2990', '2995', '2997', '7430', '2648'],
        ],
        [
            'match_label' => 'Base CSG',
            'include_codes' => ['6610', '6618', '6592'],
        ],
        [
            'match_label' => 'Cplt Bases Retraite',
            'include_codes' => ['3360'],
            // Code 3360 = base retraite totale, pas le complément — laisser à 0 en totalisation
            'skip_totalisation' => true,
        ],
        [
            'match_label' => 'Montant PAS',
            'include_codes' => ['8920'],
            // PAS géré séparément, ne pas écrire la valeur totalisation
            'skip_totalisation' => true,
        ],
        [
            'match_label' => 'Epargne PEE',
            'include_codes' => ['9500'],
        ],
        [
            'match_label' => 'Nb HS',
            'include_codes' => ['1225', '1250'],
            // Utiliser la colonne "base" (nombre d'heures) plutôt que le montant monétaire
            'value_field' => 'base',
        ],
        [
            'match_label' => 'Réduction cotisations',
            'include_codes' => ['6005', '6010', '6015', '6020', '6025', '6027', '6026', '6035'],
            // La réduction réelle est en part_patronale (4ème colonne), pas en montant (base de calcul)
            'value_field' => 'part_patronale',
        ],
        // Pas de règle pour "Montant Réduc /HS" → cellule C reste sans code → valeur non écrite
    ],
];
