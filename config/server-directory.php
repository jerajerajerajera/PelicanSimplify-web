<?php
return [
    // Test mode can use a server allocation as its join address.
    // Production publishes only explicitly configured addresses.
    // Set this to 'production' when moving to the permanent network.
    // Literal plugin config also works when Laravel's .env configuration is cached.
    'mode' => 'test',
];
