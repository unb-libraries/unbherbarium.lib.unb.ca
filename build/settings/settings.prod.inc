<?php

/**
 * @file
 * Include prod-environment-only settings here.
 */

$settings['config_readonly'] = TRUE;
$settings['trusted_host_patterns'] = [
  '^unbherbarium\.lib\.unb\.ca$',
  '^127\.0\.0\.1$',
  '^10\.244\.[0-9]+\.[0-9]+$',
];
$settings['specimen_lts_archive'] = 'hilstorage.hil.unb.ca:6983';
