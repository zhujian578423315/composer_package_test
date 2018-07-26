<?php

namespace ButcherClub\Resource;


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
