<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

return [
	'routes' => [
		[
			'name' => 'settings#save',
			'url' => '/api/v1/admin/settings',
			'verb' => 'POST',
		],
	],
];
