<?php

namespace Localtools\LaravelGmail\Services;

use Localtools\LaravelGmail\LaravelGmailClass;
use Localtools\LaravelGmail\Services\Message\Mail;
use Localtools\LaravelGmail\Traits\Filterable;
use Localtools\LaravelGmail\Traits\SendsParameters;
use Google\Service\Gmail;
use Psr\Http\Message\RequestInterface;

class Message
{

	use SendsParameters,
		Filterable;

	public $service;

	public $preload = false;

	public $pageToken;

	public $client;

	/**
	 * Optional parameter for getting single and multiple emails
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * Message constructor.
	 *
	 * @param LaravelGmailClass $client
	 */
	public function __construct(LaravelGmailClass $client)
	{
		$this->client = $client;
		$this->service = new Gmail($client);
	}

	/**
	 * Returns next page if available of messages or an empty collection
	 *
	 * @return \Illuminate\Support\Collection
	 * @throws \Google\Exception
	 */
	public function next()
	{
		if ($this->pageToken) {
			return $this->all($this->pageToken);
		} else {
			return new MessageCollection([], $this);
		}
	}

	/**
	 * Returns a collection of Mail instances
	 *
	 * @param string|null $pageToken
	 *
	 * @return \Illuminate\Support\Collection
	 * @throws \Google\Exception
	 */
	public function all(string $pageToken = null)
	{
		if (!is_null($pageToken)) {
			$this->add($pageToken, 'pageToken');
		}

		$mails = [];
		$response = $this->getMessagesResponse();
		$this->pageToken = method_exists($response, 'getNextPageToken') ? $response->getNextPageToken() : null;

		$messages = $response->getMessages();

		if (!$this->preload) {
			foreach ($messages as $message) {
				$mails[] = new Mail($message, $this->preload, $this->client->userId);
			}
		} else {
			$mails = count($messages) > 0 ? $this->batchRequest($messages) : [];
		}

		return new MessageCollection($mails, $this);
	}

	/**
	 * Returns boolean if the page token variable is null or not
	 *
	 * @return bool
	 */
	public function hasNextPage()
	{
		return !!$this->pageToken;
	}

	/**
	 * Limit the messages coming from the queryxw
	 *
	 * @param int $number
	 *
	 * @return Message
	 */
	public function take($number)
	{
		$this->params['maxResults'] = abs((int)$number);

		return $this;
	}

	/**
	 * @param $id
	 *
	 * @return Mail
	 */
	public function get($id)
	{
		$message = $this->getRequest($id);

		return new Mail($message, false, $this->client->userId);
	}

	/**
	 * Creates a batch request to get all emails in a single call
	 *
	 * @param $allMessages
	 *
	 * @return array|null
	 */
	public function batchRequest($allMessages)
	{
		$this->client->setUseBatch(true);

		$batch = $this->service->createBatch();

		foreach ($allMessages as $key => $message) {
			$batch->add($this->getRequest($message->getId()), $key);
		}

		$messagesBatch = $batch->execute();

		$this->client->setUseBatch(false);

		$messages = [];

		foreach ($messagesBatch as $message) {
			$messages[] = new Mail($message, false, $this->client->userId);
		}

		return $messages;
	}

	/**
	 * Preload the information on each Mail objects.
	 * If is not preload you will have to call the load method from the Mail class
	 * @return $this
	 * @see Mail::load()
	 *
	 */
	public function preload()
	{
		$this->preload = true;

		return $this;
	}

	public function getUser()
	{
		return $this->client->user();
	}

	/**
	 * @param $id
	 *
	 * @return \Google\Service\Gmail\Message|RequestInterface
	 */
	private function getRequest($id)
	{
		return $this->service->users_messages->get('me', $id);
	}

	/**
	 * @return \Google\Service\Gmail\ListMessagesResponse|object
	 * @throws \Google\Exception
	 */
	private function getMessagesResponse()
	{
		$responseOrRequest = $this->service->users_messages->listUsersMessages('me', $this->params);
		return $responseOrRequest;
	}
}
