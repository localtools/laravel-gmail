<?php

namespace Localtools\LaravelGmail\Services;

use Google\Exception;
use Illuminate\Support\Collection;

class MessageCollection extends Collection
{
    /**
     * @var Message
     */
    private Message $message;

    /**
     * MessageCollection constructor.
     *
     * @param array $items
     * @param Message|null $message
     */
    public function __construct($items = [], Message $message = null)
    {
        parent::__construct($items);
        $this->message = $message;
    }

    /**
     * @throws Exception
     */
    public function next(): MessageCollection|Collection
    {
        return $this->message->next();
    }

    /**
     * Returns boolean if the page token variable is null or not
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return !!$this->message->pageToken;
    }

    /**
     * Returns the page token or null
     *
     * @return string
     */
    public function getPageToken(): string
    {
        return $this->message->pageToken;
    }
}