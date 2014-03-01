<?php
use Mudpuppy\App;

$controller = App::getPageController();
?>
<!DOCTYPE html>
<html>
<head>
	<?php $controller->renderHeader(); ?>
</head>
<body>
<h1>Welcome to the Installer!</h1>
</body>
</html>