<?php

namespace MirazMac\LaminasFeed\Reader\Trends;

use Laminas\Feed\Reader\Extension\AbstractEntry;
use MirazMac\LaminasFeed\Reader\Traits\EntryTrait;

use function strlen;
use function substr;

/**
 * An extension for consuming special tags from Google Trends RSS feed
 */
class Entry extends AbstractEntry
{
    use EntryTrait;

    /**
     * Namespace for HT
     *
     * @var        string
     */
    public const HT_NAMESPACE = 'ht';

    /**
     * URL for the HT namespace
     *
     * @var        string
     */
    public const HT_NAMESPACE_URL = 'https://trends.google.com/trends/trendingsearches/daily';

    /**
     * Returns news items from Google Trends topic
     *
     * @return     array|null
     */
    public function getNewsItems(): ?array
    {
        $newsItem = $this->xpath->query(
            expression: $this->getXpathPrefix() . '//' . static::HT_NAMESPACE . ':news_item'
        );

        if (!$newsItem) {
            return null;
        }

        $news = [];

        foreach ($newsItem as $key => $value) {
            foreach ($value->childNodes as $childNode) {
                if (str_starts_with($childNode->nodeName, static::HT_NAMESPACE)) {
                    $nodeName = substr($childNode->nodeName, strlen(static::HT_NAMESPACE . ':'));
                    $news[$key][$nodeName] = trim($childNode->nodeValue);
                }
            }
        }

        return $news;
    }

    /**
     * Returns the approx. traffic count
     *
     * @return     string|null
     */
    public function getApproxTraffic(): ?string
    {
        return $this->getFirstNodeValue(static:: HT_NAMESPACE . ':approx_traffic');
    }

    /**
     * @inheritdoc
     */
    protected function registerNamespaces(): void
    {
        $this->xpath->registerNamespace(static::HT_NAMESPACE, static::HT_NAMESPACE_URL);
    }
}
