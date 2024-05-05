<?php

namespace Localtools\LaravelGmail;

use Google\Service\Gmail\Profile;
use Illuminate\Http\RedirectResponse;
use Localtools\LaravelGmail\Exceptions\AuthException;
use Localtools\LaravelGmail\Services\Message;
use Illuminate\Support\Facades\Redirect;

class LaravelGmailClass extends GmailConnection
{
    public function __construct($config, $userId = null)
    {
        if (class_basename($config) === 'Application') {
            $config = $config['config'];
        }

        parent::__construct($config, $userId);
    }

    /**
     * @return Message
     * @throws AuthException
     */
    public function message(): Message
    {
        if (!$this->getToken()) {
            throw new AuthException('No credentials found.');
        }

        return new Message($this);
    }

    /**
     * Returns the Gmail user email
     *
     * @return Profile
     */
    public function user(): Profile
    {
        return $this->config('email');
    }

    /**
     * Updates / sets the current userId for the service
     *
     * @param $userId
     * @return LaravelGmailClass
     */
    public function setUserId($userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->getAuthUrl());
    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        return $this->createAuthUrl();
    }

    public function logout(): void
    {
        $this->revokeToken();
        $this->deleteAccessToken();
    }
}
