<?php

namespace Localtools\LaravelGmail\Facade;

use Illuminate\Support\Facades\Facade;

class LaravelGmail extends Facade
{
	protected static function getFacadeAccessor(): string
	{
		return 'laravelgmail';
	}
}