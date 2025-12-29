<?php

return [
    'base_url' => env('MOVACAL_BASE_URL', 'https://link.movacal.net/service/api/v1'),

    'basic' => [
        'id' => env('MOVACAL_BASIC_ID', ''),
        'password' => env('MOVACAL_BASIC_PASSWORD', ''),
    ],

    // credential認証用
    'provider' => env('MOVACAL_PROVIDER', ''),
    'secret_key' => env('MOVACAL_SECRET_KEY', ''),

    // credentialキャッシュ秒数（デフォルト: 15分 = 900秒）
    'credential_ttl' => (int) env('MOVACAL_CREDENTIAL_TTL', 900),

    'default_params_json' => env('MOVACAL_DEFAULT_PARAMS_JSON', '{}'),

    'allowed_endpoints' => [
        'getPatientlist.php',
        'getPatient.php',
        'getPatient2.php',
        'getDiaglist.php',
        'getDiagdata.php',
        'getDiagAttachement.php',
        'getExamlist.php',
        'getExamdata.php',
        'getDocslist.php',
        'getDocsdata.php',
        'getDispdocs.php',
        'getDocsmap.php',
        'getSchedule.php',
        'getFacility.php',
        'getDisease.php',
        'getVisitNurse.php',
        'getUserData.php',
        'getActcode.php',
        'getReserve.php',
        'getSummaryItem.php',
        'getHospitalization.php',
        'getOuterChecked.php',
        'getVersion.php',
        'getNursePeriod.php',
        'getFilelist.php',
        'getFile.php',
        'getFileCategory.php',
        'getNrecorddata.php',
        'getNrecordAttachment.php',
        'getPatientHistory.php',
        'getBasicOrder.php',
    ],
];
