<?php
/**
 * @file
 * The base Provision DbService class.
 *
 * @see \Provision_Service_db
 */

namespace Aegir\Provision\Service;

//require_once DRUSH_BASE_PATH . '/commands/core/rsync.core.inc';

use Aegir\Provision\Context;
use Aegir\Provision\Context\SiteContext;
use Aegir\Provision\Provision;
use Aegir\Provision\Service;
use Aegir\Provision\ServiceInterface;
use Aegir\Provision\ServiceSubscription;
use Aegir\Provision\Step;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DbService
 *
 * @package Aegir\Provision\Service
 */
class DbService extends Service implements ServiceInterface
{

    const SERVICE = 'db';
    const SERVICE_TYPE = NULL;
    const SERVICE_DEFAULT_PORT = 3306;

    const SERVICE_NAME = 'Database Server';

    /**
     * @var \PDO
     */
    public $conn;
    
    /**
     * @var string
     */
    protected $dsn = '';
    
    /**
     * The list credentials to login to the database server. Loaded from master_db URL or a service subscription properties.
     *
     * @var array
     */
    protected $creds;

    /**
     * Indicates the place holders that should be replaced in _db_query_callback().
     */
    const PROVISION_QUERY_REGEXP = '/(%d|%s|%%|%f|%b)/';

    /**
     * DbService constructor.
     *
     * Set dsn based on context's service config.
     *
     * @param $service_config
     * @param Context $provider_context
     */
    function __construct($service_config, Context $provider_context)
    {
        parent::__construct($service_config, $provider_context);

        $this->creds = array_map('urldecode', parse_url($this->properties['master_db']));

        // If credentials cannot be decoded, throw an exception.
        if (empty($this->creds) || empty($this->creds['host']) || empty($this->creds['scheme'])) {
            if (empty($this->properties['master_db'])) {
                $this->properties['master_db'] = 'empty';
            }
            throw new InvalidConfigurationException("Unable to parse master_db connection for server '{$provider_context->name}'. Check the file {$provider_context->config_path} and try again. \n\nCurrent value: {$this->properties['master_db']}");
        }

        if (!isset($this->creds['port'])) {
            $this->creds['port'] = '3306';
        }

        if (!isset($this->creds['pass'])) {
            $this->creds['pass'] = '';
        }

        $this->dsn = sprintf("%s:host=%s;port=%s", $this->creds['scheme'],  $this->creds['host'], $this->creds['port']);

    }

    /**
     * Return the driver string to use for a site's DSN.
     *
     * @return string
     */
    private function getDriverName() {
        return 'mysql';
    }

    /**
     * Implements Service::server_options()
     *
     * @return array
     */
    static function server_options()
    {
        return [
            'master_db' => 'server with db: Master database connection info, {type}://{user}:{password}@{host}',
            'db_grant_all_hosts' => 'Grant access to site database users from any web host. If set to TRUE, any host will be allowed to connect to MySQL site databases on this server using the generated username and password. If set to FALSE, web hosts will be granted access by their detected IP address.',
            'db_port' => 'The port to run the database server on.',
        ];
    }
    
    /**
     * List context types that are allowed to subscribe to this service.
     * @return array
     */
    static function allowedContexts() {
        return [
            'site'
        ];
    }
    
    /**
     * Implements Service::server_options()
     *
     * @return array
     */
    static function site_options()
    {
        return [
            'db_name' => Provision::newProperty('The name of the database. Default: Automatically generated.')
                ->defaultValue(function () {
                   return uniqid('db_name_');
                }),
            'db_user' => Provision::newProperty('The username used to access the database. Default: Automatically generated.')
                ->defaultValue(function () {
                    return uniqid('db_user_');
                }),
            'db_password' => Provision::newProperty('The password used to access the database. Default: Automatically generated.')
                ->defaultValue(function () {
                    return uniqid('db_password_');
                }),
        ];
    }
    
