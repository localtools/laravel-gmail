<?php

namespace Localtools\LaravelGmail\Services\Message;

use Carbon\Carbon;
use Exception;
use Google\Service\Gmail\MessagePartHeader;
use Localtools\LaravelGmail\GmailConnection;
use Localtools\LaravelGmail\Traits\HasDecodableBody;
use Localtools\LaravelGmail\Traits\HasParts;
use Localtools\LaravelGmail\Traits\Modifiable;
use Localtools\LaravelGmail\Traits\Replyable;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class SingleMessage
 *
 * @package Cerbaro\LaravelGmail\services
 */
class Mail extends GmailConnection
{

    use HasDecodableBody,
        Modifiable,
        HasParts,
        Replyable {
        Replyable::__construct as private __rConstruct;
        Modifiable::__construct as private __mConstruct;
    }

    public string $id;

    public string|int|null $userId;

    public int $internalDate;

    public array $labels;

    public mixed $size;

    public string $threadId;
    public string $historyId;

    public mixed $payload;

    public mixed $parts;

    public mixed $service;

    /**
     * SingleMessage constructor.
     *
     * @param Message|null $message
     * @param bool $preload
     * @param string|int|null $userId
     */
    public function __construct(Message $message = null, $preload = false, string|int|null $userId = null)
    {
        $this->service = new Gmail($this);

        $this->__rConstruct();
        $this->__mConstruct();
        parent::__construct(config(), $userId);

        if (!is_null($message)) {
            if ($preload) {
                $message = $this->service->users_messages->get('me', $message->getId());
            }

            $this->setUserId($userId);

            $this->setMessage($message);

            if ($preload) {
                $this->setMetadata();
            }
        }
    }

