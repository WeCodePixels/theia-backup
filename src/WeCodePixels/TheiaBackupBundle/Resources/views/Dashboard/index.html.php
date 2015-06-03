<?php

use WeCodePixels\TheiaBackupBundle\BackupStatusService;

/* @var Symfony\Bundle\FrameworkBundle\Templating\PhpEngine $view */
$view->extend('WeCodePixelsTheiaBackupBundle::base.html.php');

/* @var array $backups */

$view['slots']->start('body_content');
{
    ?>

    <h1>Theia Backup for <?=gethostname()?></h1>

    <div class="backups">
        <?php
        foreach ($backups as $backupId => $backup) {
            $backupType = 'Unknown';
            if (array_key_exists('source_files', $backup)) {
                $backupType = 'Files';
            } else if (array_key_exists('source_mysql', $backup)) {
                $backupType = 'MySQL';
            } else if (array_key_exists('source_postgresql', $backup)) {
                $backupType = 'PostgreSQL';
            }
            ?>

            <div>
                <article class="panel panel-default" id="<?= $backupId ?>">
                    <div class="panel-heading">
                        <h1><?= $backup['title'] ?></h1>

                        <div class="right">
                            <span class="ajax-loader"></span>
                            <button class="btn btn-default btn-sm toggle-log" style="display: none">View logs</button>
                            <div class="modal fade" role="dialog">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            Log of <strong><?= $backupId ?></strong>
                                        </div>
                                        <div class="modal-body">
                                            <textarea class="form-control log" rows="20"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="table details">
                        <tr>
                            <th>Destination</th>
                            <td><?= $backup['destination'] ?></td>
                        </tr>
                        <tr>
                            <th>Backup type</th>
                            <td><?= $backupType ?></td>
                        </tr>
                        <?php
                        switch ($backupType) {
                            case 'Files':
                                echoBackupInfoRows(array(
                                    'source_files' => $backup['source_files']
                                ), array(
                                    'source_files' => 'Source files'
                                ));
                                break;

                            case 'MySQL':
                                echoBackupInfoRows($backup['source_mysql'], array(
                                    'hostname' => 'Host',
                                    'exclude_databases' => 'Exclude databases',
                                    'exclude_tables' => 'Exclude tables'
                                ));
                                break;

                            case 'PostgreSQL':
                                echoBackupInfoRows($backup['source_postgresql'], array());
                                break;
                        }
                        ?>
                        <tr>
                            <th>Last backup</th>
                            <td class="last-backup"></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td class="status"></td>
                        </tr>
                    </table>

                    <script>
                        $(document).ready(function () {
                            var ajaxUrl = <?=json_encode($view['router']->generate('theiabackup_ajax_backup_status', array('backupId' => $backupId)))?>;
                            var backupId = <?=json_encode($backupId)?>;

                            getBackupStatus(ajaxUrl, $('#' + backupId));
                        });
                    </script>
                </article>
            </div>

        <?php
        }
        ?>
    </div>

    <script>
        function getBackupStatus(ajaxUrl, backupElement) {
            $.ajax(ajaxUrl).always(function () {
                return function (data, textStatus, xhr) {
                    var status = data.status;
                    var errorMsg = null;

                    if (data.output) {
                        backupElement.find('.log').html(data.output);
                    }

                    if (!status || !xhr || xhr.status != 200) {
                        errorMsg = 'System error, check logs';
                    }
                    else {
                        switch (status.error) {
                            case <?=BackupStatusService::ERROR_NO_BACKUP?>:
                                errorMsg = 'No backup found';
                                break;

                            case <?=BackupStatusService::ERROR_OLD_BACKUP?>:
                                errorMsg = 'Backup too old';
                                break;

                            case <?=BackupStatusService::ERROR_OK?>:
                                break;
                        }
                    }

                    // Show last backup age.
                    if (status.lastBackupAge) {
                        backupElement.find('.last-backup').html(status.lastBackupAge + ' (' + status.lastBackupText + ')');
                    }

                    // Show error message.
                    if (!errorMsg) {
                        backupElement
                            .removeClass('panel-default')
                            .addClass('panel-success');
                        backupElement.find('.status').html('<span class="label label-success">All good</span>');
                    }
                    else {
                        backupElement.find('.status').html('<span class="label label-danger">' + errorMsg + '</span>');
                        backupElement
                            .removeClass('panel-default')
                            .addClass('panel-danger');
                    }

                    // Hide ajax loader.
                    backupElement.find('.ajax-loader').remove();

                    // Show toggle log button.
                    backupElement.find('.toggle-log')
                        .on('click', function () {
                            $(this).parent().find('.modal').modal('show');
                        })
                        .show();
                }
            }(backupElement));
        }
    </script>
<?php
}
$view['slots']->stop('body_content');

function echoBackupInfoRows($backup, $keys)
{
    foreach ($keys as $key => $title) {
        if (array_key_exists($key, $backup)) {
            $value = $backup[$key];
            ?>
            <tr>
                <th><?= $title ?></th>
                <td><?= is_array($value) ? implode(', ', $value) : $value ?></td>
            </tr>
        <?php
        }
    }
}