<?php

class PhpShell_Action extends Basic_Action
{
	public $encoding = 'UTF-8';
	public $title = 'Run code in 200+ PHP versions simultaneously';
	public $user;
	public $bodyClass;
	public $adminMessage;
	public $cspDirectives = [
		'script-src' => [
			"'self'",
			'cdn.jsdelivr.net',
		],
		'manifest-src' => ["'self'"],
		'frame-ancestors' => ["'none'"],
		'worker-src' => [ # valid sources for web-workers
			"'self'",
			"blob:", # required by ace 1.2.8
			'cdn.jsdelivr.net',
		],
		'connect-src' => ["'self'"], # for xhr
		'base-uri' => ["'self'"],
		'form-action' => ["'self'"],
		'img-src' => ["'self'", 'data:',],
		'style-src' => [
			"'self'",
			"'unsafe-inline'", # for ace-editor & tagcloud
		]
	];
	public $httpPreloads = [];

	public function init(): void
	{
		Basic::$cache = new PhpShell_ColdCacheStampede;

		Basic::$database->exec("SET statement_timeout TO 2500;");

		// For now; don't autoStart sessions
		if (isset($_COOKIE[ Basic::$config->Session->name ]))
		{
			session_name(Basic::$config->Session->name);
			session_set_cookie_params(Basic::$config->lifetime, Basic::$config->Site->baseUrl);
			session_start();
		}

		if (isset($_SESSION['userId']))
			$this->user = PhpShell_User::get($_SESSION['userId']);
		elseif (!empty($_COOKIE))
		{
			foreach (array_keys($_COOKIE) as $name)
				setcookie($name, '', strtotime('-1 day'));
		}

		header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
		header('X-Xss-Protection: 1; mode=block');
		header('X-Content-Type-Options: nosniff');
		header("Feature-Policy: geolocation 'none'");
		header('X-Frame-Options: DENY');
		header('Referrer-Policy: origin-when-cross-origin');

		$csp = "default-src 'none'; ";
		foreach ($this->cspDirectives as $directive => $settings)
			$csp .= $directive .' '.implode(' ', $settings). '; ';

		header('Content-Security-Policy: '. $csp);

		if (0 && $_GET['waa']=='meukee')
		{
			$wasOn = Basic::$config->PRODUCTION_MODE;
			Basic::$config->PRODUCTION_MODE = false;

			if ($wasOn)
				Basic::$log->start(get_class(Basic::$action) .'::init');
		}

		if (isset($_GET['resetOpcache']) && $_GET['resetOpcache'] == sha1_file(APPLICATION_PATH .'/htdocs/index.php'))
			die(print_r(opcache_get_status(false)+['RESET' => opcache_reset()]));

		if ('application/json' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'application/json';
		elseif ('text/plain' == $_SERVER['HTTP_ACCEPT'])
			$this->contentType = 'text/plain';

		if (Basic::$config->PRODUCTION_MODE && 'text/html' == $this->contentType)
		{
			$preloads = Basic::$cache->get(__CLASS__.'::staticPreloads', function(){
				return [
					// match hashes put in tpls by update-online
					'/s/c.'. substr(hash('sha256', file_get_contents(APPLICATION_PATH .'/htdocs/s/c.css')), 0, 8). '.css' => 'style',
					'/s/c.'. substr(hash('sha256', file_get_contents(APPLICATION_PATH .'/htdocs/s/c.js' )), 0, 8). '.js'  => 'script',
					// dynamically fetch correct version
					explode("'", file_get_contents(APPLICATION_PATH .'/htdocs/s/worker-php.js'))[1] => 'worker',
				];
			}, 3600) + $this->httpPreloads;

			foreach ($preloads as $link => $type)
				header('Link: <'. $link .'>; rel=preload; as='. $type, false);
		}

		// Since we resolve everything to 'script'; prevent random strings in bodyClass
		if (! Basic::$action instanceof PhpShell_Action_Script)
			$this->bodyClass = trim($this->bodyClass .' '.Basic::$userinput['action']);

		try
		{
			$this->adminMessage = Basic::$cache->get('banMessage::'. $_SERVER['REMOTE_ADDR']);
		}
		catch (Basic_Memcache_ItemNotFoundException $e)
		{
			#care
		}

		try
		{
			$this->adminMessage = Basic::$cache->get('adminMessage::'. $_SERVER['REMOTE_ADDR']);
			Basic::$cache->delete('adminMessage::'. $_SERVER['REMOTE_ADDR']);
		}
		catch (Basic_Memcache_ItemNotFoundException $e)
		{
			#care
		}

		if (isset($this->adminMessage))
		{
			header('X-Accel-Expires: 0');
			$this->_cacheLength = 0;
		}

		parent::init();
	}

	protected function _handleLastModified(): void
	{
		if (isset($_SESSION['userId']) || 'text/html' != $this->contentType)
			$this->_cacheLength = 0;

		parent::_handleLastModified();
	}

	public static function resolve(string $action, bool $hasClass, bool $hasTemplate): ?string
	{
		if ($hasClass)
			return null;

		return 'script';
	}
}