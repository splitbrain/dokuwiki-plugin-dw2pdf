<?php

namespace dokuwiki\plugin\dw2pdf\src\attributes;

/**
 * Indicates that a config property should be populated from HTTP input
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FromInput
{
    /**
     * @param ?string $name The input field to read from. If null, the property name will be used.
     */
    public function __construct(
        public ?string $name = null,
    ) {}
}
