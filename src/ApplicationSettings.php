<?php

declare(strict_types=1);

namespace Thingston\Http;

use Thingston\Settings\AbstractSettings;

final class ApplicationSettings extends AbstractSettings
{
    public const ENVIRONMENT = 'env';
    public const TIMEZONE = 'timezone';

    public const ENV_PRODUCTION = 'production';
    public const ENV_TESTING = 'testing';
    public const ENV_DEVELOPMENT = 'development';

    /**
     * @param array<string, array<mixed>|scalar|\Thingston\Settings\SettingsInterface> $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct(array_merge([
            self::ENVIRONMENT => self::ENV_PRODUCTION,
            self::TIMEZONE => 'UTC',
        ], $settings));
    }
}
