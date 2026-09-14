<?php

return [
    'mode' => env('WORKFLOW_UAT_AUTHORITY_MODE'),
    'version' => 'gate9.ordinary-mayoral-authorization.v1',
    'mayor_assignment_id' => (int) env('WORKFLOW_UAT_MAYOR_ASSIGNMENT_ID', 0),
    'releasing_assignment_id' => (int) env('WORKFLOW_UAT_RELEASING_ASSIGNMENT_ID', 0),
];
