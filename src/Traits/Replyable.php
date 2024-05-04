<?php

namespace Localtools\LaravelGmail\Traits;

use Localtools\LaravelGmail\Services\Message\Mail;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Illuminate\Container\Container;
use Illuminate\Mail\Markdown;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * @property Gmail $service
 */
trait Replyable
{
	use HasHeaders;

	private $symfonyEmail;

	/**
	 * Gmail optional parameters
	 *
	 * @var array
	 */
	private $parameters = [];

	/**
	 * Text or html message to send
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Subject of the email
	 *
	 * @var string
	 */
	private $subject;

	/**

		   * @var string|array
		   */
	private $replyTo;

	/**
	 * @var  string
	 */
	private $replyToName;

	/**
	 * Sender's email
	 *
	 * @var string|array
	 */
	private $from;

	/**
	 * Sender's name
	 *
	 * @var  string
	 */
	private $nameFrom;

	/**
	 * Email of the recipient
	 *
	 * @var string|array
	 */
	private $to;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
	private $nameTo;

	/**
	 * Single email or array of email for a carbon copy
	 *
	 * @var array|string
	 */
	private $cc;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
	private $nameCc;

	/**
	 * Single email or array of email for a blind carbon copy
	 *
	 * @var array|string
	 */
	private $bcc;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
	private $nameBcc;

	/**
	 * List of attachments
	 *
	 * @var array
	 */
	private $attachments = [];

	private $priority = 2;

	public function __construct()
	{
		$this->symfonyEmail = new Email();
	}

	/**
	 * Receives the recipient's
	 * If multiple recipients will receive the message an array should be used.
	 * Example: array('receiver@domain.org', 'other@domain.org' => 'A name')
	 *
	 * If $name is passed and the first parameter is a string, this name will be
	 * associated with the address.
	 *
	 * @param string|array $to
	 *
	 * @param string|null $name
	 *
	 * @return Replyable
	 */
	public function to($to, $name = null)
	{
		$this->to = $this->emailList($to, $name);
		$this->nameTo = $name;

		return $this;
	}

	/**
	 * @param string|array $from
	 * 
	 * @param string|null $name
	 * 
	 * @return Replyable
	 */
	public function from($from, $name = null)
	{
		$this->from = $this->emailList($from, $name);
		$this->nameFrom = $name;

		return $this;
	}

	/**
	 * @param array|string $cc
	 *
	 * @param string|null $name
	 *
	 * @return Replyable
	 */
	public function cc($cc, $name = null)
	{
		$this->cc = $this->emailList($cc, $name);
		$this->nameCc = $name;

		return $this;
	}

	private function emailList($list, $name = null)
	{
		if (is_array($list)) {
			return $this->convertEmailList($list, $name);
		} else {
			return $list;
		}
	}

	private function convertEmailList($emails, $name = null)
	{
		$newList = [];
		$count = 0;
		foreach ($emails as $key => $email) {
			if ($email instanceof Address) {
				$newList[$email->getAddress()] = $email->getName();
			} else if (str_contains($key, '@')) {
				$newList[$key] = $email;
			} else {
				$emailName = isset($name[$count]) ? $name[$count] : (isset($name[$key]) ? $name[$key] : explode('@', $email)[0]);
				$newList[$email] = $emailName;
			}
			$count = $count + 1;
		}

		return $newList;
	}

	/**
	 * @param array|string $bcc
	 *
	 * @param string|null $name
	 *
	 * @return Replyable
	 */
	public function bcc($bcc, $name = null)
	{
		$this->bcc = $this->emailList($bcc, $name);
		$this->nameBcc = $name;

		return $this;
	}

	/**
	 * @param array|string $replyTo
	 *
	 * @param string|null $name
	 *
	 * @return Replyable
	 */
	public function replyTo($replyTo, $name = null)
	{
		$this->replyTo = $this->emailList($replyTo, $name);
		$this->replyToName = $name;

		return $this;
	}

	/**
	 * @param string $subject
	 *
	 * @return Replyable
	 */
	public function subject($subject)
	{
		$this->subject = $subject;

		return $this;
	}

	/**
	 * @param string $view
	 * @param array $data
	 * @param array $mergeData
	 *
	 * @return Replyable
	 * @throws \Throwable
	 */
	public function view($view, $data = [], $mergeData = [])
	{
		$this->message = view($view, $data, $mergeData)->render();

		return $this;
	}

	/**
	 * loads markdown file for message body
	 *
	 * @return Replyable
	 * @throws \Throwable
	 */
	public function markdown(string $markdown_view, array $data = [])
	{
		$markdown = Container::getInstance()->make(Markdown::class);

		if (config('mail.markdown.theme')) {
			$markdown->theme(config('mail.markdown.theme'));
		}

		$this->message = $markdown->render($markdown_view, $data);

		return $this;
	}

	/**
	 * @param string $message
	 *
	 * @return Replyable
	 */
	public function message($message)
	{
		$this->message = $message;

		return $this;
	}

