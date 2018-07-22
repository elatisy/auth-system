<?php

namespace App\Auth;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthProcessor
{
    private $return_arr =   [];
    private $table      =   'auth';

    public function Handle(Array $recv){
        if(isset($recv['event'])){
                if(!$this->judge($recv)){
                    return json_encode($this->return_arr);
                }

            if($recv['event'] == 'signup'){
                $this->signup($recv);
            } elseif ($recv['event'] == 'signin') {
                $this->signin($recv);
            } elseif ($recv['event'] == 'onload') {
                $this->onload($recv);
            } elseif ($recv['event'] == 'signout') {
                $this->signout($recv);
            } else {
                $this->setRetArr([
                    'code'      => '1002',
                    'message'   => '未知event'
                ]);
            }
        }else{
            $this->setRetArr([
                'code'      => '1001',
                'message'   => 'event字段不存在'
            ]);
        }

        if(!isset($this->return_arr['code'])){
            $this->setRetArr(['code' => '0','message' => 'ok']);
        }
        return json_encode($this->return_arr);
    }

    private function judge(Array $recv){
        $info_need = [];
        $maxlen = 16;

        if($recv['event'] == 'signin'){
            $info_need = ['account','password'];
        }elseif ($recv['event'] == 'signup'){
            $info_need = ['username','account','password'];
        }elseif($recv['event'] == 'signout' || $recv['event'] == 'whoRU'){
            if(isset($recv['token'])){
                return true;
            }
            $this->setRetArr(['code' => '3001', 'message' => '没有token']);
            return false;
        }else{
            $this->setRetArr(['code' => '1002', 'message' => '未知event']);
        }

        foreach ($info_need as $key){
            if(!isset($recv[$key])){
                $this->setRetArr(['code' => '3002','message' => $key.'不存在']);
                return false;
            }elseif (mb_strlen($recv[$key]) >= $maxlen){
                $this->setRetArr(['code' => '3003','message' => $key.'长度超限,最大长度: '.$maxlen]);
                return false;
            }
        }
        return true;
    }

    private function signup(Array $recv){

        if($this->showRow('account',$recv['account']) != null){
            $this->setRetArr(['code' => '6001', 'message' => '账号已存在']);
            return;
        }
        $token = $this->randomString(32);
        while($this->showRow('token', $token) != null){
            $token = $this->randomString(32);
        }
        $info = [
            'username'      => $recv['username'],
            'account'       => $recv['account'],
            'password'      => $recv['password'],
            'last_sign_in'  => Carbon::now()->timestamp,
            'last_ip'       => $recv['last_ip'],
            'is_sign_out'   => false,
            'token'         => $token,
        ];

        if($this->writeTable($info)){
            $username = $this->showRow('token',$token);
            $username = $username->username;
            $this->setRetArr([
                'username'   => $username,
                'token'     => $token,
            ]);
        }
    }

    private function signin(Array $recv){

        $row = $this->showRow('account',$recv['account']);
        if($row == null || $recv['password'] != $row->password){
            $this->setRetArr([
                'code'      => '6001',
                'message'   => '账号不存在或者密码错误'
            ]);
            return;
        }

        if($this->writeTable([
            'last_sign_in'  => Carbon::now()->timestamp,
            'last_ip'       => $recv['last_ip'],
            'is_sign_out'   => false,
        ],
            'account',$recv['account'])){
            $this->setRetArr([
                'username'  => $row->username,
                'token'     => $row->token
            ]);
        }
    }

    private function onload(Array $recv){
        $rows = DB::table($this->table)->where('last_ip', '=' ,$recv['last_ip'])->get();
        $user = null;
        $time_limit = 60;   //second
        $latest_time = 0;
        foreach ($rows as $row){
            if((intval($row->last_sign_in) > $latest_time ) && !$row->is_sign_out ){
                $user = $row;
                $latest_time = intval($row->last_sign_in);
            }
        }
        if($user == null){return;}

        $now = Carbon::now()->timestamp;
        if(($now - $latest_time) > $time_limit ){
            $this->setRetArr([
                'code'      => '5001',
                'message'   => '登录已过期'
            ]);
        }else{
            $this->setRetArr([
                'code'      => '5000',
                'message'   => '登录未过期',
                'username'  => $user->username,
                'token'     => $user->token
            ]);
        }
    }

    private function signout(Array $recv){
        $row = $this->showRow('token',$recv['token']);
        if($row == null){
            $this->setRetArr([
                'code'      => '7001',
                'message'   => '未知token'
            ]);
            return;
        }
        $this->writeTable(['is_sign_out'   => true],'token',$recv['token']);
    }

    private function writeTable(Array  $info, $line = null, $where = null){
        try{
            if($where == null){
                DB::table($this->table)->insert($info);
            }else{
                DB::table($this->table)->where($line , '=' , $where)->update($info);
            }
            return true;
        }catch (\Exception $e){
            $this->setRetArr([
                'code'      => '4001',
                'message'   => '写数据库错误,错误为'.$e->getMessage(),
            ]);
            return false;
        }
    }

    private function showRow($line, $where){
        return DB::table($this->table)->where($line, '=', $where)->first();
    }

    private function setRetArr(Array $new){
        $this->return_arr = array_merge($this->return_arr, $new);
    }

    private function randomString($len){
        $characters = '1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
        $strlen = strlen($characters) - 1;
        $res = '';
        for($i = 0; $i < $len; ++$i){
            $res .= $characters[rand(0, $strlen)];
        }
        return $res;
    }

}