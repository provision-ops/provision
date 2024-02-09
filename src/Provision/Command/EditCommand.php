<?php

namespace Aegir\Provision\Command;

use Aegir\Provision\Application;
use Aegir\Provision\Command;
use Aegir\Provision\Console\ProvisionStyle;
use Aegir\Provision\Context;
use Aegir\Provision\Context\PlatformContext;
use Aegir\Provision\Context\ServerContext;
use Aegir\Provision\Context\SiteContext;
use Aegir\Provision\Property;
use Aegir\Provision\Provision;
use Aegir\Provision\Service;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EditCommand
 *
 * @package Aegir\Provision\Command
 */
class EditCommand extends Command
{
    const CONTEXT_REQUIRED = TRUE;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('context:edit')
            ->setAliases(['edit'])
            ->setDescription('Edit a context file')
            ->setHelp(
                'Use this command to interactively setup a new site, platform or server (known as "contexts"). Metadata is saved to .yml files in the "config_path" folder. Once you have create a context, use the `provision status` command to view the list of added contexts.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->context->process_exec('${VISUAL-${EDITOR-vi}} ' . $this->context->config_path);

    }
}
