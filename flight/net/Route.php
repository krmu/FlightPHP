<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\net;

/**
 * The Route class is responsible for routing an HTTP request to
 * an assigned callback function. The Router tries to match the
 * requested URL against a series of URL patterns.
 */
final class Route
{
    /**
     * @var string URL pattern
     */
    public string $pattern;

    /**
     * @var mixed Callback function
     */
    public $callback;

    /**
     * @var array<int, string> HTTP methods
     */
    public array $methods = [];

    /**
     * @var array<int, ?string> Route parameters
     */
    public array $params = [];

    /**
     * @var string|null Matching regular expression
     */
    public ?string $regex = null;

    /**
     * @var string URL splat content
     */
    public string $splat = '';

    /**
     * @var bool Pass self in callback parameters
     */
    public bool $pass = false;

	/**
	 * @var string The alias is a way to identify the route using a simple name ex: 'login' instead of /admin/login
	 */
	public string $alias = '';

	/**
	 * @var array<callable> The middleware to be applied to the route
	 */
	public array $middleware = [];

    /**
     * Constructor.
     *
     * @param string $pattern  URL pattern
     * @param callable  $callback Callback function
     * @param array<int, string>  $methods  HTTP methods
     * @param bool   $pass     Pass self in callback parameters
     */
    public function __construct(string $pattern, $callback, array $methods, bool $pass, string $alias = '')
    {
        $this->pattern = $pattern;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->pass = $pass;
		$this->alias = $alias;
    }

    /**
     * Checks if a URL matches the route pattern. Also parses named parameters in the URL.
     *
     * @param string $url            Requested URL
     * @param bool   $case_sensitive Case sensitive matching
     *
     * @return bool Match status
     */
    public function matchUrl(string $url, bool $case_sensitive = false): bool
    {
        // Wildcard or exact match
        if ('*' === $this->pattern || $this->pattern === $url) {
            return true;
        }

        $ids = [];
        $last_char = substr($this->pattern, -1);

        // Get splat
        if ('*' === $last_char) {
            $n = 0;
            $len = \strlen($url);
            $count = substr_count($this->pattern, '/');

            for ($i = 0; $i < $len; $i++) {
                if ('/' === $url[$i]) {
                    $n++;
                }
                if ($n === $count) {
                    break;
                }
            }

            $this->splat = (string) substr($url, $i + 1);
        }

        // Build the regex for matching
        $regex = str_replace([')', '/*'], [')?', '(/?|/.*?)'], $this->pattern);

        $regex = preg_replace_callback(
            '#@([\w]+)(:([^/\(\)]*))?#',
            static function ($matches) use (&$ids) {
                $ids[$matches[1]] = null;
                if (isset($matches[3])) {
                    return '(?P<' . $matches[1] . '>' . $matches[3] . ')';
                }

                return '(?P<' . $matches[1] . '>[^/\?]+)';
            },
            $regex
        );

        // Fix trailing slash
        if ('/' === $last_char) {
            $regex .= '?';
        } // Allow trailing slash
        else {
            $regex .= '/?';
        }

        // Attempt to match route and named parameters
        if (preg_match('#^' . $regex . '(?:\?[\s\S]*)?$#' . (($case_sensitive) ? '' : 'i'), $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = (\array_key_exists($k, $matches)) ? urldecode($matches[$k]) : null;
            }

            $this->regex = $regex;

            return true;
        }

        return false;
    }

    /**
     * Checks if an HTTP method matches the route methods.
     *
     * @param string $method HTTP method
     *
     * @return bool Match status
     */
    public function matchMethod(string $method): bool
    {
        return \count(array_intersect([$method, '*'], $this->methods)) > 0;
    }

	/**
	 * Checks if an alias matches the route alias.
	 *
	 * @param string $alias [description]
	 * @return boolean
	 */
	public function matchAlias(string $alias): bool
	{
		return $this->alias === $alias;
	}

	/**
	 * Hydrates the route url with the given parameters
	 *
	 * @param array<string,mixed> $params the parameters to pass to the route
	 * @return string
	 */
	public function hydrateUrl(array $params = []): string {
		$url = preg_replace_callback("/(?:@([a-zA-Z0-9]+)(?:\:([^\/]+))?\)*)/i", function($match) use ($params) {
			if(isset($match[1]) && isset($params[$match[1]])) {
				return $params[$match[1]];
			}
		}, $this->pattern);

		// catches potential optional parameter
		$url = str_replace('(/', '/', $url);
		// trim any trailing slashes
		$url = rtrim($url, '/');
		return $url;
	}

	/**
	 * Sets the route alias
	 *
	 * @return self
	 */
	public function setAlias(string $alias): self {
		$this->alias = $alias;
		return $this;
	}

	/**
	 * Sets the route middleware
	 *
	 * @param array<callable>|callable $middleware
	 * @return self
	 */
	public function addMiddleware($middleware): self {
		if(is_array($middleware) === true) {
			$this->middleware = array_merge($this->middleware, $middleware);
		} else {
			$this->middleware[] = $middleware;
		}
		return $this;
	}
}
