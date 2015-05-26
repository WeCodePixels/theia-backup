<?php

namespace WeCodePixels\TheiaBackupBundle;

class ConfigurationService
{
    /* @var array; */
    private $config;

    public function setConfiguration($config)
    {
        $this->config = $config;
    }

    public function getConfiguration()
    {
        return $this->config;
    }

    public function parseConfig($config)
    {
        if (!is_array($config['destination'])) {
            $config['destination'] = [$config['destination']];
        }

        // Get credentials.
        {
            $config['duplicity_credentials_cmd'] = "";
            if (
                $config['aws_access_key_id'] &&
                $config['aws_secret_access_key']
            ) {
                $config['duplicity_credentials_cmd'] .= "
                export AWS_ACCESS_KEY_ID='$config[aws_access_key_id]'
                export AWS_SECRET_ACCESS_KEY='$config[aws_secret_access_key]'
            ";
            }

            if (isset($config['gpg_encryption_passphrase'])) {
                $config['duplicity_credentials_cmd'] .= "
                export PASSPHRASE='$config[gpg_encryption_passphrase]'
            ";
            }

            if (isset($config['gpg_signature_passphrase'])) {
                $config['duplicity_credentials_cmd'] .= "
                export SIGN_PASSPHRASE='$config[gpg_signature_passphrase]'
            ";
            }
        }

        // Get additional options.
        {
            $config['additional_options'] = [];
            //        if (isset($config['sshOptions'])) {
            //            $config['additional_options'][] = "--ssh-options=\"" . $config['sshOptions'] . "\"";
            //        }

            if (!$config['enable_encryption']) {
                $config['additional_options'][] = "--no-encryption";
            }

            if ($config['gpg_encryption_key']) {
                $config['additional_options'][] = "--encrypt-key=" . $config['gpg_encryption_key'];
            }

            if ($config['gpg_signature_key']) {
                $config['additional_options'][] = "--sign-key=" . $config['gpg_signature_key'];
            }

            $config['additional_options'] = implode(' ', $config['additional_options']);
        }

        return $config;
    }
}