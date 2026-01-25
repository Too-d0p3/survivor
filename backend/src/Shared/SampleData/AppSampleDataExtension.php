<?php

namespace App\Shared\SampleData;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class AppSampleDataExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(
            new Configuration(),
            $configs
        );


        $container->setParameter(
            'app_sample_data.order',
            $config['order']
        );
    }

}