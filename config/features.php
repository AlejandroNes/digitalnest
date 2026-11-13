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

    /*
    |--------------------------------------------------------------------------
    | Corporate Modules Feature Flag
    |--------------------------------------------------------------------------
    |
    | Services, portfolio, testimonials and FAQ come from the corporate starter kit and
    | are not part of DigitalNest. When disabled they disappear from the panel, the
    | dashboard, the public routes and the sitemap; their code and data are kept.
    |
    */
    'corporate_modules_enabled' => (bool) env('CORPORATE_MODULES_ENABLED', false),
];
