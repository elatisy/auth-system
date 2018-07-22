<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Auth\AuthProcessor;

class AuthController extends Controller
{
    public function handle(Request $request){
//        return response($request->all());
        $arr = array_merge($request->all(),['last_ip' => $request->getClientIps()[0]]);

        $processor = new AuthProcessor();
        return response($processor->Handle($arr));
    }
}
