<?php

namespace WeCodePixels\TheiaBackupBundle\DependencyInjection;

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
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('we_code_pixels_theia_backup');

        $rootNode
            ->children()
                ->scalarNode('aws_access_key_id')->end()
                ->scalarNode('aws_secret_access_key')->end()
                ->scalarNode('gpg_encryption_key')->end()
                ->scalarNode('gpg_signature_key')->end()
                ->scalarNode('gpg_signature_passphrase')->end()
                ->booleanNode('enable_encryption')->defaultValue(true)->end()
                ->arrayNode('backups')->isRequired()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('title')->end()
                            ->scalarNode('aws_access_key_id')->end()
                            ->scalarNode('aws_secret_access_key')->end()
                            ->scalarNode('gpg_encryption_key')->end()
                            ->scalarNode('gpg_signature_key')->end()
                            ->scalarNode('gpg_signature_passphrase')->end()
                            ->scalarNode('enable_encryption')->end()
                            ->scalarNode('source_files')->end()
                            ->arrayNode('source_mysql')
                                ->children()
                                    ->scalarNode('hostname')->end()
                                    ->scalarNode('port')->end()
                                    ->scalarNode('username')->end()
                                    ->scalarNode('password')->end()
                                    ->arrayNode('exclude_databases')
                                        ->prototype('scalar')->end()
                                        ->end()
                                    ->arrayNode('exclude_tables')
                                        ->prototype('scalar')->end()
                                        ->end()
                                ->end()
                            ->end()
                            ->arrayNode('source_postgresql')
                                ->children()
                                    ->scalarNode('hostname')->end()
                                    ->scalarNode('port')->end()
                                    ->scalarNode('username')->end()
                                    ->scalarNode('password')->end()
                                ->end()
                            ->end()
                            ->scalarNode('destination')->isRequired()->end()
                            ->arrayNode('include')->end()
                            ->arrayNode('exclude')->end()
                            ->scalarNode('remove_older_than')->end()
	                        ->booleanNode('allow_source_mismatch')->defaultValue(false)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
