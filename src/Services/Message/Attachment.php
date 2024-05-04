<?php

namespace Localtools\LaravelGmail\Services\Message;

use Localtools\LaravelGmail\GmailConnection;
use Localtools\LaravelGmail\Traits\HasDecodableBody;
use Google\Service\Gmail;
use Google\Service\Gmail\MessagePart;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Attachment extends GmailConnection
{
	use HasDecodableBody;

	/**
	 * @var
	 */
	public $body;
	/**
	 * @var string
     */
	public string $id;
	/**
	 * @var string
     */
	public string $filename;
	/**
	 * @var
	 */
	public $mimeType;
	/**
	 * @var
	 */
	public $size;
	/**
	 * @var
	 */
	public $headerDetails;
	/**
	 * @var
	 */
	private $headers;
	/**
	 * @var Gmail
	 */
	private $service;

	/**
	 * @var
	 */
	private $messageId;

	/**
	 * Attachment constructor.
	 *
	 * @param $singleMessageId
	 * @param  MessagePart  $part
	 * @param  MessagePart  $part
	 * @param  int 	$userId
	 */
	public function __construct($singleMessageId, MessagePart $part, $userId = null)
	{
		parent::__construct(config(), $userId);

		$this->service = new Gmail($this);

		$body = $part->getBody();
		$this->id = $body->getAttachmentId();
		$this->size = $body->getSize();
		$this->filename = $part->getFilename();
		$this->mimeType = $part->getMimeType();
		$this->messageId = $singleMessageId;
		$headers = $part->getHeaders();
		$this->headerDetails = $this->getHeaderDetails($headers);
	}

	/**
	 * Retuns attachment ID
	 *
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Returns attachment file name
	 *
	 * @return string
	 */
	public function getFileName()
	{
		return $this->filename;
	}

	/**
	 * Returns mime type of the attachment
	 *
	 * @return string
	 */
	public function getMimeType()
	{
		return $this->mimeType;
	}

	/**
	 * Returns approximate size of the attachment
	 *
	 * @return mixed
	 */
	public function getSize()
	{
		return $this->size;
	}

	/**
	 * @param  string  $path
	 * @param  string|null  $filename
	 *
	 * @param  string  $disk
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function saveAttachmentTo($path = null, $filename = null, $disk = 'local')
	{

		$data = $this->getDecodedBody($this->getData());

		if (!$data) {
			throw new \Exception('Could not get the attachment.');
		}

		$filename = $filename ?: $this->filename;

		if (is_null($path)) {
			$path = '/';
		} else {
			if (!Str::endsWith('/', $path)) {
				$path = "{$path}/";
			}
		}

		$filePathAndName = "{$path}{$filename}";

		Storage::disk($disk)->put($filePathAndName, $data);

		return $filePathAndName;
	}

	/**
	 * @throws \Exception
	 */
	public function getData()
	{
		$attachment = $this->service->users_messages_attachments->get('me', $this->messageId, $this->id);

		return $attachment->getData();
	}

	/**
	 * Returns attachment headers
	 * Contains Content-ID and X-Attachment-Id for embedded images
	 *
	 * @return array
	 */
	public function getHeaderDetails($headers)
	{
		$headerDetails = [];

		foreach ($headers as $header) {
			$headerDetails[$header->name] = $header->value;
		}

		return $headerDetails;
	}
}
