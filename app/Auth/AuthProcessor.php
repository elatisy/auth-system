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
            }else{
                $this->setRetArr(['code' => '1002','message' => '未知event']);
            }
        }else{
            $this->setRetArr(['code' => '1001','message' => 'event字段不存在']);
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
                'code'       => '0',
                'message'    => 'ok',
                'username'   => $username,
                'token'     => $token,
            ]);
        }
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
                'code'      => '4000',
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