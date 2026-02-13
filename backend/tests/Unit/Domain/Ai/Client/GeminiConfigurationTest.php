<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Client;

use App\Domain\Ai\Client\GeminiConfiguration;
use PHPUnit\Framework\TestCase;

final class GeminiConfigurationTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $config = new GeminiConfiguration(
            'test-api-key',
            'gemini-1.5-pro',
            'https://generativelanguage.googleapis.com/v1beta',
            0.7,
        );

        self::assertSame('test-api-key', $config->getApiKey());
        self::assertSame('gemini-1.5-pro', $config->getModel());
        self::assertSame('https://generativelanguage.googleapis.com/v1beta', $config->getBaseUrl());
        self::assertSame(0.7, $config->getDefaultTemperature());
    }

    public function testGetEndpointUrlBuildsCorrectUrl(): void
    {
        $config = new GeminiConfiguration(
            'test-api-key',
            'gemini-1.5-pro',
            'https://generativelanguage.googleapis.com/v1beta',
            0.7,
        );

        self::assertSame(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
            $config->getEndpointUrl(),
        );
    }

    public function testGetEndpointUrlWithDifferentModelAndBaseUrl(): void
    {
        $config = new GeminiConfiguration(
            'another-key',
            'gemini-2.0-flash',
            'https://api.example.com/v2',
            1.0,
        );

        self::assertSame(
            'https://api.example.com/v2/models/gemini-2.0-flash:generateContent',
            $config->getEndpointUrl(),
        );
    }
}
