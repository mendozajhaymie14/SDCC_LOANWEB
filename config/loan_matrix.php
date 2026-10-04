<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SDCC Loan Matrix Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines all loan products offered by the cooperative,
    | including their maximum amounts, terms, collateral options, requirements,
    | and interest rates.
    |
    */

    'loan_types' => [
        'character'  => 'Productive/Provident - Character Loan (CL)',
        'healthcare' => 'Provident - Healthcare Loan',
        'micro'      => 'Productive - Micro Palangbayan Loan',
        'regular'    => 'Regular Productive and Provident Loan',
        'educational'=> 'Provident - Educational Loan',
        'emergency'  => 'Provident - Emergency Loan',
        'recovery'   => 'Provident - Recovery Loan (Calamity)',
        'secured'    => 'Secured Loan',
        'share_cap'  => 'Share Capital Loan',
        'special'    => 'Productive and Provident - Special Loan',
        'utility'    => 'Provident - Utility Bills Loan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Options for Dropdowns
    |--------------------------------------------------------------------------
    |
    | These options are used in the loan application form for monthly income
    | and source of income dropdowns. Keeping them here ensures consistency
    | between the view and the validation rules.
    |
    */

    'form_options' => [
        'monthly_income' => [
            ['10000',  '₱10,000 and below'],
            ['15000',  '₱10,000 – ₱15,000'],
            ['20000',  '₱15,000 – ₱20,000'],
            ['25000',  '₱20,000 – ₱25,000'],
            ['30000',  '₱25,000 – ₱30,000'],
            ['40000',  '₱30,000 – ₱40,000'],
            ['50000',  '₱40,000 – ₱50,000'],
            ['75000',  '₱50,000 – ₱75,000'],
            ['100000', '₱75,000 – ₱100,000'],
            ['150000', '₱100,000 – ₱150,000'],
            ['300000', '₱150,000 and above'],
        ],
        'source_of_income' => [
            'Salary',
            'Business / Self-employed',
            'Government salary',
            'Freelance / Independent',
            'Agriculture / Fishing',
            'Retirement / Pension',
            'Allowance / Family support',
            'Other',
        ],
    ],

    'matrix' => [
        'character' => [
            'max_amount' => 'share_capital_x2',
            'terms' => [36],
            'collateral_options' => ['None'],
            'requirements' => [
                'Amount is 50%, 100%, or 100% × 2 of share capital.',
                'With approved credit limit and within the approved DTI ratio (if applicable).',
                'No approved credit limit / No DTI ratio for the 50% option.',
            ],
            'rate' => 0.12,
        ],
        'healthcare' => [
            'max_amount' => 2000000,
            'terms' => [12],
            'collateral_options' => ['None'],
            'requirements' => [
                'Based on the Coop Healthcare Plan.',
                'With approved credit limit and within the approved DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'micro' => [
            'max_amount' => 100000,
            'terms' => [12],
            'collateral_options' => ['None', 'Cart'],
            'requirements' => [
                'P100,000.00 max; P10,000.00 if inventory-only.',
                'No loan packaging / No approved credit limit / No DTI ratio (if None).',
                'With approved credit limit and within approved DTI ratio (if Cart).',
                'With Co-op HealthCard.',
                'With accident and inventory/cart insurance.',
                'Attend Basic Entrepreneur Training (BET).',
                'Open Palawan Account (optional, but encouraged for cart maintenance).',
            ],
            'rate' => 0.12,
        ],
        'regular' => [
            'max_amount' => 'share_capital_x2',
            'terms' => [36],
            'collateral_options' => ['None', 'Chattel', 'Real Estate Mortgage'],
            'requirements' => [
                'Approved credit limit + 100% of share capital.',
                'With approved credit limit and within the approved DTI ratio.',
                'Collateral: Chattel and/or Real Estate Mortgage (REM).',
            ],
            'rate' => 0.12,
        ],
        'educational' => [
            'max_amount' => 2000000,
            'terms' => [36],
            'collateral_options' => ['Chattel', 'Real Estate Mortgage'],
            'requirements' => [
                'Purpose: payment of tuition fees, purchase of uniforms, shoes, books, and other school materials.',
                'Proof of Purpose: school registration or card, statement of account/tuition fee, or list of books/school supplies.',
            ],
            'rate' => 0.12,
        ],
        'emergency' => [
            'max_amount' => 2000000,
            'terms' => [36],
            'collateral_options' => ['Chattel', 'Real Estate Mortgage'],
            'requirements' => [
                '20% of the approved credit limit.',
                'With approved credit limit and within the approved DTI ratio.',
                'Proof of emergency purpose (e.g., hospitalization, medical care, typhoon/flood/fire).',
            ],
            'rate' => 0.12,
        ],
        'recovery' => [
            'max_amount' => 30000,
            'terms' => [24],
            'collateral_options' => ['None'],
            'requirements' => [
                '50% of Share Capital, not exceeding P30,000.00 for MIGS.',
                '50% of Share Capital, not exceeding P15,000.00 for NON-MIGS.',
                'Purpose: repair of house/vehicle, household appliances/equipment, or small-business capital.',
                'With approved credit limit and within approved DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'secured' => [
            'max_amount' => 'share_capital_plus_savings',
            'terms' => [36],
            'collateral_options' => ['100% Share Capital and Savings/Time Deposit'],
            'requirements' => [
                'Collateral: 100% Share Capital and Savings/Time Deposit.',
                'No loan packaging / No approved credit limit / No DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'share_cap' => [
            'max_amount' => 15000,
            'terms' => [12],
            'collateral_options' => ['100% Share Capital'],
            'requirements' => [
                'Amount to complete the minimum share capital of P15,000.00.',
                'No loan packaging / No approved credit limit / No DTI ratio.',
            ],
            'rate' => 0.12,
        ],
        'special' => [
            'max_amount' => 2000000,
            'terms' => [120],
            'collateral_options' => ['TCT and Machinery & Equipment'],
            'requirements' => [
                'Over and above the approved credit limit on a regular loan, not to exceed the approved DTI ratio.',
                'Proof of collateral: ownership/registration of machinery & equipment, TCT, certified true copy, CTC, tax declaration, improvement, location/vicinity maps, and certificate of no improvement.',
                'Enrolled in Planong Damayan.',
                'Payment through post-dated checks (PDC).',
            ],
            'rate' => 0.12,
        ],
        'utility' => [
            'max_amount' => 10000,
            'terms' => [12],
            'collateral_options' => ['None'],
            'requirements' => [
                'Actual billing up to a maximum of P10,000.00.',
                'With approved credit limit and within the approved DTI ratio.',
                'Statement of accounting statement of Utility Bills (electricity, water, telephone/cellphone, cable, internet).',
            ],
            'rate' => 0.12,
        ],
    ],

];