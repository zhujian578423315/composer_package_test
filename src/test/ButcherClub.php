<?php

namespace Test\test;


class ButcherClub
{
    function __construct()
    {

    }

    public function test()
    {
        return generate_uuid(4);
    }
}
