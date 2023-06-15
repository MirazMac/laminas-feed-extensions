<?php

use MirazMac\LaminasFeed\LaminasFeedExtensions;

require_once '../vendor/autoload.php';

LaminasFeedExtensions::register();


/**
 * @var \MirazMac\LaminasFeed\Reader\Feed $feed
 */
$feed = Laminas\Feed\Reader\Reader::importFile('feed.xml');


foreach ($feed as $entry) {
    $edata = [
        'title'        => html_entity_decode($entry->getTitle()),
        'description'  => $entry->getDescription(),
        'dateModified' => $entry->getDateModified() ? $entry->getDateModified()->format(DateTimeInterface::RSS) : null,
        'authors'      => $entry->getAuthors(),
        'link'         => $entry->getLink(),
        'content'      => $entry->getContent(),
        'featimg'      => $entry->getFeaturedImage(
            $entry,
            [
                'ignoreImageIfContains' => [],
            ]
        ),
    ];

    dump($edata);


    echo "<hr>";
}
