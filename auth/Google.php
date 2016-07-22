<?php
namespace Auth;

/**
 * Allows for Google login
 *
 * Class Google
 * @package Auth
 */
class Google {
    private $ANDROID_ID = '9774d56d682e549c';
    private $SERVICE= 'audience:server:client_id:848232511240-7so421jotr2609rmqakceuu1luuq0ptb.apps.googleusercontent.com';
    private $APP = 'com.nianticlabs.pokemongo';
    private $CLIENT_SIG = '321187995bc7cdc2b5fc91b11a96e2baa8602c62';

    public function __construct() {
        die("Google login is work in progress. Help us finish it :-)\n");
    }

    public function getAuthProvider() {
        return 'google';
    }

    public function getAccessToken($username, $password) {
        // TODO
    }
} 