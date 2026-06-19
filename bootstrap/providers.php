<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    TenancyServiceProvider::class,
];
