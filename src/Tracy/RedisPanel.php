<?php declare(strict_types = 1);

namespace Contributte\Redis\Tracy;

use Throwable;
use Tracy\IBarPanel;

final class RedisPanel implements IBarPanel
{

	/** @var mixed[] */
	private $connections;

	/**
	 * @param mixed[] $connections
	 */
	public function __construct(array $connections)
	{
		$this->connections = $connections;
	}

	/**
	 * Renders HTML code for custom tab.
	 */
	public function getTab(): string
	{
		return '<span title="Redis panel">'
			. '<img height="16px" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAADm0lEQVQ4T61US2icVRT+zrn/YzKT5jWZTNIInSQzUxskULDRurFrxa4ki4oILUUURagi6EYQRHBhEUSqtHaRRbTbSjcuxIWVVFOfNWqGpKWxNo/JaybTztz/3ivnTwyN2mbj2fxwz3++c7/vO+cS/uegnfC+OHTI271041EAfeS5r4qXpybvVXNXwKn9+UFr+ahlfjro6e3Sf96AammFLi9fUp47zaH9tDBeWvsn+DbAX/fu3YUQIwCOgXAQREg+OIzuN98GJ5NYOTeG8kcfwGkNWNTAGLOOT+/7cXKcACfg5ACaGioMW+LjxuJIamioKcwXAOdwe/IKGteuws92o/PFEyif+RD6+jVU1qpY1BEYDveFPiwwTUynKKCPaeKBwqWU4gOC3vL4YfS89c42FtefPYra+EWQUogig9mGRdU4kDP4drWCYjKBA20pKAArUfQbfdLfN5MNVC4TKHhKIXXwEYT5IuAsbv9yBbe+vwy/ZzeCvn6YSgW17yawbhyqxmKXIqQUYzWyuNmwWIvsBI3u6f+BGEMMoN1nZAJGs9qQNrx/EF0nXkVy+GHY9XVcffIJiDkSxgGLDYv5hkE9Vk8ExCn6ZrD4xpKOXlk21By5jUySCV0ho8NnSKOwUITqSKM2/jXq1mGuYVFuWJgYA2jzCZ2+ch7cebpQGLjZm1DZgAnLkcV8fUMjCY+BjMdo9xUiuDgn9CQrJDp9hWxA8JlQ1hazdf0TjeZyJWYeaFWMroDQ4jFqxmFeWyxpB7t567+dSjAhGzLSHsW0F7TFQsNCSxdnL9L5Yv61qtavG6hmKZKCjM9IB0IWKGsTAyuiuGFr3NBiru5iRoIjf7YFVGv1+CR9ua842xVQ72rksNAwkK8EEyHtCwijiTdMWtain9mSRHpKXqhr6/BHXZdiygnmgUyo0OlzLLRotagtzCbdJgVEjuIiCZkCAZKpqEQ2NmktcrBwEzSa63+XCC/JpeRWHd6GwwkiLIlJDRtrKtHuE7oDhSYGypGLc7c2c7DmZzC/HHMZy+Vyhvk5OHcMoLScpRRBhl1oy6h4sqwOmItNcFu3d85eIKL3npqZ+Vx+2fY4nM3lEh6pEYJ7HsBDAuwTxXMm0q5qK3sLa02Vmc8aovefmZ7+/c5dvevzNbYnv9+QfQGEI2J+XGTNrFPqZAicGZmeXt229FvL8l+nd5ydy+cz2pjDlmglzfzZY6VS/V4lO77YO/T7V/ovpDmUCVPkVDYAAAAASUVORK5CYII="/>'
			. '</span>';
	}

	/**
	 * Renders HTML code for custom panel.
	 */
	public function getPanel(): string
	{
		ob_start();

		$connections = $this->connections;

		foreach ($connections as $key => $connection) {
			$start = microtime(true);
			try {
				$connections[$key]['ping'] = $connection['client']->ping('ok');
			} catch (Throwable $e) {
				$connections[$key]['ping'] = 'failed';
			} finally {
				$connections[$key]['duration'] = (microtime(true) - $start) * 1000;
			}

			try {
				$connections[$key]['dbSize'] = $connection['client']->dbsize();
			} catch (Throwable $e) {
				$connections[$key]['dbSize'] = $e->getMessage();
			}
		}

		require __DIR__ . '/templates/panel.phtml';

		return (string) ob_get_clean();
	}

}
