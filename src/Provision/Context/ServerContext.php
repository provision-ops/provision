<?php

namespace Aegir\Provision\Context;

use Aegir\Provision\Console\Config;
use Aegir\Provision\ServiceProvider;
use Aegir\Provision\Property;
use Aegir\Provision\Provision;
use Psr\Log\LogLevel;
use Robo\ResultData;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ServerContext
 *
 * @package Aegir\Provision\Context
 *
 * @see \Provision_Context_server
 */
class ServerContext extends ServiceProvider implements ConfigurationInterface
{
    /**
     * @var string
     * 'server', 'platform', or 'site'.
     */
    public $type = 'server';
    const TYPE = 'server';

    /**
     * @var string
     * The path to store the server's configuration files in.  ie. /var/aegir/config/server_master.
     */
    public $server_config_path;

    /**
     * ServerContext constructor.
     *
     * Prepares "server_config_path" as the place to store this server's service
     * configuration files (apache configs, etc.).
     *
     * @param $name
     * @param Provision $provision
     * @param array $options
     */
    function __construct($name, Provision $provision, array $options = [])
    {
        // @TODO: Create a 'servers_path' to keep things nice and clean.
        parent::__construct($name, $provision, $options);

        // If server_config_path property is empty, generate it from provision config_path + server name.
        if (empty($this->getProperty('server_config_path'))) {
            $this->server_config_path = $this->getProvision()->getConfig()->get('config_path') . DIRECTORY_SEPARATOR . $name;
            $this->setProperty('server_config_path', $this->server_config_path);
        }
        else {
            $this->server_config_path = $this->getProperty('server_config_path');
        }
    }

    /**
     * @return string|Property[]
     */
    static function option_documentation()
    {
        return [
            'context_class' => Provision::newProperty()
                ->description('The name of the class to load for this context.')
                ->hidden()
                ->defaultValue(self::getClassName(self::TYPE))
            ,
            'remote_host' =>
                Provision::newProperty()
                    ->description('server: host name')
                    ->required(TRUE)
                    ->defaultValue('localhost')
                    ->validate(function($remote_host) {
                        // If remote_host doesn't resolve to anything, warn the user.
                        $ip = gethostbynamel($remote_host);
                        if (empty($ip)) {
                            throw new \RuntimeException("Hostname $remote_host does not resolve to an IP address. Please try again.");
                        }
                        return $remote_host;
                  }),
            'ip_addresses' =>
                Provision::newProperty()
                    ->description('server: IP Addresses')
                    ->required(FALSE)
                    ->validate(function($ip_addresses) {
                        $ips = explode(',', $ip_addresses);
                        foreach ($ips as $ip) {
                            // If remote_host doesn't resolve to anything, warn the user.
                            if (!ServerContext::valid_ip($ip)) {
                                throw new \RuntimeException("IP $ip is invalid.");
                            }
                        }
                        // @TODO: Figure out how to allow array values in service configs.
                        return implode(',', $ips);
                    }),
            'script_user' =>
                Provision::newProperty()
                    ->description('server: OS user name')
                    ->required(TRUE)
                    ->defaultValue(Config::getScriptUser()),
            'aegir_root' =>
                Provision::newProperty()
                    ->description('server: aegir user home directory')
                    ->required(TRUE)
                    ->defaultValue(Config::getHomeDir()),
//            // @TODO: Why do server contexts need a master_url?
//            'master_url' =>
//                Provision::newProperty()
//                    ->description('server: Hostmaster URL')
//                    ->required(FALSE),

            'server_config_path' =>
                Provision::newProperty()
                    ->description('server: The location to store the server\'s configuration files. If left empty, will be generated automatically.')
                    ->required(FALSE)
                    ->hidden()
            ,
        ];
    }

    /**
     * Check if a hostname provided is an ip address.
     *
     * @param string $hostname
     *   The hostname to check.
     *
     * @return bool
     *   TRUE is the $hostname is a valid IP address, FALSE otherwise.
     */
    static function valid_ip($hostname) {
      return is_string(inet_pton($hostname));
    }
}
