<?php

namespace Aegir\Provision\Context;

use Aegir\Provision\Application;
use Aegir\Provision\ContextSubscriber;
use Aegir\Provision\Provision;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class PlatformContext
 *
 * @package Aegir\Provision\Context
 *
 * @see \Provision_Context_platform
 */
class PlatformContext extends ContextSubscriber implements ConfigurationInterface
{
    /**
     * @var string
     */
    public $type = 'platform';
    const TYPE = 'platform';

    /**
     * @var \Aegir\Provision\Context\ServerContext;
     */
    public $web_server;

    /**
     * PlatformContext constructor.
     *
     * @param $name
     * @param Provision $provision
     * @param array $options
     */
    function __construct(
        $name,
        Provision $provision = NULL,
        $options = []
    ) {
        parent::__construct($name, $provision, $options);

        // Load "web_server" context.
        // There is no need to validate for $this->properties['web_server'] because the config system does that.
//        $this->web_server = $application->getContext($this->properties['web_server']);
    }
    
    static function option_documentation()
    {
        $options = [
            'root' =>
                Provision::newProperty()
                    ->description('platform: path to the Drupal installation. You may use a relative or absolute path.')
                    ->defaultValue(getcwd())
                    ->required(TRUE)
                    ->validate(function($path) {
                        if (strpos($path, '/') !== 0) {
                            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
                        }
                        return $path;
                    })
                ,
            'makefile' =>
                Provision::newProperty()
                    ->description('platform: Drush makefile to use for building the platform. May be a path or URL.')
                    ->required(FALSE)
                    ->validate(function($makefile) {
                        if (empty($makefile)) {
                            return $makefile;
                        }
                        $parsed = parse_url($makefile);

                        // If parsed is empty, it couldn't be read as a URL or filename.
                        if (empty($parsed)) {
                            throw new \RuntimeException("The makefile at {$makefile} could not be read.");
                        }
                        // If array is only path, it is a file path.
                        elseif (count(array_keys($parsed)) == 1 && isset($parsed['path'])) {
                            if (is_readable($parsed['path'])) {
                                return $makefile;
                            }
                            else {
                                throw new \RuntimeException("The makefile at {$makefile} could not be read.");
                            }
                        }
                        // Otherwise, makefile is a URL. Check if we can access it.
                        else {
                            try {
                                $content = @file_get_contents($makefile);
                                if ($content === false) {
                                    throw new \RuntimeException("The makefile at {$makefile} could not be read.");
                                } else {
                                    return $makefile;
                                }
                            }
                            catch (\Exception $e) {
                                throw new \RuntimeException("The makefile at {$makefile} could not be read.");
                            }
                        }
                        
                        return $makefile;
                    })
            
            ,
            'make_working_copy' =>
                Provision::newProperty()
                    ->description('platform: Specifiy TRUE to build the platform with the Drush make --working-copy option.')
                    ->required(FALSE)
            ,
            'git_url' =>
                Provision::newProperty()
                    ->description('platform: Git repository remote URL.')
                    ->required(FALSE)
                    ->validate(function($git_url) {
                        
                        Provision::getProvision()->io()->comment('Checking git remote...');
                        
                        // Use git ls-remote to detect a valid and accessible git URL.
                        $result = Provision::getProvision()->getTasks()->taskExec('git ls-remote')
                            ->arg($git_url)
                            ->silent(!Provision::getProvision()->getOutput()->isVerbose())
                            ->run();

                        if (!$result->wasSuccessful()) {
                            throw new \RuntimeException("Unable to connect to git remote $git_url. Please check access and try again.");
                        }
    
                        Provision::getProvision()->io()->successLite('Connected to git remote.');

                        // @TODO: Parse brances and tags.
                        
                        return $git_url;
                    })
            ,
        ];

        return $options;
    }

    /**
     * Platforms require a web (http) server.
     *
     * @return array
     */
    public static function serviceRequirements() {
        return ['http'];
    }
    
    /**
     * Output extra info before verifying.
     */
    public function verify()
    {
        $this->getProvision()->io()->customLite($this->getProperty('root'), 'Root: ', 'info');
        $this->getProvision()->io()->customLite($this->config_path, 'Configuration File: ', 'info');
        $this->getProvision()->io()->newLine();
    
        $tasks = [];
    
        // If platform files don't exist, but has git url or makefile, build now.
        if (!$this->fs->exists($this->getProperty('root')) && $this->getProperty('git_url')) {
    
            $tasks['platform.git'] = $this->getProvision()->newTask()
                ->success('Deployed platform from git repository.')
                ->failure('Unable to clone platform.')
                ->execute(function () {
                    $this->getProvision()->io()->warningLite('Root path does not exist. Cloning source code from git repository ' . $this->getProperty('git_url') . ' to ' . $this->getProperty('root'));
    
                    $this->getProvision()->getTasks()->taskExec("git clone")
                        ->arg($this->getProperty('git_url'))
                        ->arg($this->getProperty('root'))
                        ->silent(!$this->getProvision()->getOutput()->isVerbose())
                        ->run()
                    ;
            
                });
        }
        elseif (!$this->fs->exists($this->getProperty('root')) && $this->getProperty('makefile')) {
            $tasks['platform.make'] = $this->getProvision()->newTask()
                ->success('Deployed platform from makefile.')
                ->failure('Unable to deploy platform from makefile.')
                ->execute(function () {
                    $this->getProvision()->io()->warningLite('Root path does not exist. Creating platform from makefile ' . $this->getProperty('git_url') . ' in ' . $this->getProperty('root'));
        
                    $this->getProvision()->getTasks()->taskExec("drush make")
                        ->arg($this->getProperty('makefile'))
                        ->arg($this->getProperty('root'))
                        ->silent(!$this->getProvision()->getOutput()->isVerbose())
                        ->run()
                    ;
        
                });
                
                
        }
    
        return $tasks;
        
//        return parent::verify();
    }
}