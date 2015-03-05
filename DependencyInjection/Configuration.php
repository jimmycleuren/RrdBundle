<?php

namespace JimmyCleuren\Bundle\RrdBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $treeBuilder->root('rrdbundle')
            ->children()
                ->scalarNode('path')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('types')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('step')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('datasources')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('heartbeat')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('lower_limit')->isRequired()->end()
                                        ->scalarNode('upper_limit')->isRequired()->end()
                                        ->scalarNode('graph_function')->isRequired()->end()
                                        ->scalarNode('graph_type')->isRequired()->end()
                                        ->scalarNode('graph_color')->isRequired()->end()
                                        ->scalarNode('graph_legend')->isRequired()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('archives')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('function')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('steps')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('rows')->isRequired()->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}

