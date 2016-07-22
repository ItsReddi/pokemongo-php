<?php
namespace Utils;


class General {
    public static function getCurlSession($user_agent, $post=true) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        if ($post) curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        return $curl;
    }

    public static function log($message) {
        echo sprintf("%.3f %s\n", microtime(true), $message);
    }
} 