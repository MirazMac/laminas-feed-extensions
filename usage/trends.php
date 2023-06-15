<?php


use MirazMac\LaminasFeed\LaminasFeedExtensions;
use MirazMac\LaminasFeed\Reader\Feed;

require_once '../vendor/autoload.php';

LaminasFeedExtensions::register();


/**
 * @var Feed $feed
 */
$feed = Laminas\Feed\Reader\Reader::importFile('trends.xml');

foreach ($feed as $entry) {
    $data = [
        'author' => $entry->getAuthor(),
        'content' => $entry->getContent(),
        'description' => $entry->getDescription(),
        'newsItems' => $entry->getNewsItems(),
        'approxTraffic' => $entry->getApproxTraffic()
    ];

}