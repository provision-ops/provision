<?php
/**
 * @file Site.php
 *
 *       Apache Configuration for Server Context.
 * @see \Provision_Config_Apache_Site
 * @see \Provision_Config_Http_Site
 */

namespace Aegir\Provision\Service\Http\Nginx\Configuration;

use Aegir\Provision\Service\Http\Apache\Configuration\SiteConfigFile as BaseSiteConfigFile;

class SiteConfigFile extends BaseSiteConfigFile {
  
  const SERVICE_TYPE = 'nginx';

  function process() {
    parent::process();
    $this->data['php_sock_location'] = $this->context->getSubscription('http')->getProperty('php_sock_location');;
  }

}