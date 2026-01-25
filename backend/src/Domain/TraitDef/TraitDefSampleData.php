<?php

namespace App\Domain\TraitDef;

use App\Shared\SampleData\SampleData;

class TraitDefSampleData implements SampleData
{

    public function __construct(
        private TraitDefFacade $traitDefFacade,
    ){
    }

    public function create(): void
    {
        $this->traitDefFacade->createTraitDef(
            'loyal',
            'Lojální',
            'Drží slovo a stojí při lidech, kterým věří. I pod tlakem má tendenci zůstávat na jedné straně.',
            TraitDef::TYPE_SOCIAL,
        );

        $this->traitDefFacade->createTraitDef(
            'treacherous',
            'Zrádný',
            'Když se mu to hodí, změní stranu nebo poruší dohodu. Vztahy bere spíš jako prostředek než závazek.',
            TraitDef::TYPE_SOCIAL,
        );

        $this->traitDefFacade->createTraitDef(
            'manipulative',
            'Manipulativní',
            'Umí nenápadně tlačit lidi do rozhodnutí, která chce. Často používá emoce, polopravdy nebo nátlak.',
            TraitDef::TYPE_SOCIAL,
        );

        $this->traitDefFacade->createTraitDef(
            'leader',
            'Vůdčí',
            'Přirozeně přebírá iniciativu a dokáže strhnout ostatní. V krizích umí rozhodnout a nést odpovědnost.',
            TraitDef::TYPE_SOCIAL,
        );

        $this->traitDefFacade->createTraitDef(
            'introverted',
            'Introvertní',
            'Dobíjí energii spíš o samotě než mezi lidmi. Víc pozoruje a přemýšlí, než mluví.',
            TraitDef::TYPE_SOCIAL,
        );

        $this->traitDefFacade->createTraitDef(
            'naive',
            'Naivní',
            'Snadno věří dobrým úmyslům a přehlíží varovné signály. Často podceňuje riziko a manipulaci.',
            TraitDef::TYPE_EMOTIONAL,
        );

        $this->traitDefFacade->createTraitDef(
            'strategic',
            'Strategický',
            'Přemýšlí dopředu a skládá si plán do několika kroků. Upřednostňuje dlouhodobý zisk před okamžitým impulzem.',
            TraitDef::TYPE_STRATEGIC,
        );

        $this->traitDefFacade->createTraitDef(
            'emotionally_unstable',
            'Emočně nestabilní',
            'Nálady se mu rychle mění a reaguje silněji než okolí čeká. Ve stresu může jednat impulzivně.',
            TraitDef::TYPE_EMOTIONAL,
        );

        $this->traitDefFacade->createTraitDef(
            'paranoid',
            'Paranoidní',
            'Často očekává zradu nebo skryté motivy. I neutrální signály si může vykládat jako hrozbu.',
            TraitDef::TYPE_EMOTIONAL,
        );

        $this->traitDefFacade->createTraitDef(
            'witty',
            'Vtipný',
            'Rychle reaguje humorem a umí odlehčit napětí. Často si získává lidi šarmem a nadsázkou.',
            TraitDef::TYPE_SOCIAL,
        );
    }

}