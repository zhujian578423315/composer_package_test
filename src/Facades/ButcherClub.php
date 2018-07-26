<?php
namespace Test\Facades;

use Illuminate\Support\Facades\Facade;

class ButcherClub extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ButcherClub';
    }
}