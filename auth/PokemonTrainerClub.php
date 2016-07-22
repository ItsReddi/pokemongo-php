<?php

namespace Auth;

use Utils\General;

/**
 * Allows for Pokemon Trainer Club (PTC) login
 *
 * Class PokemonTrainerClub
 * @package Auth
 */
class PokemonTrainerClub {
    private $LOGIN_URL = 'https://sso.pokemon.com/sso/login?service=https%3A%2F%2Fsso.pokemon.com%2Fsso%2Foauth2.0%2FcallbackAuthorize';
    private $LOGIN_OAUTH = 'https://sso.pokemon.com/sso/oauth2.0/accessToken';
    private $PTC_CLIENT_SECRET = 'w8ScCUXJQc6kXKw8FiOhd8Fixzht18Dq3PEVkUCP5ZPxtgyWsbTvWHFLm2wNY0JR';

    public function getAuthProvider() {
        return 'ptc';
    }

    public function getAccessToken($username, $password) {
        $curl = General::getCurlSession('niantic');
        $cookie_jar = tempnam('/tmp','cookie');

        // Get LOGIN_URL page
        curl_setopt($curl, CURLOPT_URL, $this->LOGIN_URL);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_jar);
        $r = curl_exec($curl);
        if (empty($r)) throw new \Exception('failed to get login_url');

        $login_data = json_decode($r, true);
        if (empty($login_data)) {
            General::log('response from get login_url unexpected, retrying');
            sleep(1);
            return $this->getAccessToken($username, $password);
        }

        // Attempt to log in
        $data = [
            'lt' => $login_data['lt'],
            'execution' => $login_data['execution'],
            '_eventId' => 'submit',
            'username' => $username,
            'password' => $password,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_URL, $this->LOGIN_URL);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_HEADER, 1);
        $r = curl_exec($curl);

        preg_match_all('/ticket=(.+)/', $r, $matches);
        if (!isset($matches[1]) || !isset($matches[1][0])) throw new \Exception('failed to get ticket');
        $ticket = trim($matches[1][0]);

        // Exchange the ticket for an access_token
        $data = [
            'client_id' => 'mobile-app_pokemon-go',
            'redirect_uri' => 'https://www.nianticlabs.com/pokemongo/error',
            'client_secret' => $this->PTC_CLIENT_SECRET,
            'grant_type' => 'refresh_token',
            'code' => $ticket
        ];
        curl_setopt($curl, CURLOPT_URL, $this->LOGIN_OAUTH);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        $r = curl_exec($curl);

        preg_match_all('/access_token=(.+?)&/', $r, $matches);
        if (!isset($matches[1]) || !isset($matches[1][0])) throw new \Exception('failed to get access_token');
        $access_token = trim($matches[1][0]);

        curl_close($curl);
        unlink($cookie_jar);

        return $access_token;
    }
} 