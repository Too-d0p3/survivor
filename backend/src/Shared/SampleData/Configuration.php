<?php

namespace App\Shared\SampleData;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('app_sample_data');


        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('order')
                    ->scalarPrototype()->end()
                    ->requiresAtLeastOneElement()
                ->end()
            ->end();


        return $treeBuilder;
    }

}