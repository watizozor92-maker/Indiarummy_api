<?php

namespace org;

class Util
{
    static public function getTimestampMs($length = 16) {
        $milliseconds = round(microtime(true) * 1000);
        return $milliseconds;
    }
}