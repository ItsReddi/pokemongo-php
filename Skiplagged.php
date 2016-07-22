<?php

require_once 'utils/General.php';
require_once 'utils/Pokemon.php';

require_once 'auth/Google.php';
require_once 'auth/PokemonTrainerClub.php';

class Skiplagged {
    private $SKIPLAGGED_API = 'http://skiplagged.com/api/pokemon.php';
    private $GENERAL_API = 'https://pgorelease.nianticlabs.com/plfe/rpc';
    private $SPECIFIC_API = null;
    private $PROFILE = null;
    private $PROFILE_RAW = null;

    private $curl_skiplagged_session = null;
    private $curl_niantic_session = null;

    private $username = null;
    private $password = null;
    private $access_token = null;
    private $auth_provider = 'ptc';

    public function __construct() {
        $this->curl_skiplagged_session = Utils\General::getCurlSession('pokemongo-php');
        $this->curl_niantic_session = Utils\General::getCurlSession('Niantic App');
    }

    public function __destruct() {
        curl_close($this->curl_skiplagged_session);
        curl_close($this->curl_niantic_session);
    }

    // Login

    public function loginWithGoogle($username, $password) {
        Utils\General::log('called loginWithGoogle');

        $google_auth = new Auth\Google();
        $auth_provider = $google_auth->getAuthProvider();
        $access_token = $google_auth->getAccessToken($username, $password);
        $access_token = $access_token;

        return $this->updateLogin($auth_provider, $access_token, $username, $password);
    }

    public function loginWithPokemonTrainer($username, $password) {
        Utils\General::log('called loginWithPokemonTrainer');

        $ptc_auth = new Auth\PokemonTrainerClub();
        $auth_provider = $ptc_auth->getAuthProvider();
        $access_token = $ptc_auth->getAccessToken($username, $password);

        if (empty($access_token)) {
            throw new Exception('failed to get access_token');
        }

        return $this->updateLogin($auth_provider, $access_token, $username, $password);
    }

    protected function updateLogin($auth_provider, $access_token, $username, $password) {
        if (!empty($access_token)) {
            $this->auth_provider = $auth_provider;
            $this->access_token = $access_token;
            $this->username = $username;
            $this->password = $password;

            return [$this->auth_provider, $this->access_token];
        }

        return false;
    }

    public function isLoggedIn() {
        return !empty($this->access_token);
    }

    protected function refreshLogin() {
        if (!$this->isLoggedIn()) {
            throw new Exception('needs an existing log in');
        }

        $this->SPECIFIC_API = null;
        $this->PROFILE = null;
        $this->PROFILE_RAW = null;

        if ($this->auth_provider == 'google') {
            return $this->loginWithGoogle($this->username, $this->password);
        } else if ($this->auth_provider == 'ptc') {
            return $this->loginWithPokemonTrainer($this->username, $this->password);
        }
    }

    public function getAccessToken() {
        return $this->access_token;
    }

    public function getAuthProvider() {
        return $this->auth_provider;
    }

    // Calls

    protected function call($endpoint, $data) {
        $is_skiplagged_api = strpos($endpoint, 'skiplagged') !== false;
        $curl_session = $is_skiplagged_api ? $this->curl_skiplagged_session : $this->curl_niantic_session;

        while (1) {
            try {
                curl_setopt($curl_session, CURLOPT_URL, $endpoint);

                if ($is_skiplagged_api) {
                    curl_setopt($curl_session, CURLOPT_POSTFIELDS, $data);
                    return json_decode(curl_exec($curl_session), true);
                } else {
                    curl_setopt($curl_session, CURLOPT_POSTFIELDS, base64_decode($data));
                    return base64_encode(curl_exec($curl_session));
                }
            } catch (Exception $e) {
                Utils\General::log(sprintf('post exception %s', $e));
                sleep(1);
            }
        }
    }

