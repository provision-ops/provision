<?php

namespace Aegir\Provision\Context;

use Aegir\Provision\Console\Config;
use Aegir\Provision\ServiceProvider;
use Aegir\Provision\Property;
use Aegir\Provision\Provision;
use Aegir\Provision\Service\DockerServiceInterface;
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
     * If server has any services that implement DockerServiceInterface,
     * $this->dockerCompose will be loaded.
     *
     * @var \Aegir\Provision\Context\ServerContextDockerCompose|null
     */
    public $dockerCompose = NULL;

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

        // If any assigned services implement DockerServiceInterface, load our
        // ServerContextDockerCompose class.
        foreach ($this->services as $service) {
            if ($service instanceof DockerServiceInterface) {
                $this->dockerCompose = new ServerContextDockerCompose($this);
                break;
            }
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
     * @return array
     */
    public function preVerify()
    {
        // Create the server/service directory. We put this here because we need to make sure this is always run before any other steps, no matter what.
        Provision::fs()->mkdir($this->server_config_path);

        $steps = [];

        // If dockerCompose engine is available, add those steps.
        if ($this->dockerCompose) {
            $steps += $this->dockerCompose->preVerify();
        }

        foreach ($this->servicesInvoke('preVerifyServer') as $serviceSteps) {
            if (is_array($serviceSteps)) {
                $steps += $serviceSteps;
            }
        }

        return $steps;
    }

    /**
     * @return array
     */
    public function postVerify()
    {
        $steps = [];

        // If dockerCompose engine is available, add those steps.
        if ($this->dockerCompose) {
            $steps = $this->dockerCompose->postVerify();
        }

        foreach ($this->servicesInvoke('postVerifyServer') as $serviceSteps) {
            if (is_array($serviceSteps)) {
                $steps += $serviceSteps;
            }
        }
        return $steps;
    }
}
