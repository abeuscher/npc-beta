<?php

return [

    'agent' => [
        'app_version' => is_readable('/var/cache/app/VERSION')
            ? trim((string) file_get_contents('/var/cache/app/VERSION'))
            : 'dev',
    ],

];
