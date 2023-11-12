<?php

namespace MirazMac\LaminasFeed\Reader\Media;

use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Extension\AbstractEntry;
use MirazMac\LaminasFeed\LaminasFeedExtensions;
use fivefilters\Readability\Configuration;
use MirazMac\LaminasFeed\Reader\Traits\EntryTrait;

use function array_map;
use function array_merge_recursive;
use function is_string;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function sprintf;
use function trim;

/**
 * An extension that provides bunch of useful aliases to consume the media tag
 */
class Entry extends AbstractEntry
{
    use EntryTrait;

    /**
     * The URL to the media namespace
     *
     * @var        string
     */
    public const MEDIA_NAMESPACE_URL = 'http://search.yahoo.com/mrss/';

    /**
     * XMLNS
     *
     * @var        string
     */
    public const MEDIA_NAMESPACE = 'media';

    /**
     * URL for the YT namespace
     *
     * @var        string
     */
    public const YT_NAMESPACE_URL = 'http://www.youtube.com/xml/schemas/2015';

    /**
     * Namespace for YouTube
     *
     * @var        string
     */
    public const YT_NAMESPACE = 'yt';

    /**
     * Thumbnail URL for YouTube videos
     *
     * @var        string
     */
    public const YT_THUMBNAIL_URL = 'https://img.youtube.com/vi/%s/maxresdefault.jpg';

    /**
     * Namespace for SZN
     *
     * @var        string
     */
    public const SZN_NAMESPACE = 'szn';

    /**
     * URL for the SZN namespace
     *
     * @var        string
     */
    public const SZN_NAMESPACE_URL = 'https://www.seznam.cz';

    /**
     * Returns the `featured image`.
     *
     * @param EntryInterface $entry Additional options
     * @param array $options
     *
     * @return     null|string
     */
    public function getFeaturedImage(EntryInterface $entry, array $options = []): ?string
    {
        if (isset($options['ignoreImageIfContains'])) {
            $options['ignoreImageIfContains'] = (array)$options['ignoreImageIfContains'];
        }

        $options = array_merge_recursive([
            'ignoreImageIfContains' => ['npr-rss-pixel.png', '/b.gif'],
        ], $options);

        // If this is is a YouTube feed we can easily get the video thumbnail
        if ($videoId = $this->getYouTubeVideoId()) {
            return sprintf(static::YT_THUMBNAIL_URL, $videoId);
        }

        if ($highQualitySingleImage = $this->fetchSingleImage('high')) {
            return $highQualitySingleImage;
        }

        // Check for media content tags (covers both standalone and nested media:group)
        if ($mediaImage = $this->fetchMediaImage()) {
            return $mediaImage;
        }

        // See if an image enclosure
        $enclosure = $entry->getEnclosure();
        if ($enclosure !== null && isset($enclosure->type) && str_contains($enclosure->type, 'image')) {
            return $enclosure->url;
        }

        // Check for low priority one off image tags
        if ($lowQualitySingleImage = $this->fetchSingleImage('low')) {
            return $lowQualitySingleImage;
        }

        // Thumbnails tend to be smaller, so fallback to them if nothing else is found..
        $thumbnails = $this->getThumbnails();

        // Not even a thumbnail? That sucks, let's try to find an image using RegEx
        //
        // Some feeds may contain tracking pixels, i.e: https://feeds.npr.org/1004/rss.xml
        // To avoid these, add a list of exceptions via the options argument:
        // [
        //      'ignoreImageIfContains' => ['string', 'more-string'],
        // ]
        if (empty($thumbnails)) {
            preg_match_all('/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', trim($entry->getContent()), $imageTags);

            // Image tags are present
            if (!empty($imageTags[1])) {
                // No filter is set
                if (empty($options['ignoreImageIfContains'])) {
                    return $imageTags[1][0];
                }

                $regex = array_map(static function ($el) {
                    return preg_quote($el, '/');
                }, $options['ignoreImageIfContains']);

                $regex = '(' . implode('|', $regex) . ')';

                foreach ($imageTags[1] as $value) {
                    if (!preg_match('/' . $regex . '/iu', $value)) {
                        return $value;
                    }
                }

                return null;
            }
        }

        if (empty($thumbnails)) {
            return null;
        }

        // Try to send back the largest thumbnail (assuming there are more than one)
        $maxWidthKey = 0;
        $currentWidth = 0;

        foreach ($thumbnails as $key => $thumbnail) {
            $width = (int)$thumbnail->width;
            if ($width && $width > $currentWidth) {
                $currentWidth = $width;
                $maxWidthKey = $key;
            }
        }

        return $thumbnails[$maxWidthKey]->url;
    }

