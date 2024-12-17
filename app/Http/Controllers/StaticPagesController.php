<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StaticPagesController extends Controller
{
    public function create()
    {
        echo 'hello world';
        die;
    }
}