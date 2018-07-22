<?php

namespace App\Auth;


class AuthProcessor
{
    private $return_arr = [];

    public function Handle(Array $recv){

        if(isset($recv['event'])){
            if(!$this->judge($recv)){
                return $this->return_arr;
            }

//            if($recv['event'] == 'signin'){
//
//            }else{
//                $this->setRetArr(['code' => '1002','message' => '未知event']);
//            }
        }else{
            $this->setRetArr(['code' => '1001','message' => 'event字段不存在']);
        }

        if(!isset($this->return_arr['code'])){
            $this->setRetArr(['code' => '0','message' => 'ok']);
        }
        return $this->return_arr;
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

//    private function signin(){
//
//    }

    private function setRetArr(Array $new){
        $this->return_arr = array_merge($this->return_arr, $new);
    }
}