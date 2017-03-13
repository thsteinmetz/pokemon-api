<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;


/**
 * Look up the deatails of a pokemon by name or ID
 */
$app->get('/{pokemon}', function (Client $client, $pokemon) use ($app) {
    try {
        $response = $client->request('get', 'http://pokeapi.co/api/v2/pokemon/' . $pokemon);
        return $response->getBody();
    } catch (ClientException $e) {
        return $e->getResponse()->getBody();
    }
});

/**
 * Look up the attack value of a pokemon by name or ID
 */
$app->get('/{pokemon}/attack', function (Client $client, $pokemon) use ($app) {
    try {
        $pokemon = PokemonApiRepository::getPokemonData($pokemon);
        return json_encode(['attack' => $pokemon->getAttack()]);
    } catch (ClientException $e) {
        return $e->getResponse()->getBody();
    }
});

/**
 * Take two pokemon by names or ID and have a battle
 */
$app->get('/battle/{pokemonOne}/{pokemonTwo}', function ($pokemonOne, $pokemonTwo) use ($app) {
    try {
        $pokemonOne = PokemonApiRepository::getPokemonData($pokemonOne);
        $pokemonTwo = PokemonApiRepository::getPokemonData($pokemonTwo);

        $battle = new PokemonBattle($pokemonOne, $pokemonTwo);

        $battle->setBattleHistory(new BattleHistory());

        $battle->execute();

        $winner = $battle->getWinner();

        $battleHistory = $battle->getBattleHistory();

        return json_encode([
            'pokemon' => [$pokemonOne->getName(), $pokemonTwo->getName()],
            'winner' => $winner->getName(),
            'history' => $battleHistory->getEntries()
        ]);

    } catch (ClientException $e) {
        return $e->getResponse()->getBody();
    }
});


/**
 * Simple wrapper for API calls to Poke Api
 * Class PokemonApiRepository
 */
class PokemonApiRepository
{
    /**
     * Make a call to the pokiapi.co API and return a Pokemon object with the required info (Name, HP, attack)
     * @param $name
     * @return Pokemon
     */
    public static function getPokemonData($name): Pokemon
    {
        $client = new Client();
        $response = $client->request('get', 'http://pokeapi.co/api/v2/pokemon/' . $name);

        $response = json_decode($response->getBody());

        foreach ($response->stats as $stat) {
            if ($stat->stat->name == 'attack') {
                $attack = $stat->base_stat;
            }

            if ($stat->stat->name == 'hp') {
                $hp = $stat->base_stat;
            }
        }

        $pokemon = new Pokemon($name, $hp, $attack);

        return $pokemon;
    }
}


class Pokemon
{
    private $name;
    private $hp;
    private $attack;

    public function __construct(String $name, int $hp, int $attack)
    {
        $this->name = $name;
        $this->hp = $hp;
        $this->attack = $attack;
    }

    /**
     * Return Pokemon's name
     * @return String
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Return the current HP of the pokemon
     * @return int
     */
    public function getHp()
    {
        return $this->hp;
    }

    /**
     * Return the attack of the pokemon
     * @return int
     */
    public function getAttack()
    {
        return $this->attack;
    }

    /**
     * Perform the attack of the pokemon and return the damage (10% of the attack)
     * @return int
     */
    public function attack()
    {
        return $this->attack * .1;
    }

    /**
     * Return if the pokemon is still alive
     * @return bool
     */
    public function isAlive()
    {
        return $this->hp > 0;
    }

    /**
     * Update the HP of the pokemon based on the damage that was just taken
     * @param $damage
     */
    public function takeDamage($damage)
    {
        $this->hp -= $damage;
    }
}


class PokemonBattle
{
    private $battleHistory;
    private $pokemonOne;
    private $pokemonTwo;
    private $winner;
    private $bothAreAlive = true;
    private $round = 1;

    /**
     * PokemonBattle constructor.
     * @param Pokemon $pokemonOne
     * @param Pokemon $pokemonTwo
     */
    public function __construct(Pokemon $pokemonOne, Pokemon $pokemonTwo)
    {
        // Super generic randomization for ordering...
        if (rand(1, 2) == 1) {
            $this->pokemonOne = $pokemonOne;
            $this->pokemonTwo = $pokemonTwo;
        } else {
            $this->pokemonOne = $pokemonTwo;
            $this->pokemonTwo = $pokemonOne;
        }

    }

    /**
     * Execute the Battle of the two pokemon
     */
    public function execute()
    {
        while ($this->bothAreAlive) {

            // Pokemone One goes first
            $this->executeTurn($this->pokemonOne, $this->pokemonTwo);

            // Check to make sure attack did not kill the defender
            if ($this->bothAreAlive) {
                $this->executeTurn($this->pokemonTwo, $this->pokemonOne);
            }
            $this->round++;
        }

    }

    /**
     * Execute the turn for the attacking pokemon
     * @param Pokemon $attacker
     * @param Pokemon $defender
     */
    private function executeTurn(Pokemon $attacker, Pokemon $defender)
    {
        $damage = $attacker->attack();
        $defender->takeDamage($damage);
        $this->battleHistory->addEntry('Round ' . $this->round . ': ' . $attacker->getName() . ' dealt ' . $damage . ' to ' . $defender->getName());
        if (!$defender->isAlive()) {
            $this->battleHistory->addEntry($attacker->getName() . ' has defeated ' . $defender->getName() . ' in round ' . $this->round . '!');
            $this->bothAreAlive = false;
            $this->setWinner($attacker);
        }
    }

    /**
     * Set the battle history logging object
     * @param BattleHistory $battleHistory
     */
    public function setBattleHistory(BattleHistory $battleHistory)
    {
        $this->battleHistory = $battleHistory;
    }

    /**
     * Return the BattleHistory object
     * @return BattleHistory
     */
    public function getBattleHistory(): BattleHistory
    {
        return $this->battleHistory;
    }

    /**
     * Set the winner of the battle
     * @param Pokemon $winner
     */
    private function setWinner(Pokemon $winner)
    {
        $this->winner = $winner;
    }

    /**
     * Return the winner of the battle
     * @return mixed
     */
    public function getWinner(): Pokemon
    {
        return $this->winner;
    }
}

/**
 * Class BattleHistory
 *
 * Normally this would adhere to an interface but for the sake of this example I will skip it
 */
class BattleHistory
{
    private $entries = [];

    /**
     * Create a history entry
     * @param $entry
     */
    public function addEntry($entry)
    {
        $this->entries[] = $entry;
    }

    /**
     * Return all the history entries
     * @return array
     */
    public function getEntries()
    {
        return $this->entries;
    }
}