    /**
     * Returns the YouTube Video ID (if found)
     *
     * @return     null|string
     */
    public function getYouTubeVideoId(): ?string
    {
        return $this->getFirstNodeValue(static::YT_NAMESPACE . ':videoId');
    }

    /**
     * @param string $type
     *
     * @return mixed|null
     */
    public function fetchSingleImage(string $type): mixed
    {
        $singleImageTags = $this->getSingleImageTags();

        // Check for low priority one off image tags
        foreach ($singleImageTags[$type] as $tagName) {
            if ($value = $this->getFirstNodeValue($tagName)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Map for one-off image tags
     *
     * @return string[][]
     */
    protected function getSingleImageTags(): array
    {
        return [
            // Will be executed first
            'high' => [
                // 'fullimage' tag, as seen on FeedBurner feeds
                'fullimage',
                'StoryImage',
                // SZN image tags (non-standard)
                // Found in: https://www.extra.cz/rss.xml
                static::SZN_NAMESPACE . ':bigImage',
                static::SZN_NAMESPACE . ':image',
            ],

            // Will be executed last
            'low' => [
                // Try to fetch from the 'StoryImage' tag, as seen on FeedBurner feeds
                // But we do this at the very end as like thumbnails they also tend to be very smaller
                'StoryImage',
                // For Google Trends, extremely low res but since this tag only exists
                // in Google Trend's RSS feeds that shouldn't be a problem (for now)
                \MirazMac\LaminasFeed\Reader\Trends\Entry::HT_NAMESPACE . ':picture',
            ],
        ];
    }

    /**
     * Returns the image URL from media tags if present
     *
     * @return mixed|null
     */
    public function fetchMediaImage(): mixed
    {
        // Check for media content tags (covers both standalone and nested media:group)
        $media = $this->xpath->query($this->getXpathPrefix() . '//' . static::MEDIA_NAMESPACE . ':content');

        if ($media->count()) {
            return null;
        }

        $maxWidthKey = 0;
        $mediaContents = [];
        $currentWidth = 0;
        $i = 0;
        foreach ($media as $mediaContent) {
            // Make sure a type or medium of image is specified
            if (str_contains($mediaContent->getAttribute('type'), 'image') ||
                ($mediaContent->getAttribute('medium') === 'image' &&
                    !empty($mediaContent->getAttribute('url')))) {
                // Compare width if present
                $width = (int)$mediaContent->getAttribute('width');
                if ($width && $width > $currentWidth) {
                    $currentWidth = $width;
                    $maxWidthKey = $i;
                }
                $mediaContents[$i] = $mediaContent->getAttribute('url');
                $i++;
            }
        }

        // Return the image with the highest width
        return $mediaContents[$maxWidthKey] ?? null;
    }

    /**
     * Returns thumbnail(s) as an array
     *
     * @return     array
     */
    public function getThumbnails(): array
    {
        $thumbnails = $this->xpath->query($this->getXpathPrefix() . '//' . static::MEDIA_NAMESPACE . ':thumbnail');
        $medias = [];

        foreach ($thumbnails as $media) {
            $medias[] = (object)[
                'url' => $media->getAttribute('url'),
                'height' => $media->getAttribute('height') === '' ? null : $media->getAttribute('height'),
                'width' => $media->getAttribute('width') === '' ? null : $media->getAttribute('width'),
                'time' => $media->getAttribute('time') === '' ? null : $media->getAttribute('time'),
            ];
        }

        return $medias;
    }

    /**
     * Gets the readable content using readability.php
     *
     * @param EntryInterface $entry The entry
     * @param array $options The options
     * @param null|string $html The html
     *
     * @return Readability
     * @throws ParseException
     */
    public function getReadableContent(
        EntryInterface $entry,
        array          $options = [],
        ?string        $html = null
    ): Readability
    {
        $options = array_merge_recursive([
            'fixRelativeURLs' => true,
            'originalURL' => $entry->getLink(),
            'SummonCthulhu' => true,
            'NormalizeEntities' => true,
        ], $options);

        $config = new Configuration($options);
        $readability = new Readability($config);

        // If HTML is not provided explicitly, fetch it via HTTP request
        if (!is_string($html)) {
            $html = LaminasFeedExtensions::httpGet($entry->getLink());
        }

        $readability->parse($html);

        return $readability;
    }

    /**
     * @inheritdoc
     */
    protected function registerNamespaces(): void
    {
        $this->xpath->registerNamespace(static::MEDIA_NAMESPACE, static::MEDIA_NAMESPACE_URL);
        $this->xpath->registerNamespace(static::YT_NAMESPACE, static::YT_NAMESPACE_URL);
        $this->xpath->registerNamespace(static::SZN_NAMESPACE, static::SZN_NAMESPACE_URL);
    }
}
