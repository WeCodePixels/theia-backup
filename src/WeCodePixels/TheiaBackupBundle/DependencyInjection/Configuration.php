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
                ->scalarNode('azure_account_name')->end()
                ->scalarNode('azure_account_key')->end()
                ->scalarNode('aws_access_key_id')->end()
                ->scalarNode('aws_secret_access_key')->end()
                ->scalarNode('gpg_encryption_key')->end()
                ->scalarNode('gpg_encryption_passphrase')->end()
                ->scalarNode('gpg_signature_key')->end()
                ->scalarNode('gpg_signature_passphrase')->end()
                ->booleanNode('enable_encryption')->defaultValue(true)->end()
                ->scalarNode('temp_dir')->isRequired()->end()
                ->scalarNode('archive_dir')->isRequired()->end()
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
                            ->arrayNode('source_files')
                                ->children()
                                    ->scalarNode('path')->end()
                                    ->scalarNode('additional_args')->end()
                                ->end()
                            ->end()
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
                                    ->scalarNode('cmd')->isRequired()->end()
                                    ->scalarNode('filename')->isRequired()->end()
                                ->end()
                            ->end()
                            ->scalarNode('destination')->isRequired()->end()
                            ->scalarNode('remove_older_than')->end()
	                        ->booleanNode('allow_source_mismatch')->defaultValue(false)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