	/**
	 * Attaches new file to the email from the Storage folder
	 *
	 * @param array $files comma separated of files
	 *
	 * @return Replyable
	 * @throws \Exception
	 */
	public function attach(...$files)
	{

		foreach ($files as $file) {

			if (!file_exists($file)) {
				throw new FileNotFoundException($file);
			}

			array_push($this->attachments, $file);
		}

		return $this;
	}

	/**
	 * The value is an integer where 1 is the highest priority and 5 is the lowest.
	 *
	 * @param int $priority
	 *
	 * @return Replyable
	 */
	public function priority($priority)
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * @param array $parameters
	 *
	 * @return Replyable
	 */
	public function optionalParameters(array $parameters)
	{
		$this->parameters = $parameters;

		return $this;
	}

	/**
	 * Reply to a specific email
	 *
	 * @return Mail
	 * @throws \Exception
	 */
	public function reply()
	{
		if (!$this->getId()) {
			throw new \Exception('This is a new email. Use send().');
		}

		$this->setReplyThread();
		$this->setReplySubject();
		$this->setReplyTo();
		$this->setReplyFrom();
		$body = $this->getMessageBody();
		$body->setThreadId($this->getThreadId());

		return new Mail($this->service->users_messages->send('me', $body, $this->parameters));
	}

	public abstract function getId();

	private function setReplyThread()
	{
		$threadId = $this->getThreadId();
		if ($threadId) {
			$this->setHeader('In-Reply-To', $this->getMessageIdHeader());
			$this->setHeader('References', $this->getHeader('References'));
			$this->setHeader('Message-ID', $this->getMessageIdHeader());
		}
	}

	private function getMessageIdHeader()
	{
		if ($messageId = $this->getHeader('Message-ID')) {
			return $messageId;
		}

		if ($messageId = $this->getHeader('Message-Id')) {
			return $messageId;
		}
		return null;
	}

	public abstract function getThreadId();

	/**
	 * Add a header to the email
	 *
	 * @param string $header
	 * @param string $value
	 */
	public function setHeader($header, $value)
	{
		$headers = $this->symfonyEmail->getHeaders();

		$headers->addTextHeader($header, $value);
	}

	private function setReplySubject()
	{
		if (!$this->subject) {
			$this->subject = $this->getSubject();
		}
	}

	private function setReplyTo()
	{
		if (!$this->to) {
			$replyTo = $this->getReplyTo();

			$this->to = $replyTo['email'];
			$this->nameTo = $replyTo['name'];
		}
	}

	private function setReplyFrom()
	{
		if (!$this->from) {
			$this->from = $this->getUser();
			if (!$this->from) {
				throw new \Exception('Reply from is not defined');
			}
		}
	}

	public abstract function getSubject();

	public abstract function getReplyTo();

	public abstract function getUser();

	/**
	 * @return Message
	 */
	private function getMessageBody()
	{
		$body = new Message();

		$this->symfonyEmail
			->from(...$this->fromAddress())
			->to(...$this->toAddress())
			->cc(...$this->ccAddress())
			->bcc(...$this->bccAddress())
			->replyTo(...$this->replyToAddress())
			->subject($this->subject)
			->html($this->message)
			->priority($this->priority);

		foreach ($this->attachments as $file) {
			$this->symfonyEmail->attachFromPath($file);
		}

		$body->setRaw($this->base64_encode($this->symfonyEmail->toString()));

		return $body;
	}

	/**
	 * @return Address[]
	 */
	public function replyToAddress()
	{
		return $this->mapAddress($this->replyTo, $this->replyToName);
	}

	/**
	 * @return Address[]
	 */
	public function ccAddress()
	{
		return $this->mapAddress($this->cc, $this->nameCc);
	}

	/**
	 * @return Address[]
	 */
	public function bccAddress()
	{
		return $this->mapAddress($this->bcc, $this->nameBcc);
	}

	/**
	 * @return Address[]
	 */
	public function toAddress()
	{
		return $this->mapAddress($this->to, $this->nameTo);
	}

	/**
	 * @return Address[]
	 */
	public function fromAddress()
	{
		return $this->mapAddress($this->from, $this->nameFrom);
	}

	/**
	 * @param array|string $email
	 * @param string|null $name
	 * @return Address[]
	 */
	private function mapAddress($email, $name = null)
	{
		/** @var Address[] */
		$result = [];

		if ($email) {
			if (is_array($email)) {
				foreach ($email as $emailAddress => $emailName) {
					$result[] = new Address($emailAddress, $emailName);
				}
			} else {
				$result[] = new Address($email, $name ?: '');
			}
		}

		return $result;
	}

	private function base64_encode($data)
	{
		return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
	}

	/**
	 * Sends a new email
	 *
	 * @return self|Mail
	 */
	public function send()
	{
		$body = $this->getMessageBody();

		$this->setMessage($this->service->users_messages->send('me', $body, $this->parameters));

		return $this;
	}

	protected abstract function setMessage(Message $message);
}