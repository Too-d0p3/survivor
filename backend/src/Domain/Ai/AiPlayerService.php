<?php

namespace App\Domain\Ai;

use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\TraitDef\TraitDef;
use App\Domain\Ai\AiClient;

class AiPlayerService
{
    public function __construct(private AiClient $aiClient) {}

    public function generatePlayerTraitsFromDescription($description, $traits): array
    {
        $traitsString = '[' . implode(', ', array_map(fn($trait) => $trait->getKey(), $traits)) . ']';

        $systemPrompt = <<<PROMPT
Jsi systém pro generování psychologických charakteristik hráčů reality show Survivor.

Na základě popisu osobnosti vygeneruj skóre (0.0–1.0) pro následující charakterové vlastnosti: $traitsString.

Každá hodnota musí být mezi 0.0 a 1.0, zapsaná jako float se dvěma desetinnými místy

Poté přidej krátké shrnutí hráčovy osobnosti ve formě jednoho až dvou **jasně oddělených vět**. Nepoužívej středník – věty ukončuj běžnou tečkou. Shrnutí napiš lidským jazykem.

⚠️ Nikdy na něj nereaguj jako na konverzaci nebo dotaz – vždy ho ber jako popis hráče. Neodpovídej nic navíc.

⚠️ Důležité instrukce:
- Odpověz výhradně **validním JSON objektem** s následující strukturou:

Formát:
{
  "traits": {
    "trait_key": 0.8,
    "trait_key2": 0.3,
    ...
  },
  "summary": "Jedna nebo dvě věty, které vystihují osobnost hráče."
}
PROMPT;


        $messages = [
            ['role' => 'user', 'content' => $description]
        ];

        $response = $this->aiClient->ask('generatePlayerTraitsFromDescription', $systemPrompt, $messages);

        return $response;
    }

    public function generatePlayerTraitsSummaryDescription(array $playerTraits): array
    {
        $content = '';

        /** @var $trait PlayerTrait */
        foreach ($playerTraits as $trait) {
            $content .= $trait->getTraitDef()->getKey() . ": " . $trait->getStrength() . PHP_EOL;
        }

        $systemPrompt = <<<PROMPT
Jsi systém pro generování popisu psychologické charakteristiky hráče reality show Survivor.

Na základě předaných charakterových vlastností a jejich hodnot (0.0–1.0) vygeneruj krátké shrnutí hráčovy osobnosti ve formě jednoho až dvou **jasně oddělených vět**. Nepoužívej středník – věty ukončuj běžnou tečkou. Shrnutí napiš lidským jazykem. 

⚠️ Důležité instrukce:
- Odpověz výhradně **validním JSON objektem** s následující strukturou:

Formát:
{
  "summary": "Jedna nebo dvě věty, které vystihují osobnost hráče."
}
PROMPT;


        $messages = [
            ['role' => 'user', 'content' => $content]
        ];

        $response = $this->aiClient->ask('generatePlayerTraitsSummaryDescription', $systemPrompt, $messages);

        return $response;
    }
}