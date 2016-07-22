<?php
require_once 'Skiplagged.php';

$client = new Skiplagged();

// Determine a bounding box to traverse and look for pokemon

//$bounds = [
//    [40.76356269219236, -73.98657795715332], # Lower left lat, lng
//    [40.7854671345488, -73.95812508392333] # Upper right lat, lng
//]; # Central park, New York City

$bounds = $client->getBoundsForAddress('central park, ny');
echo json_encode($bounds)."\n";

// Log in with a Google or Pokemon Trainer Club account
//echo json_encode($client->loginWithGoogle('EMAIL', 'PASSWORD'))."\n";
echo json_encode($client->loginWithPokemonTrainer('USERNAME', 'PASSWORD'))."\n";

// Get specific Pokemon Go API endpoint
echo $client->getSpecificApiEndpoint()."\n";

// Get profile
echo json_encode($client->getProfile())."\n";

// Find pokemon
$client->findPokemon($bounds, function($pokemon) {
    echo $pokemon."\n";
});
