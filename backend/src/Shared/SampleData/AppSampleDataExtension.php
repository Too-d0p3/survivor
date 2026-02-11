<?php

declare(strict_types=1);

namespace App\Shared\SampleData;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class AppSampleDataExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{order: array<int, class-string>} $config */
        $config = $this->processConfiguration(
            new Configuration(),
            $configs,
        );

        $container->setParameter(
            'app_sample_data.order',
            $config['order'],
        );
    }
}
