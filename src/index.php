<?php
require("mudpuppy/mudpuppy.php");
$controller = App::getPageController();
?>
<!doctype html>
<!--[if lt IE 7]>
<html class="no-js lt-ie9 lt-ie8 lt-ie7" lang="en"> <![endif]-->
<!--[if IE 7]>
<html class="no-js lt-ie9 lt-ie8" lang="en"> <![endif]-->
<!--[if IE 8]>
<html class="no-js lt-ie9" lang="en"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class="no-js" lang="en"> <!--<![endif]-->
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <title><?php print Config::$appTitle; ?></title>
<?php App::getPageController()->renderHeader(); ?>
</head>
<body>
<?php App::getPageController()->render(); ?>
</body>
</html>
<?php App::cleanExit(); ?>