    /**
     * React to the `provision verify` command on Server contexts
     */
    function verifyServer() {
        $tasks = [];
        
        // Confirm we can connect to the database server as root.
        $tasks['db_connect'] = Provision::newStep()
            ->execute(function () {
                $this->connect();
            })
            ->start('Checking connection to database server...')
            ->failure('Checking connection to database server... Unable to connect using credentials saved in context ' . $this->provider->name . '.')
        ;
    
        // Confirm we have access to create databases.
        $tasks['db_create'] = Provision::newStep()
            ->execute(function () {
                return $this->can_create_database()? 0: 1;
            })
            ->start('Checking root database access...')
        ;
    
        // Confirm we can create database users.
        $tasks['db_grant'] = Provision::newStep()
            ->start('Checking access to grant privileges...')
            ->execute(function () {
                return $this->can_grant_privileges()? 0: 1;
            })
        ;
        
        return $tasks;
        //
//        try {
//            $this->connect();
//            $return = TRUE;
//            $this->provider->getProvision()->io()->successLite('Successfully connected to database server!');
//
//            if ($this->can_create_database()) {
//                $this->provider->getProvision()->io()->successLite('Provision can create new databases.');
//            } else {
//                $this->provider->getProvision()->io()->errorLite('Provision is unable to create databases.');
//                $return = FALSE;
//            }
//            if ($this->can_grant_privileges()) {
//                $this->provider->getProvision()->io()->successLite('Provision can grant privileges on database users.');
//            } else {
//                $this->provider->getProvision()->io()->errorLite('Provision is unable to grant privileges on database users.');
//                $return = FALSE;
//            }
//
//            return [
//                'service' => $return
//            ];
//        }
//        catch (\PDOException $e) {
//            $this->provider->getProvision()->io()->errorLite($e->getMessage());
//            return [
//                'service' => FALSE
//            ];
//        }
    }
    
    /**
     * React to the `provision verify` command on subscriber contexts (sites and platforms)
     */
    function verifySite() {

        return [
            'Prepared site database.' => function () {

        $this->subscription = $this->getContext()->getSubscription($this::SERVICE);

        // Check for database
        $this->create_site_database($this->getContext());

        $this->creds_root = array_map('urldecode', parse_url($this->properties['master_db']));
    
        // Use the credentials from the subscription properties.
        $this->creds = $this->creds_root;
        $this->creds['user'] = $this->subscription->properties['db_user'];
        $this->creds['pass'] = $this->subscription->properties['db_password'];
    
        if (!isset($this->creds['port'])) {
            $this->creds['port'] = '3306';
        }
    
        $this->dsn = sprintf("%s:host=%s;port=%s;dbname=%s", $this->getDriverName(),  $this->creds['host'], $this->creds['port'], $this->subscription->properties['db_name']);
    
        try {
            $this->connect();
            $this->subscription->getContext()->getProvision()->io()->successLite('Successfully connected to database server.');
        }
        catch (\PDOException $e) {
            throw new \Exception('Unable to connect to database using service properties: ' . $e->getMessage());
        }

            }
        ];
    }

    public function verifyPlatform() {

    }


    /**
     * Attempt to connect to the database server using $this->creds
     * @return \PDO
     * @throws \Exception
     */
    function connect() {
        $user = isset($this->creds['user']) ? $this->creds['user'] : '';
        $pass = isset($this->creds['pass']) ? $this->creds['pass'] : '';
        try {
            $this->conn = new \PDO($this->dsn, $user, $pass);
            return $this->conn;
        }
        catch (\PDOException $e) {
            throw new \PDOException("Unable to connect to database server using DSN {$this->dsn}: " . $e->getMessage());
        }
    }
    
    function ensure_connected() {
        if (is_null($this->conn)) {
            $this->connect();
        }
    }
    
    function query($query) {
        $args = func_get_args();
        array_shift($args);
        if (isset($args[0]) and is_array($args[0])) { // 'All arguments in one array' syntax
            $args = $args[0];
        }
        $this->ensure_connected();
        $this->query_callback($args, TRUE);
        $query = preg_replace_callback($this::PROVISION_QUERY_REGEXP, array($this, 'query_callback'), $query);
        
        try {
            $this->provider->getProvision()->getLogger()->info("Running Query: {$query}");
            $result = $this->conn->query($query);
        }
        catch (\PDOException $e) {
            $this->provider->getProvision()->getLogger()->error($e->getMessage());
            return FALSE;
        }
        
        return $result;
        
    }
    
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
                return substr($this->conn->quote(array_shift($args)), 1, -1);
            case '%%':
                return '%';
            case '%f':
                return (float) array_shift($args);
            case '%b': // binary data
                return $this->conn->quote(array_shift($args));
        }
        
    }

    /**
     * Return the credentials array.
     *
     * @return array
     */
    public function getCreds() {
        return $this->creds;
    }
    
    //
