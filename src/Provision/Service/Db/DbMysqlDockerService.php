<?php

namespace Aegir\Provision\Service\Db;

use Aegir\Provision\Common\DockerContainerTrait;
use Aegir\Provision\Context;
use Aegir\Provision\Provision;
use Aegir\Provision\Service\DockerServiceInterface;
use Robo\ResultData;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Class HttpMySqlDockerService
 *
 * @package Aegir\Provision\Service\Db
 */
class DbMysqlDockerService extends DbMysqlService implements DockerServiceInterface
{
    const SERVICE_TYPE = 'mysqlDocker';
    const SERVICE_TYPE_NAME = 'MySQL on Docker';

    public function __construct($service_config, \Aegir\Provision\Context $provider_context) {
        parent::__construct($service_config, $provider_context);

        // Force remote host for db servers to match the service type.
        // @TODO: Should this be done in the docker interface?
        $this->provider->setProperty('remote_host', 'db');
    }

    /**
     * Return the docker image name to use for this service.
     *
     * @return string
     */
    public function dockerImage()
    {
        return 'mariadb';
    }

    /**
     * Normally this returns self::SERVICE_TYPE. We have to override because this
     * is what is used in settings.php files.
     * @return string
     */
    public function getType() {
        return 'mysql';
    }

    public function dockerComposeService(){
        $compose = array(
            'image'  => $this->dockerImage(),
            'restart'  => 'always',
            'environment' => $this->dockerEnvironment(),

            // Recommended way to enable UTF-8 for Drupal.
            // See https://www.drupal.org/node/2754539
            // @TODO: The latest mariadb does NOT support this! We need to determine specific docker image tags to fix this.
            // 'command' => 'mysqld --innodb-large-prefix --innodb-file-format=barracuda --innodb-file-per-table',
        );

        // if the user entered no port, don't add ports array. If we do, a random public port is assigned.
        // We don't typically want this for db servers.
        try {
          $compose['ports'][] = $this->getProperty('db_port') . ':3306';
        }
        catch (\Exception $e) {}

        // Look up a mysql conf override file.
        if (file_exists($this->provider->server_config_path . '/mysql.cnf')) {
          $compose['volumes'][] = $this->provider->server_config_path . '/mysql.cnf' . ':/etc/mysql/conf.d/provision.cnf';
        }

        return $compose;
    }

    function dockerEnvironment() {
        return array(
            // MariaDB image does not have a MYSQL_ROOT_USER environment variable.
            // 'MYSQL_ROOT_USER' => 'root',
            'MYSQL_ROOT_PASSWORD' => $this->creds['pass'],
        );
    }

    /**
     * Attempt to connect to the database server using $this->creds
     * @return \PDO
     * @throws \Exception
     */
    function connect()
    {
        // If already connected, just return TRUE;
        static $connected;
        if ($connected) {
            return TRUE;
        }
        $start = time();

        $command = $this->getProvision()->getTasks()->taskExec('docker-compose exec db')
            ->arg('mysqladmin')
            ->arg('ping')
            ->option('host', 'db', '=')
            ->option('user', $this->creds['user'], '=')
            ->option('password', $this->creds['pass'], '=')
            ->getCommand();

        // If it runs early enough, the docker exec doesn't work at all, throwing an exception.
        // Loop until we're *not* throwing exceptions because the command failed.
        $timeout = $this->getProvision()->getConfig()->get('database_wait_timeout', 30);
        while (true) {
            try {
                $output = $this->provider->shell_exec($command, NULL, 'stdout');
                if (strpos($output, "Access denied for user") !== FALSE) {
                    throw new \Exception($output);
                }
                break;
            } catch (\Exception $e) {
                // If docker client can't connect, bail and pass the error through to the exeception.
                if (strpos($e->getMessage(), "Couldn't connect to Docker daemon") !== FALSE) {
                    throw new \Exception($e->getMessage());
                }

                // If we hit our timeout while waiting for container, bail out.
                if (time() - $start > $timeout) {
                    throw new \Exception('Timed out waiting for database Docker container.');
                }

                // If root user cannot connect for some reason, bail out.
                if (strpos($e->getMessage(), "Access denied for user") !== FALSE) {
                    throw new \Exception($e->getMessage());
                }

                // Otherwise, print a waiting dot and try again.
                print '.';
            }
        }

        // Loop until output of command is 'mysqld is alive'
        while ('mysqld is alive' != trim($this->provider->shell_exec($command))) {
            sleep(1);
            $this->getProvision()->getLogger()->info('Waiting for MySQL to become available...');
        }

        // Set static variable to indicate we are connected.
        $connected = TRUE;
    }

