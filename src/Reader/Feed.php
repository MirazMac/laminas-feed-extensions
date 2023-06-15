<?php

namespace MirazMac\LaminasFeed\Reader;

use Laminas\Feed\Reader\Extension\AbstractFeed;
use MirazMac\LaminasFeed\Reader\Trends\Entry as TrendsEntry;
use function is_string;

/**
 * Provides some helper functions for feeds
 */
class Feed extends AbstractFeed
{
    /**
     * @inheritdoc
     */
    public function registerNamespaces(): void
    {
        $this->xpath->registerNamespace(TrendsEntry::HT_NAMESPACE, TrendsEntry::HT_NAMESPACE_URL);
    }

    /**
     * Determines if the feed is a Google Trends feed
     * (in theory any feed that implements the Google Trends namespace)
     *
     * @return     bool
     */
    public function isTrendsFeed(): bool
    {
        return is_string($this->domDocument->documentElement->getAttribute('xmlns:' . TrendsEntry::HT_NAMESPACE));
    }
}
