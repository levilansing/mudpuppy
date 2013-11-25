<?php
require("mudpuppy/mudpuppy.php");
$controller = App::getPageController();
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">
	<?php $controller->renderHeader(); ?>
</head>
<body>
<?php $controller->render(); ?>
</body>
</html>