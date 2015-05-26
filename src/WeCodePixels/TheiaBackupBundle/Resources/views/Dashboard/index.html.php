<?php

/* @var Symfony\Bundle\FrameworkBundle\Templating\PhpEngine $view */
$view->extend('WeCodePixelsTheiaBackupBundle::base.html.php');

/* @var array $backups */

$view['slots']->start('body_content');
{
    ?>

    <h1>Theia Backup</h1>

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
                            <button class="btn btn-default btn-sm toggle-log" style="display: none">View log</button>
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
                        if ($backupType == 'Files') {
                            ?>
                            <tr>
                                <th>Source files</th>
                                <td><?= $backup['source_files'] ?></td>
                            </tr>
                        <?php
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
                return function (msg) {
                    var status = msg.status;
                    var error = null;

                    if (!status) {
                        error = 'System error';
                    }
                    else {
                        backupElement.find('.log').html(msg.output);

                        if (status.lastBackupTime) {
                            backupElement.find('.last-backup').html(status.lastBackupTime);

                        }
                        else {
                            error = 'Could not get last backup time';
                        }
                    }

                    // Show status.
                    if (!error) {
                        backupElement
                            .removeClass('panel-default')
                            .addClass('panel-success');
                        backupElement.find('.status').html('<span class="label label-success">All good</span>');
                    }
                    else {
                        backupElement.find('.status').html('<span class="label label-danger">' + error + '</span>');
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