<?php

namespace dokuwiki\plugin\dw2pdf\src;

/**
 * Signals an expected, user-facing failure during PDF export
 *
 * The exception message is a language key. Optional arguments are applied to the
 * translated string via vsprintf(), allowing the message to be localized where it
 * is displayed to the user.
 */
class ExportException extends \Exception
{
    /** @var array Arguments to fill placeholders in the translated message */
    protected array $args;

    /**
     * @param string $langKey Language key describing the failure
     * @param array $args Arguments applied to the translated string via vsprintf()
     */
    public function __construct(string $langKey, array $args = [])
    {
        parent::__construct($langKey);
        $this->args = $args;
    }

    /**
     * Get the arguments to fill placeholders in the translated message
     *
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
