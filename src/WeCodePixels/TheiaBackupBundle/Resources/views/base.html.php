<?php

/* @var Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables $app */

/* @var Symfony\Bundle\FrameworkBundle\Templating\PhpEngine $view */

?><!DOCTYPE html>
<html>
<head>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css" rel="stylesheet">
    <?php
    foreach ($view['assetic']->stylesheets([
        '@WeCodePixelsTheiaBackupBundle/Resources/scss/*.scss',
    ], [
        'compass'
    ]) as $url) {
        ?>
        <link rel="stylesheet" type="text/css" href="<?= $view->escape($url) ?>">
    <?php
    }
    ?>
    <script src="http://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    <link rel="icon" type="image/png" href="/bundles/wecodepixelstheiabackup/images/history.png">
    <link rel="icon" type="image/svg+xml" href="/bundles/wecodepixelstheiabackup/images/history.svg">
</head>
<body>
<?php
$view['slots']->output('body_content');
?>

<footer>
    <a href="https://github.com/WeCodePixels/theia-backup">Theia Backup</a>, made with ♥ by <a
        href="https://wecodepixels.com">WeCodePixels</a> and contributors.
</footer>
</body>
</html>