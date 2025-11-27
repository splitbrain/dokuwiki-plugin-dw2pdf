<?php

namespace dokuwiki\plugin\dw2pdf\src\attributes;

/**
 * Indicates that a config property should be populated from configuration data
 *
 * This might actually be from the config file, or might be passed in from another environment (like CLI parameters).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FromConfig
{
    /**
     * @param ?string $name The config option to read from. If null, the (lowercased) property name will be used.
     */
    public function __construct(
        public ?string $name = null,
    ) {}
}
