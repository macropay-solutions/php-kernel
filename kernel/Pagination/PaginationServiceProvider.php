<?php

namespace MacropaySolutions\Kernel\Pagination;

use MacropaySolutions\Kernel\Support\ServiceProvider;

class PaginationServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        PaginationState::resolveUsing($this->app);
    }
}
