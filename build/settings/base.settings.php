<?php

/**
 * @file
 * Include global settings overrides here.
 */

// Redis.
$settings['cache_prefix']['default'] = 'unbherb_';
$conf['chq_redis_cache_enabled'] = TRUE;
require_once dirname(__FILE__) . "/settings.redis.inc";

// Set the private filesystem path.
$settings['file_private_path'] = '/app/private_filesystem';
