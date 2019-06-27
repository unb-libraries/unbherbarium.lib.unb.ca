<?php
/*
Include all settings overrides here
# require_once 'settings_file.inc';
*/

// Load environment based includes.
if (isset($_SERVER['APPLICATION_ENV'])) {
  $environment = strtolower($_SERVER['APPLICATION_ENV']);
  $environment_include = dirname(__FILE__) . "/settings.$environment.inc";
  if (file_exists($environment_include)) {
    require_once $environment_include;
  }
}

// Redis.
$settings['cache_prefix']['default'] = 'unbherb_';
$conf['chq_redis_cache_enabled'] = TRUE;
require_once dirname(__FILE__) . "/settings.redis.inc";

// Set the private filesystem path.
$settings['file_private_path']  = '/app/private_filesystem';
