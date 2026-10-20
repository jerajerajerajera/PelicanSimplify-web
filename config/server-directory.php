<?php
return [
    // Test mode can use a server allocation as its join address.
    // Production publishes only explicitly configured addresses.
    // Set this to 'production' when moving to the permanent network.
    // Literal plugin config also works when Laravel's .env configuration is cached.
    'mode' => 'test',
    // New servers are shown on the website as soon as they are created. Set to false to list them by hand.
    'show_new_servers' => true,
];