    /**
     * Set user Id
     *
     * @param int $userId
     */
    protected function setUserId($userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Sets data from mail
     *
     * @param Message $message
     */
    protected function setMessage(Message $message): void
    {
        $this->id = $message->getId();
        $this->internalDate = $message->getInternalDate();
        $this->labels = $message->getLabelIds();
        $this->size = $message->getSizeEstimate();
        $this->threadId = $message->getThreadId();
        $this->historyId = $message->getHistoryId();
        $this->payload = $message->getPayload();
        if ($this->payload) {
            $this->parts = collect($this->payload->getParts());
        }
    }

    /**
     * Sets the metadata from Mail when preloaded
     */
    protected function setMetadata(): void
    {
        $this->to = $this->getTo();
        $from = $this->getFrom();
        $this->from = $from['email'] ?? null;
        $this->nameFrom = $from['email'] ?? null;

        $this->subject = $this->getSubject();
    }

    /**
     * Return a UNIX version of the date
     *
     * @return int UNIX date
     */
    public function getInternalDate(): int
    {
        return $this->internalDate;
    }

    /**
     * Returns the labels of the email
     * Example: INBOX, STARRED, UNREAD
     *
     * @return array
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Returns approximate size of the email
     *
     * @return mixed
     */
    public function getSize(): mixed
    {
        return $this->size;
    }

    /**
     * Returns thread ID of the email
     *
     * @return string
     */
    public function getThreadId(): string
    {
        return $this->threadId;
    }

    /**
     * Returns history ID of the email
     *
     * @return string
     */
    public function getHistoryId(): string
    {
        return $this->historyId;
    }

    /**
     * Returns all the headers of the email
     *
     * @return Collection
     */
    public function getHeaders(): Collection
    {
        return $this->buildHeaders($this->payload->getHeaders());
    }

    /**
     * Returns the subject of the email
     *
     * @return string
     */
    public function getSubject(): string
    {
        return $this->getHeader('Subject');
    }

    /**
     * Returns the subject of the email
     *
     * @return array|string
     */
    public function getReplyTo(): array|string
    {
        $replyTo = $this->getHeader('Reply-To');

        return $this->getFrom($replyTo ? $replyTo : $this->getHeader('From'));
    }

    /**
     * Returns array of name and email of each recipient
     *
     * @param string|null $email
     * @return array
     */
    public function getFrom(string $email = null): array
    {
        $from = $email ? $email : $this->getHeader('From');

        preg_match('/<(.*)>/', $from, $matches);

        $name = preg_replace('/ <(.*)>/', '', $from);

        return [
            'name' => $name,
            'email' => $matches[1] ?? null,
        ];
    }

    /**
     * Returns email of sender
     *
     * @return string|null
     */
    public function getFromEmail(): ?string
    {
        $from = $this->getHeader('From');

        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }

        preg_match('/<(.*)>/', $from, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Returns name of the sender
     *
     * @return string|null
     */
    public function getFromName(): ?string
    {
        $from = $this->getHeader('From');

        return preg_replace('/ <(.*)>/', '', $from);
    }

    /**
     * Returns array list of recipients
     *
     * @return array
     */
    public function getTo(): array
    {
        $allTo = $this->getHeader('To');

        return $this->formatEmailList($allTo);
    }

    /**
     * Returns array list of cc recipients
     *
     * @return array
     */
    public function getCc(): array
    {
        $allCc = $this->getHeader('Cc');

        return $this->formatEmailList($allCc);
    }

    /**
     * Returns array list of bcc recipients
     *
     * @return array
     */
    public function getBcc(): array
    {
        $allBcc = $this->getHeader('Bcc');

        return $this->formatEmailList($allBcc);
    }

    /**
     * Returns an array of emails from an string in RFC 822 format
     *
     * @param string $emails email list in RFC 822 format
     *
     * @return array
     */
    public function formatEmailList($emails): array
    {
        $all = [];
        $explodedEmails = explode(',', $emails);

        foreach ($explodedEmails as $email) {

            $item = [];

            preg_match('/<(.*)>/', $email, $matches);

            $item['email'] = str_replace(' ', '', isset($matches[1]) ? $matches[1] : $email);

            $name = preg_replace('/ <(.*)>/', '', $email);

            if (Str::startsWith($name, ' ')) {
                $name = substr($name, 1);
            }

            $item['name'] = str_replace("\"", '', $name ?: null);

            $all[] = $item;
        }

        return $all;
    }

    /**
     * Returns the original date that the email was sent
     *
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        return Carbon::parse($this->getHeader('Date'));
    }

    /**
     * Returns email of the original recipient
     *
     * @return string
     */
    public function getDeliveredTo(): string
    {
        return $this->getHeader('Delivered-To');
    }

    /**
     * Base64 version of the body
     *
     * @return bool|string|null
     * @throws Exception
     */
    public function getRawPlainTextBody(): bool|string|null
    {
        return $this->getPlainTextBody(true);
    }

    /**
     * @param bool $raw
     *
     * @return bool|string|null
     * @throws Exception
     */
    public function getPlainTextBody(bool $raw = false): bool|string|null
    {
        $content = $this->getBody();

        return $raw ? $content : $this->getDecodedBody($content);
    }

    /**
     * Returns a specific body part from an email
     *
     * @param string $type
     *
     * @return null|string
     * @throws Exception
     */
    public function getBody(string $type = 'text/plain'): ?string
    {
        $parts = $this->getAllParts($this->parts);

        try {
            if (!$parts->isEmpty()) {
                foreach ($parts as $part) {
                    if ($part->mimeType == $type) {
                        return $part->body->data;
                        //if there are no parts in payload, try to get data from body->data
                    } elseif ($this->payload->body->data) {
                        return $this->payload->body->data;
                    }
                }
            } else {
                return $this->payload->body->data;
            }
        } catch (Exception $exception) {
            throw new Exception("Preload or load the single message before getting the body.");
        }

        return null;
    }

    /**
     * True if message has at least one attachment.
     *
     * @return boolean
     */
    public function hasAttachments(): bool
    {
        $parts = $this->getAllParts($this->parts);
        $has = false;

        foreach ($parts as $part) {
            if (!empty($part->body->attachmentId) && $part->getFilename() != null && strlen($part->getFilename()) > 0) {
                $has = true;
                break;
            }
        }

        return $has;
    }

    /**
     * Number of attachments of the message.
     *
     * @return int
     */
    public function countAttachments(): int
    {
        $numberOfAttachments = 0;
        $parts = $this->getAllParts($this->parts);

        foreach ($parts as $part) {
            if (!empty($part->body->attachmentId)) {
                $numberOfAttachments++;
            }
        }

        return $numberOfAttachments;
    }

    /**
     * Decodes the body from gmail to make it readable
     *
     * @param $content
     * @return bool|string
     */
    public function getDecodedBody($content): bool|string
    {
        $content = str_replace('_', '/', str_replace('-', '+', $content));

        return base64_decode($content);
    }

    /**
     * @return string base64 version of the body
     */
    public function getRawHtmlBody(): bool|string|null
    {
        return $this->getHtmlBody(true);
    }

    /**
     * Gets the HTML body
     *
     * @param bool $raw
     *
     * @return bool|string|null
     * @throws Exception
     */
    public function getHtmlBody(bool $raw = false): bool|string|null
    {
        $content = $this->getBody('text/html');

        return $raw ? $content : $this->getDecodedBody($content);
    }

    /**
     * Get a collection of attachments with full information
     *
     * @return Collection
     * @throws Exception
     */
    public function getAttachmentsWithData(): Collection
    {
        return $this->getAttachments(true);
    }

    /**
     * Returns a collection of attachments
     *
     * @param bool $preload Preload only the attachment's 'data'.
     * But does not load the other attachment info like filename, mimetype, etc..
     *
     * @return Collection
     * @throws Exception
     */
    public function getAttachments(bool $preload = false): Collection
    {
        $attachments = new Collection();
        $parts = $this->getAllParts($this->parts);

        foreach ($parts as $part) {
            if (!empty($part->body->attachmentId)) {
                $attachment = (new Attachment($part->body->attachmentId, $part, $this->userId));

                if ($preload) {
                    $attachment = $attachment->getData();
                }

                $attachments->push($attachment);
            }
        }

        return $attachments;
    }

    /**
     * Returns ID of the email
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Gets the user email from the config file
     *
     * @return mixed|null
     */
    public function getUser(): mixed
    {
        return $this->config('email');
    }

    /**
     * Get's the gmail information from the Mail
     *
     * @return Mail
     */
    public function load(): Mail
    {
        $message = $this->service->users_messages->get('me', $this->getId());

        return new self($message);
    }

    /**
     * Sets the access token in case we wanna use a different token
     *
     * @param string $token
     *
     * @return Mail
     */
    public function using(string $token): static
    {
        $this->setToken($token);

        return $this;
    }

    /**
     * checks if message has at least one part without iterating through all parts
     *
     * @return bool
     */
    public function hasParts(): bool
    {
        return !!$this->iterateParts($this->parts, $returnOnFirstFound = true);
    }

    /**
     * Gets all the headers from an email and returns a collections
     *
     * @param $emailHeaders
     * @return Collection
     */
    private function buildHeaders($emailHeaders): Collection
    {
        $headers = [];

        foreach ($emailHeaders as $header) {
            /** @var MessagePartHeader $header */

            $head = new \stdClass();

            $head->key = $header->getName();
            $head->value = $header->getValue();

            $headers[] = $head;
        }

        return collect($headers);
    }
}