    /**
     * Override parent::query() to use `mysql` command directly in the container instead of PDO.
     * @param $query
     */
    function query($query) {
        $args = func_get_args();
        array_shift($args);
        if (isset($args[0]) and is_array($args[0])) { // 'All arguments in one array' syntax
            $args = $args[0];
        }
        $this->ensure_connected();
        $this->query_callback($args, TRUE);
        $query = preg_replace_callback($this::PROVISION_QUERY_REGEXP, array($this, 'query_callback'), $query);

        $command = $this->getProvision()->getTasks()->taskExec('docker-compose exec db')
            ->arg('mysql')
            ->arg('-B')
            ->option('host', 'db', '=')
            ->option('user', $this->creds['user'], '=')
            ->option('password', $this->creds['pass'], '=')
            ->option('execute', $query, '=')
            ->getCommand();
        ;

        $output = $this->provider->shell_exec($command);

        return new PDODummy($output);
    }

    /**
     * Override for parent::database_exists because we can't use PDO.
     *
     * @param $name
     * @return bool
     */
    function database_exists($name) {

        $command = $this->getProvision()->getTasks()->taskExec('docker-compose exec db')
            ->arg('mysql')
            ->option('host', 'db', '=')
            ->option('user', $this->creds['user'], '=')
            ->option('password', $this->creds['pass'], '=')
            ->option('database', $name, '=')
            ->option('execute', 'SHOW TABLES')
            ->getCommand();

        try {
            return $this->provider->shell_exec($command, NULL, 'exit') == ResultData::EXITCODE_OK;
        }
        catch (\Exception $e) {
            return FALSE;
        }

    }

    /**
     * Overrides parent::query_callback so we can avoid using PDO::quote();
     *
     * This is legacy Aegir code... I had to change the numbers for the substr also, not sure why.
     *
     * @deprecated
     */
    function query_callback($match, $init = FALSE) {
        static $args = NULL;
        if ($init) {
            $args = $match;
            return;
        }

        switch ($match[1]) {
            case '%d': // We must use type casting to int to convert FALSE/NULL/(TRUE?)
                return (int) array_shift($args); // We don't need db_escape_string as numbers are db-safe
            case '%s':
                # TODO: Remove the need for escaping at all. We have very limited number of queries, we don't need this.
//                return substr(mysql_real_escape_string(array_shift($args)), 0);
                return substr(array_shift($args), 0);
            case '%%':
                return '%';
            case '%f':
                return (float) array_shift($args);
            case '%b': // binary data
                return array_shift($args);
//                return mysql_real_escape_string(array_shift($args));
        }
    }

    /**
     * Override the existing server because we can't check connectivity until
     * ServerContextDockerCompose::postVerify() runs.
     */
    public function verifyServer() {
        return [];
    }

    /**
     * @return array
     */
    public function postVerifyServer() {
        return parent::verifyServer();
    }

    /**
     * Override the existing server because we can't check connectivity until
     * ServerContextDockerCompose::postVerify() runs.
     */
    public function verifySite() {
        return [];
    }

    /**
     * @return array
     */
    public function postVerifySite() {
        return parent::verifySite();
    }