    public function getSpecificApiEndpoint() {
        Utils\General::log('called getSpecificApiEndpoint');

        if (!$this->isLoggedIn()) {
            throw new Exception('need to log in first');
        }

        $response = $this->call($this->SKIPLAGGED_API, [
            'access_token' => $this->getAccessToken(),
            'auth_provider' => $this->getAuthProvider()
        ]);

        if (!isset($response['pdata'])) {
            throw new Exception('failed to get pdata');
        }

        $response = $this->call($this->GENERAL_API, $response['pdata']);

        if (empty($response)) {
            throw new Exception('pdata api call failed');
        }

        $response = $this->call($this->SKIPLAGGED_API, [
            'access_token' => $this->getAccessToken(),
            'auth_provider' => $this->getAuthProvider(),
            'pdata' => $response
        ]);

        if (!isset($response['api_endpoint']) || empty($response['api_endpoint'])) {
            throw new Exception('failed to retrieve specific api endpoint');
        }

        $this->SPECIFIC_API = $response['api_endpoint'];

        return $this->SPECIFIC_API;
    }

    public function getProfile() {
        Utils\General::log('called getProfile');

        if (empty($this->SPECIFIC_API)) {
            return $this->getSpecificApiEndpoint();
        }

        $response = $this->call($this->SKIPLAGGED_API, [
            'access_token' => $this->getAccessToken(),
            'auth_provider' => $this->getAuthProvider(),
            'api_endpoint' => $this->SPECIFIC_API
        ]);

        if (!isset($response['pdata'])) {
            throw new Exception('failed to get pdata');
        }

        $response = $this->call($this->SPECIFIC_API, $response['pdata']);
        if (empty($response)) {
            throw new Exception('pdata api call failed');
        }

        $this->PROFILE_RAW = $response;

        $response = $this->call($this->SKIPLAGGED_API, [
            'access_token' => $this->getAccessToken(),
            'auth_provider' => $this->getAuthProvider(),
            'api_endpoint' => $this->SPECIFIC_API,
            'pdata' => $this->PROFILE_RAW
        ]);

        if (!isset($response['username'])) {
            throw new Exception('failed to retrieve profile');
        }

        $this->PROFILE = $response;
        return $this->PROFILE;
    }

    /**
     * Generates a realistic path to traverse the bounds and find spawned pokemon.
     * Processed sequentially and with delay to minimize chance of getting account banned.
     * Lower step size means higher accuracy, but takes more time to traverse.
     */
    public function findPokemon($bounds, $pokemon_callback=null, $step_size=0.002) {
        Utils\General::log('called getProfile');

        if (empty($this->PROFILE_RAW)) {
            $this->getProfile();
        }

        $bounds = sprintf('%f,%f,%f,%f', $bounds[0][0], $bounds[0][1], $bounds[1][0], $bounds[1][1]);

        $response = $this->call($this->SKIPLAGGED_API, [
            'access_token' => $this->getAccessToken(),
            'auth_provider' => $this->getAuthProvider(),
            'profile' => $this->PROFILE_RAW,
            'bounds' => $bounds,
            'step_size' => $step_size
        ]);

        if (!isset($response['requests'])) {
            throw new Exception('failed to get requests');
        }

        foreach ($response['requests'] as $request) {
            Utils\General::log('moving player');
            $pokemon_data = $this->call($this->SPECIFIC_API, $request['pdata']);
            $response = $this->call($this->SKIPLAGGED_API, ['pdata' => $pokemon_data]);

            $num_pokemon_found = count($response['pokemons']);
            if ($num_pokemon_found > 0) {
                Utils\General::log(sprintf('found %d pokemon', $num_pokemon_found));
            }

            foreach ($response['pokemons'] as $_pokemon) {
                $pokemon = new Utils\Pokemon($_pokemon);

                if (!is_null($pokemon_callback)) {
                    call_user_func_array($pokemon_callback, [$pokemon]);
                } else {
                    echo $pokemon."\n";
                }
            }

            sleep(.5);
        }
    }

    public function getBoundsForAddress($address, $offset=0.002) {
        $curl = curl_init('https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        $bounds = $response['results'][0]['geometry']['viewport'];

        return [
            [$bounds['southwest']['lat'] - $offset, $bounds['southwest']['lng'] - $offset],
            [$bounds['northeast']['lat'] + $offset, $bounds['northeast']['lng'] + $offset]
        ];
    }
} 