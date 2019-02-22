<?php
/**
 * @file
 * Provides the Provision_Config_Drupal_Services class.
 */

class Provision_Config_Drupal_Services extends Provision_Config {
  public $template = 'aegir.services.tpl.php';
  public $description = 'Drupal aegir.services.yml file';
  protected $mode = 0440;

  function filename() {
    return $this->site_path . '/aegir.services.yml';
  }

  function process() {
    $this->version = provision_version();
    $this->cookie_domain = $this->getCookieDomain();
    $this->group = $this->platform->server->web_group;
  }

  /**
   * Extract our cookie domain from the URI.
   */
  protected function getCookieDomain() {
    $uri = explode('.', $this->uri);
    # Leave base domain; only strip out subdomains.
    if (count($uri) > 2) {
      $uri[0] = '';
    }
    return implode('.', $uri);
  }

}
