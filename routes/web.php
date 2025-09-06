<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/test/performance', function () {
    $result = DB::connection('default')->table('users')->count();

    return view('mission-control::performance', [
        'result' => $result
    ]);
});
