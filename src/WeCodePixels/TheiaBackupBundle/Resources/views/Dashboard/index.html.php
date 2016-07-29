<?php

use WeCodePixels\TheiaBackupBundle\BackupStatusService;

/* @var Symfony\Bundle\FrameworkBundle\Templating\PhpEngine $view */
$view->extend('WeCodePixelsTheiaBackupBundle::base.html.php');

/* @var array $backups */

$view['slots']->start('body_content');
{
    ?>
    <script>
        var backups = [];
    </script>

    <h1 class="panel panel-default">
        Theia Backup for <?=gethostname()?>
    </h1>

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
                            <span class="ajax-loader" style="display: none"></span>
                            <button class="btn btn-default btn-sm toggle-log" style="display: none"><i class="fa fa-file-text-o" aria-hidden="true"></i> View logs</button>
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

                    <table class="table table-striped details">
                        <tr>
                            <th><i class="fa fa-folder-open-o" aria-hidden="true"></i> Destination</th>
                            <td><?= $backup['destination'] ?></td>
                        </tr>
                        <tr>
                            <th><i class="fa fa-file-o" aria-hidden="true"></i> Backup type</th>
                            <td><?= $backupType ?></td>
                        </tr>
                        <?php
                        switch ($backupType) {
                            case 'Files':
                                echoBackupInfoRows(array(
                                    'source_files' => $backup['source_files']
                                ), array(
                                    'source_files' => '<i class="fa fa-files-o" aria-hidden="true"></i> Source files'
                                ));
                                break;

                            case 'MySQL':
                                echoBackupInfoRows($backup['source_mysql'], array(
                                    'hostname' => '<i class="fa fa-server" aria-hidden="true"></i> Host',
                                    'exclude_databases' => '<i class="fa fa-database" aria-hidden="true"></i> Exclude databases',
                                    'exclude_tables' => '<i class="fa fa-table" aria-hidden="true"></i> Exclude tables'
                                ));
                                break;

                            case 'PostgreSQL':
                                echoBackupInfoRows($backup['source_postgresql'], array());
                                break;
                        }
                        ?>
                        <tr>
                            <th><i class="fa fa-clock-o" aria-hidden="true"></i> Last checked on</th>
                            <td class="status-timestamp"></td>
                        </tr>
                        <tr>
                            <th><i class="fa fa-clock-o" aria-hidden="true"></i> Last backup</th>
                            <td class="last-backup"></td>
                        </tr>
                        <tr>
                            <th><i class="fa fa-stethoscope" aria-hidden="true"></i> Status</th>
                            <td class="status"></td>
                        </tr>
                    </table>

                    <script>
                        backups.push({
                            ajaxUrl: <?=json_encode($view['router']->generate('theiabackup_ajax_backup_status', array('backupId' => $backupId)))?>,
                            backupId: <?=json_encode($backupId)?>
                        });
                    </script>
                </article>
            </div>

        <?php
        }
        ?>
    </div>

    <script>
        function refreshBackupStatus() {
            for (var i = 0; i < backups.length; i++) {
                var backup = backups[i];

                getBackupStatus(backup.ajaxUrl, $('#' + backup.backupId));
            }
        }

        function getBackupStatus(ajaxUrl, backupElement) {
            // Don't do anything if still loading.
            if (backupElement.find('.ajax-loader').is(':visible')) {
                return;
            }

            backupElement.find('.ajax-loader').show();
            backupElement.find('.toggle-log').hide();

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

                    // Show status timestamp.
                    if (status.timestampAge) {
                        backupElement.find('.status-timestamp').html(status.timestampAge + ' (' + status.timestampText + ')');
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
                    backupElement.find('.ajax-loader').hide();

                    // Show toggle log button.
                    backupElement.find('.toggle-log')
                        .on('click', function () {
                            $(this).parent().find('.modal').modal('show');
                        })
                        .show();
                }
            }(backupElement));
        }

        $(document).ready(function () {
            refreshBackupStatus();

            setInterval(refreshBackupStatus, 60000);
        });
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