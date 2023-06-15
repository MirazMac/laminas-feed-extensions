<?php

declare(strict_types=1);

namespace MirazMac\LaminasFeed;

use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Reader\StandaloneExtensionManager;
use MirazMac\LaminasFeed\Reader\Feed;
use MirazMac\LaminasFeed\Reader\Media\Entry;

use function curl_exec;
use function curl_init;
use function curl_setopt;
use function extension_loaded;
use function fclose;
use function fopen;
use function implode;
use function stream_context_create;
use function stream_get_contents;

use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_USERAGENT;

/**
 * LaminasFeed
 *
 * Some useful extensions for Laminas Feed parser
 *
 * @author Miraz Mac <mirazmac@gmail.com>
 * @link https://mirazmac.com
 */
class LaminasFeedExtensions
{
    /**
     * A map of the available extensions with their appopriate types
     *
     * @var        array
     */
    protected static array $extensions = [
        "\MirazMac\LaminasFeed\Reader\Media" => [
            'class' => Entry::class,
            'type' => 'Entry'
        ],


        "\MirazMac\LaminasFeed\Reader" => [
            'class' => Feed::class,
            'type' => 'Feed'
        ],

        "\MirazMac\LaminasFeed\Reader\Trends" => [
            'class' => \MirazMac\LaminasFeed\Reader\Trends\Entry::class,
            'type' => 'Entry'
        ],
    ];

    /**
     * Register all of the reader extensions using the standard Laminas extensions manager, optional.
     * As you can always manually register them.
     */
    public static function register(): bool
    {
        /** @var StandaloneExtensionManager $manager */
        $manager = Reader::getExtensionManager();

        foreach (static::$extensions as $name => $map) {
            $manager->add($name . '\\' . $map['type'], $map['class']);
            Reader::registerExtension($name);
        }

        return true;
    }

    /**
     * Simple wrapper to make HTTP GET requests, works with/without CURL
     *
     * @param string $url The url
     *
     * @return     string
     */
    public static function httpGet(string $url): string
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36';
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'DNT: 1',
            'Referer: ' . $url
        ];

        if (extension_loaded('curl')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            return (string)curl_exec($ch);
        }

        $options = [
            'http' => [
                'follow_location' => true,
                'method' => 'GET',
                'header' => implode("\n", $headers),
                'ignore_errors' => true,
                'user_agent' => $userAgent,
            ]
        ];

        $context = stream_context_create($options);
        $fp = fopen($url, 'rb', false, $context);
        $response = stream_get_contents($fp);
        fclose($fp);

        return (string)$response;
    }
}
