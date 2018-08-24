<?php

class MockRequest
{
    public $url;
    public $headers;
    public $query;
    public $code;

    public static function post($url, $headers, $query)
    {
        return (object) [
            'code' => 200,
        ];
    }
}
