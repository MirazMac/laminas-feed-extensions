<?php

namespace MirazMac\LaminasFeed\Reader\Traits;

trait EntryTrait
{
    /**
     * Gets the first node value.
     *
     * @param      string $tagName  The tag name
     * @param mixed|null  $fallback The fallback
     *
     * @return     mixed
     */
    public function getFirstNodeValue(string $tagName, mixed $fallback = null): mixed
    {
        $result = $this->getXpath()->query($this->getXpathPrefix() . '//' . $tagName);
        return $result->count() ? $result->item(0)->nodeValue : $fallback;
    }
}