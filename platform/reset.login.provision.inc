<?php
/**
 * @file
 *   Provision hooks for the provision login_reset command.
 */

/**
 * Provision login-reset command
 *
 * These are the hooks that will be executed by the drush_invoke function
 * when generating a one-time login reset URL.
 */

/**
 * Generate a one-time login reset url
 */
function drush_provision_drupal_provision_login_reset() {
  $uri = d()->redirection ?: d()->uri;
  $result = drush_invoke_process(d()->name, 'user-login', array(), array('uri' => $uri, 'no-browser' => TRUE));

  drush_set_option('login_link', $result['output']);
  drush_log(dt('Login url: !onetime', array('!onetime' => $result['output'])), 'success');
}
