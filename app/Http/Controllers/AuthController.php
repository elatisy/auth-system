<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Auth\AuthProcessor;

class AuthController extends Controller
{
    public function handle(Request $request){
        $arr = $request->all();
        $arr = array_merge($arr,$request->getClientIps());

        $processor = new AuthProcessor();
        $processor->Handle($arr);
    }
}
