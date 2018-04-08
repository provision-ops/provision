---
description: How to use start using Provision.
---

# Getting Started

## Getting Started

The prerequisites for Provision are:

* PHP-CLI.
* Composer.
* A web & database server or Docker and `docker-compose` \(Docker v17.05 or higher\).

Since we are still early in development, we recommend installing from source repository at [https://github.com/provision4/provision](https://github.com/provision4/provision).

```
$ git clone git@github.com:provision4/provision.git ~/Projects/provision4
$ cd ~/Projects/provision
$ composer install
$ sudo ln -s $PWD/bin/provision /usr/local/bin/provision
$ sudo ln -s $PWD/bin/provision /usr/local/bin/pro
```

{% hint style="info" %}
Provision works by writing Apache or NGINX configuration dynamically. It can work with any Apache/NGINX based server. 

Provision also includes Dockerfiles for these services. Either way, Provision writes the Apache/NGINX configuration from template files.
{% endhint %}

{% hint style="danger" %}
There is a bug currently where the first time run will throw an error about missing `config_path` folder, instead of the `setup` command.

Create the `contexts_path` at the default locations to continue:

```bash
$ mkdir /Users/me/.config/provision/contexts -p
```
{% endhint %}

Once those links are in place, you can run `provision` or `pro` to view the list of commands.

```
    ____                  _      _                __ __
   / __ \_________ _   __(_)____(_)___  ____     / // /
  / /_/ / ___/ __ \ | / / / ___/ / __ \/ __ \   / // /_
 / ____/ /  / /_/ / |/ / (__  ) / /_/ / / / /  /__  __/
/_/   /_/   \____/|___/_/____/_/\____/_/ /_/     /_/   
                                
Provision 4.x-dev

Usage:
  command [options] [arguments]

Options:
  -h, --help               Display this help message
  -q, --quiet              Do not output any message
  -V, --version            Display this application version
      --ansi               Force ANSI output
      --no-ansi            Disable ANSI output
  -n, --no-interaction     Do not ask any interactive question
  -c, --context[=CONTEXT]  The target context to act on.
  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help      Displays help for a command
  list      Lists commands
  save      [add] Create or update a site, platform, or server.
  services  Manage the services attached to servers.
  setup     Configure provision for your system.
  shell     commands.shell.description
  status    Display system status.
  verify    Verify a Provision Context.

```

### Provision Setup command.

Run the provision setup command to get a guided tour and run a setup wizard:

```bash
$ pro setup
                                                                               
  Welcome to the Provision Setup Wizard!                                       
                                                                               

Console Configuration
=====================

 In this section, we will make sure your Provision CLI configuration and       
 folders are created.                                                          

 ! Provision CLI configuration file was not found at /Users/jon/.provision.yml

 Helpful Tips:                                                                 

 ➤ The ~/.provision.yml file determines the config_path and contexts_path settings.
 ➤ The config_path is where Provision stores server configuration. Each server gets a folder inside this path to store their configuration files, such as Apache virtualhosts. The default config path is /Users/jon/.config/provision
 ➤ The contexts_path is where Provision stores the metadata about your servers and sites, called Contexts. Contexts are saved as YML files in this folder. The default contexts path is /Users/jon/.config/provision/contexts
 ➤ When Provision asks you a question, it may provide a [default value]. If you just hit enter, that default value will be used.

 Where would you like Provision to store its configuration? [/Users/jon/.config/provision]:
 > 

 Where would you like Provision to store its contexts? [/Users/jon/.config/provision/contexts]:
 > 

 Would you like to create the file /Users/jon/.provision.yml ? (yes/no) [yes]:
```



### Provision Save

The provision save command will add sites and servers to the system. It is fully interactive, so you can just type `provision save` to get started. 

You must start with a server, so Provision will help make sure that's the first thing you create. 

Traditionally, the first server we add to Provision is called server\_master, but you can call it whatever you'd like.

{% hint style="info" %}
When Provision asks you a question, it often offers you a \[Default Value\], which will be inside the brackets \[like this\]. If you see this and are happy with that value \(or aren't sure what it means\), just hit _Enter_ and keep moving!
{% endhint %}

So, to create your first server, run the `provision save` command:

```text
$ pro save

 Context name [server_master]:
 > 

 Using default option remote_host=localhost
 Using default option script_user=jon
 Using default option aegir_root=/Users/jon
 Using default option server_config_path=
 -------------------- -------------------------------------------- 
  Saving Context:      server_master                               
 -------------------- -------------------------------------------- 
  remote_host          localhost                                   
  script_user          jon                                         
  aegir_root           /Users/jon                                  
  server_config_path   /Users/jon/.config/provision/server_master  
  type                 server                                      
  name                 server_master                               
 -------------------- -------------------------------------------- 

 Save server context server_master to /Users/jon/.config/provision/contexts/server.server_master.yml? (yes/no) [yes]:
 > 

                                                                               
 [OK] Configuration saved to                                                   
      /Users/jon/.config/provision/contexts/server.server_master.yml           
                                                                               

 Add a service? (yes/no) [yes]:
 > 

```

### Servers & Services

A "server" represents the system providing services to a site, such as "web" and "database".

{% hint style="warning" %}
**NOTE on User Experience: **Currently, you must add services individually to your server context. In the near future, the `setup` command will assess your system and offer pre-configured servers.

Thank you for your patience as we work to make things as streamlined as possible!
{% endhint %}

After the `provision save` command, Pro4 asks if you would like to add services, remember, just hit _Enter_ to answer Yes:

{% hint style="danger" %}
There is currently a bug that requires that the `http` server be added first. Please select "http" as your first service.
{% endhint %}

```text
Add a service? (yes/no) [yes]:
 > 

 Add Services

 Which service?:
  [db  ] Database Server
  [http] Web Server
 > http

 Which service type?:
  [nginx       ] NGINX
  [apacheDocker] Apache on Docker
  [php         ] PHP Server
  [apache      ] Apache
 > apacheDocker

 http_port (The port which the web service is running on.) [80]:
 > 9999

 web_group (Web server group.) [www]:
 > www-data

 Adding http service apacheDocker...

                                                                               
 [OK] Service saved to Context!                                                
                                   
```