//    /**
//     * Register the db handler for sites, based on the db_server option.
//     */
//    static function subscribe_site($context)
//    {
//        $context->setProperty('db_server', '@server_master');
//        $context->is_oid('db_server');
//        $context->service_subscribe('db', $context->db_server->name);
//    }
//
//    function init_server()
//    {
//        parent::init_server();
//        $this->server->setProperty('master_db');
//        $this->server->setProperty('db_grant_all_hosts', false);
//        $this->server->setProperty('utf8mb4_is_supported', false);
//        $this->creds = array_map(
//            'urldecode',
//            parse_url($this->server->master_db)
//        );
//
//        return true;
//    }
//
//    function save_server()
//    {
//        // Check database 4 byte UTF-8 support and save it for later.
//        $this->server->utf8mb4_is_supported = $this->utf8mb4_is_supported();
//    }
//
//    /**
//     * Verifies database connection and commands
//     */
//    function verify_server_cmd()
//    {
//        if ($this->connect()) {
//            if ($this->can_create_database()) {
//                drush_log(dt('Provision can create new databases.'), 'success');
//            } else {
//                drush_set_error('PROVISION_CREATE_DB_FAILED');
//            }
//            if ($this->can_grant_privileges()) {
//                drush_log(
//                    dt('Provision can grant privileges on database users.'),
//                    'success'
//                );;
//            } else {
//                drush_set_error('PROVISION_GRANT_DB_USER_FAILED');
//            }
//            if ($this->server->utf8mb4_is_supported) {
//                drush_log(
//                    dt(
//                        'Provision can activate multi-byte UTF-8 support on Drupal 7 sites.'
//                    ),
//                    'success'
//                );
//            } else {
//                drush_log(
//                    dt(
//                        'Multi-byte UTF-8 for Drupal 7 is not supported on your system. See the <a href="@url">documentation on adding 4 byte UTF-8 support</a> for more information.',
//                        ['@url' => 'https://www.drupal.org/node/2754539']
//                    ),
//                    'warning'
//                );
//            }
//        } else {
//            drush_set_error('PROVISION_CONNECT_DB_FAILED');
//        }
//    }
//
//    /**
//     * Find a viable database name, based on the site's uri.
//     */
//    function suggest_db_name()
//    {
//        $uri = $this->context->uri;
//
//        $suggest_base = substr(
//            str_replace(['.', '-'], '', preg_replace('/^www\./', '', $uri)),
//            0,
//            16
//        );
//
//        if (!$this->database_exists($suggest_base)) {
//            return $suggest_base;
//        }
//
//        for ($i = 0; $i < 100; $i++) {
//            $option = sprintf(
//                "%s_%d",
//                substr($suggest_base, 0, 15 - strlen((string)$i)),
//                $i
//            );
//            if (!$this->database_exists($option)) {
//                return $option;
//            }
//        }
//
//        drush_set_error(
//            'PROVISION_CREATE_DB_FAILED',
//            dt("Could not find a free database names after 100 attempts")
//        );
//
//        return false;
//    }

    /**
     * Generate a new mysql database and user account for the specified
     * credentials
     */
    function create_site_database(SiteContext $site)
    {

        $db_name = $site->getSubscription('db')->getProperty('db_name');
        $db_user = $site->getSubscription('db')->getProperty('db_user');
        $db_passwd = $site->getSubscription('db')->getProperty('db_password');

        if (!$this->can_create_database()) {
            throw new \Exception("Unable to create a database for the site {$site->name}");
        }

        if ($this->database_exists($db_name)) {
            $this->getProvision()->io()->successLite(strtr("Database '@name' already exists.", [
                '@name' => $db_name
            ]));
        }
        else {
            $this->create_database($db_name);
            $this->getProvision()->io()->successLite(strtr("Created database '@name'.", [
                '@name' => $db_name,
            ]));
        }

        foreach ($this->grant_host_list() as $db_grant_host) {
            if (!$this->grant($db_name, $db_user, $db_passwd, $db_grant_host)) {
                throw new \Exception(strtr("Could not create database user @user", [
                    '@user' => $db_user
                ]));
            }
            $this->getProvision()->io()->successLite(strtr("Granted privileges to user '@user@@host' for database '@name'.", [
                '@user' => $db_user,
                '@host' => $db_grant_host,
                '@name' => $db_name,
            ]));
        }

        $status = $this->database_exists($db_name);

        if ($status) {
            $this->getProvision()->io()->successLite(strtr("Database service configured for site @name.", [
                '@name' => $site->name,
            ]));
        }
        else {
            throw new \Exception(strtr("Could not create @name database", [
                "@name" => $db_name
            ]));
        }

        return $status;
    }
