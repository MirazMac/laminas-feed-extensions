<?php

declare(strict_types=1);

namespace MirazMac\LaminasFeed;

use Laminas\Feed\Reader\Extension\AbstractEntry;

/**
 * Helpers for the library
 */
class Helpers
{
    /**
     * Gets the first node value.
     *
     * @param AbstractEntry $entry The entry
     * @param string $tagName The tag name
     * @param mixed $fallback The fallback
     *
     * @return     mixed
     */
    public static function getFirstNodeValue(
        AbstractEntry $entry,
        string        $tagName,
        mixed         $fallback = null
    ): mixed
    {
        $result = $entry->getXpath()->query($entry->getXpathPrefix() . '//' . $tagName);
        return $result->count() ? $result->item(0)->nodeValue : $fallback;
    }
}