    //
//    use DockerContainerTrait;
//
//    /**
//     * DbMysqlDockerService constructor.
//     * @param $service_config
//     * @param Context $provider_context
//     */
//    function __construct($service_config, Context $provider_context)
//    {
//        parent::__construct($service_config, $provider_context);
//
//        $this->containerName = "provision_db_{$this->provider->name}";
//        $this->containerTag = "mariadb";
//
//    }
//
//    public function verifyServer()
//    {
//        $tasks = [];
//
//        // Docker run
//        $provision = $this->getProvision();
//        $tasks['db.docker.run'] = $this->getProvision()->newTask()
//            ->start('Running MariaDB docker image...')
//            ->execute(function () use ($provision) {
//
//                // Check for existing container.
//                $provision = $this->getProvision();
//                $containerExists = $provision->getTasks()
//                    ->taskExec("docker ps -a -q -f name={$this->containerName}")
//                    ->silent(!$this->getProvision()->getOutput()->isVerbose())
//                    ->printOutput(false)
//
//                    ->storeState('container_id')
//                    ->taskFileSystemStack()
//                    ->defer(
//                        function ($task, $state) use ($provision) {
//                            $container = $state['container_id'];
//                            if ($container) {
//
//                                //Check that it is running
//                                $provision->getTasks()
//                                    ->taskExec("docker inspect -f '{{.State.Running}}' {$this->containerName}")
//                                    ->silent(!$provision->getOutput()->isVerbose())
//                                    ->printOutput(false)
//                                    ->storeState('running')
//                                    ->taskFileSystemStack()
//                                    ->defer(
//                                        function ($task, $state) use ($provision) {
//
//                                            if ($state['running'] == 'true') {
////                                          $provision->io()->successLite('Container is already running: ' . $this->containerName);
//                                                // @TODO: Figure out how to change the "success" message of a task.
////                                          $this->success('Container is already running: ' . $this->containerName);
//                                            }
//                                            // If not running, try to start it.
//                                            else {
//                                                $startResult = $provision->getTasks()
//                                                    ->taskExec("docker start {$this->containerName}")
//                                                    ->silent(!$provision->getOutput()->isVerbose())
//                                                    ->run()
//                                                ;
//
//                                                if ($startResult->wasSuccessful()) {
////                                              $provision->io()->successLite('Existing container found. Restarted container ' . $this->containerName);
//                                                }
//                                                else {
////                                              $provision->io()->errorLite('Unable to restart docker container: ' . $this->containerName);
//                                                    throw new \Exception('Unable to restart docker container: ' . $this->containerName);
//                                                }
//                                            }
//                                        })
//                                    ->run();
//
//                            }
//
//                            # Docker container not found. Start it.
//                            else {
//                                $container = $provision->getTasks()->taskDockerRun($this->containerTag)
//                                    ->detached()
//                                    ->publish($this->getProperty($this::SERVICE . '_port'), $this::SERVICE_DEFAULT_PORT)
//                                    ->name($this->containerName)
//                                    ->silent(!$provision->getOutput()->isVerbose())
////                            ->option('rm')
//                                        ->env('MYSQL_ROOT_PASSWORD', $this->creds['pass'])
//                                    ->interactive();
//                                // Lookup all subscribers (all platforms that use this web service) and map volumes for root.
//                                foreach ($this->getAllSubscribers() as $platform) {
//                                    if (!empty($platform->getProperty('root'))) {
//                                        $container->volume($platform->getProperty('root'), $this->mapContainerPath($platform->getProperty('root')));
//                                    }
//                                }
//
//                                $result = $container->run();
//
//                                if (!$result->wasSuccessful()) {
//                                    throw new \Exception('Unable to run docker container: ' . $this->containerName);
//                                }
//                            }
//                        }
//                    )
//                    ->run();
//                if (!$containerExists->wasSuccessful()) {
//                    throw new RuntimeException('Unable to check for the container: ' . $containerExists->getMessage(), 1);
//                }
//            });
//        $tasks = array_merge($tasks, parent::verifyServer());
//        return $tasks;
//    }


}