//
//    /**
//     * Remove the database and user account for the supplied credentials
//     */
//    function destroy_site_database($creds = [])
//    {
//        if (!sizeof($creds)) {
//            $creds = $this->fetch_site_credentials();
//        }
//        extract($creds);
//
//        if ($this->database_exists($db_name)) {
//            drush_log(dt("Dropping database @dbname", ['@dbname' => $db_name]));
//            if (!$this->drop_database($db_name)) {
//                drush_log(
//                    dt(
//                        "Failed to drop database @dbname",
//                        ['@dbname' => $db_name]
//                    ),
//                    'warning'
//                );
//            }
//        }
//
//        if ($this->database_exists($db_name)) {
//            drush_set_error('PROVISION_DROP_DB_FAILED');
//
//            return false;
//        }
//
//        foreach ($this->grant_host_list() as $db_grant_host) {
//            drush_log(
//                dt(
//                    "Revoking privileges of %user@%client from %database",
//                    [
//                        '%user' => $db_user,
//                        '%client' => $db_grant_host,
//                        '%database' => $db_name,
//                    ]
//                )
//            );
//            if (!$this->revoke($db_name, $db_user, $db_grant_host)) {
//                drush_log(dt("Failed to revoke user privileges"), 'warning');
//            }
//        }
//    }
//
//
//    function import_site_database($dump_file = null, $creds = [])
//    {
//        if (is_null($dump_file)) {
//            $dump_file = d()->site_path.'/database.sql';
//        }
//
//        if (!sizeof($creds)) {
//            $creds = $this->fetch_site_credentials();
//        }
//
//        $exists = provision_file()->exists($dump_file)
//            ->succeed('Found database dump at @path.')
//            ->fail(
//                'No database dump was found at @path.',
//                'PROVISION_DB_DUMP_NOT_FOUND'
//            )
//            ->status();
//        if ($exists) {
//            $readable = provision_file()->readable($dump_file)
//                ->succeed('Database dump at @path is readable')
//                ->fail(
//                    'The database dump at @path could not be read.',
//                    'PROVISION_DB_DUMP_NOT_READABLE'
//                )
//                ->status();
//            if ($readable) {
//                $this->import_dump($dump_file, $creds);
//            }
//        }
//    }
//
//    function generate_site_credentials()
//    {
//        $creds = [];
//        // replace with service type
//        $db_type = drush_get_option(
//            'db_type',
//            function_exists('mysqli_connect') ? 'mysqli' : 'mysql'
//        );
//        // As of Drupal 7 there is no more mysqli type
//        if (drush_drupal_major_version() >= 7) {
//            $db_type = ($db_type == 'mysqli') ? 'mysql' : $db_type;
//        }
//
//        //TODO - this should not be here at all
//        $creds['db_type'] = drush_set_option('db_type', $db_type, 'site');
//        $creds['db_host'] = drush_set_option(
//            'db_host',
//            $this->server->remote_host,
//            'site'
//        );
//        $creds['db_port'] = drush_set_option(
//            'db_port',
//            $this->server->db_port,
//            'site'
//        );
//        $creds['db_passwd'] = drush_set_option(
//            'db_passwd',
//            provision_password(),
//            'site'
//        );
//        $creds['db_name'] = drush_set_option(
//            'db_name',
//            $this->suggest_db_name(),
//            'site'
//        );
//        $creds['db_user'] = drush_set_option(
//            'db_user',
//            $creds['db_name'],
//            'site'
//        );
//
//        return $creds;
//    }
//
//    function fetch_site_credentials()
//    {
//        $creds = [];
//
//        $keys = [
//            'db_type',
//            'db_port',
//            'db_user',
//            'db_name',
//            'db_host',
//            'db_passwd',
//        ];
//        foreach ($keys as $key) {
//            $creds[$key] = drush_get_option($key, '', 'site');
//        }
//
//        return $creds;
//    }
//
//    function database_exists($name)
//    {
//        return false;
//    }
//
//    function drop_database($name)
//    {
//        return false;
//    }
//
//    function create_database($name)
//    {
//        return false;
//    }
//
    function can_create_database()
    {
        return false;
    }

    function can_grant_privileges()
    {
        return false;
    }
//
//    function grant($name, $username, $password, $host = '')
//    {
//        return false;
//    }
//
//    function revoke($name, $username, $host = '')
//    {
//        return false;
//    }
//
//    function import_dump($dump_file, $creds)
//    {
//        return false;
//    }
//
//    function generate_dump()
//    {
//        return false;
//    }

    /**
     * Return a list of hosts, as seen by the db server, which should be granted
     * access to the site database. If server property 'db_grant_all_hosts' is
     * TRUE, use the MySQL wildcard '%' instead of
     */
    function grant_host_list()
    {

        // @TODO: Implement grant_server_list by injecting $service->subscription. Right now we don't have access to the site context inside a service class.
        return ['%'];

//        if ($this->getProperty('db_grant_all_hosts')) {
//            return ['%'];
//        } else {
//
//            return array_unique(
//                array_map(
//                    [$this, 'grant_host'],
//                    $this->platform->service('http')->grant_server_list()
//                )
//            );
//        }
    }
//
//    /**
//     * Return a hostname suitable for database grants from a server object.
//     */
//    function grant_host(Provision_Context_server $server)
//    {
//        return $server->remote_host;
//    }
//
//    /**
//     * Checks whether utf8mb4 support is available on the current database
//     * system.
//     *
//     * @return bool
//     */
//    function utf8mb4_is_supported()
//    {
//        // By default we assume that the database backend may not support 4 byte
//        // UTF-8.
//        return false;
//    }
}
