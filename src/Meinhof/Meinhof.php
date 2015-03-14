<?php

namespace Meinhof;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface as BaseExtensionInterface;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Config\FileLocator;

use Meinhof\DependencyInjection\ExtensionInterface;
use Meinhof\DependencyInjection\Compiler\TemplatingEnginePass;
use Meinhof\DependencyInjection\Compiler\EventListenerPass;

/**
 * Main class
 *
 * This class loads the dependency container and then can be used to trigger the different events
 * that will do stuff.
 *
 * @author Miguel Ibero <miguel@ibero.me>
 */
class Meinhof
{
    /**
     * the meinhof version
     */
    const VERSION = "0.1";

    /**
     * @var ContainerInterface the dependency injection container
     */
    protected $container;

    protected $dir;
    protected $input;
    protected $output;

    /**
     * @param string          $dir    the path to the base of the site configuration
     * @param InputInterface  $Input  the command line input
     * @param OutputInterface $output the command line output
     */
    public function __construct($dir, InputInterface $input = null, OutputInterface $output = null)
    {
        $this->dir = $dir;
        $this->input = $input;
        $this->output = $output;
        $this->reload();

        $this->dispatchEvent('load');
    }

    /**
     * Reload the dependency container
     */
    public function reload()
    {
        $this->container = $this->buildContainer($this->dir);

        // load input and output
        $this->container->set('input', $this->input);
        $this->container->set('output', $this->output);

        // freeze the container
        $this->container->compile();
    }

    /**
     * Returns a service.
     *
     * @param string $id id of the service
     *
     * @return mixed the service object
     *
     * @throws \RuntimeException when the container is not present
     */
    public function get($id)
    {
        if (!$this->container) {
            throw new \RuntimeException("The container has not been set up.");
        }

        return $this->container->get($id);
    }

    public function getParameter($id)
    {
        return $this->container->getParameter($id);
    }

    /**
     * Builds the dependency injection container
     *
     * @param  string             $dir the path to the base of the site configuration
     * @return ContainerInterface the new container
     *
     * @throws \RuntimeException when a service tagged as an extension does not implement ExtensionInterface
     */
    public function buildContainer($dir)
    {
        // load the container
        $container = new ContainerBuilder();

        // setup basic container compiler passes
        $container->addCompilerPass(new EventListenerPass());
        $container->addCompilerPass(new TemplatingEnginePass());

        $configDir = $dir.'/config';
        // set the key as a parameter
        $container->setParameter('base_dir', $dir);
        $container->setParameter('config_dir', $configDir);

        // load base services
        $configdirs = array(__DIR__.'/Resources/config', $dir, $configDir);
        $loader = new XmlFileLoader($container, new FileLocator($configdirs));
        $loader->load('services.xml');

        // register extensions
        foreach ($container->findTaggedServiceIds('extension') as $id => $attributes) {
            $extension = $container->get($id);
            if (!$extension instanceof BaseExtensionInterface) {
                throw new \RuntimeException("Invalid extension with id '${id}'.");
            }
            $container->registerExtension($extension);
        }

        // preload the extensions
        foreach ($container->getExtensions() as $extension) {
            if ($extension instanceof ExtensionInterface) {
                $extension->preload($container);
            }
        }

        return $container;
    }

    /**
     * Dispatches an event
     *
     * @throws \RuntimeException when the container is not present
     * @throws \RuntimeException when the site directory is not readable
     */
    protected function dispatchEvent($event)
    {
        if (!$this->container) {
            throw new \RuntimeException("The container has not been set up.");
        }
        $dir = $this->container->getParameter('base_dir');
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new \RuntimeException("'${dir}' is not a valid readable directory.");
        }
        $dispatcher = $this->container->get('event_dispatcher');
        $dispatcher->dispatch($event);
    }

    /**
     * Dispatches the setup event.
     * This should create a new site configuration.
     */
    public function setup()
    {
        $this->dispatchEvent('setup');
    }

    /**
     * Dispatches the update event.
     * This should export the changes to the site
     */
    public function update()
    {
        $this->dispatchEvent('update');
    }

    public function isSiteConfigured()
    {
        return $this->container->hasDefinition('site');
    }

}
