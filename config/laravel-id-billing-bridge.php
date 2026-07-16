<?php

declare(strict_types=1);

return [

    /*
     * When true (and the package is installed), metered Cbox ID domain events are
     * forwarded to the billing usage buffer off the transactional outbox. Disable to
     * stop forwarding without removing the package.
     */
    'enabled' => env('CBOX_ID_BILLING_BRIDGE_ENABLED', true),

];
