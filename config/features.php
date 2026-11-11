<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Client Panel Feature Flag
    |--------------------------------------------------------------------------
    |
    | Enables the independent client panel at /panel for accounts with the 'user' role.
    | Disabled by default in the Corporate Starter Kit.
    |
    */
    'client_panel_enabled' => (bool) env('CLIENT_PANEL_ENABLED', false),
];
