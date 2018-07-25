<?php
namespace Test\Facades;

use Illuminate\Support\Facades\Facade;

class Testf extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'test';
    }
}