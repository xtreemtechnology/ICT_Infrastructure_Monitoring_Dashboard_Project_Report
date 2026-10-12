<?php

use Illuminate\Support\Facades\Route;

Route::view('/login', 'welcome')->name('login');

Route::get('/', function () {
    return view('welcome');
});
