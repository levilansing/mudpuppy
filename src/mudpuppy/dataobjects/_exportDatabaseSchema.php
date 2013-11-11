<html><body>
<?php
	// use this script to generate and update data objects directly from your database schema
	// dataobjects folder and files must be writable in your dev environment (NOT IN PRODUCTION!)

	require("../mudpuppy.php");

	$baseFolder = 'mudpuppy/dataobjects/';
	
	$db = App::getDBO();
	$result = $db->query("SHOW TABLES");
	$tables = $result->fetchAll(PDO::FETCH_COLUMN, 0);


    print '<table><tr><th>Table</th><th>Column</th><th>Type &amp; Length</th><th>Null</th></tr>';
	foreach ($tables as $table) {
        $result = $db->query("SHOW FULL COLUMNS FROM $table");
        $columns = $result->fetchAll(PDO::FETCH_NUM);
        foreach ($columns as $col) {
            print "<tr><td>$table</td><td>$col[0]</td><td>$col[1]</td><td>$col[3]</td></tr>";
        }
	}
    print '</table>';


    print '<table><tr><th>Table</th><th>Column</th><th>Description</th></tr>';
    foreach ($tables as $table) {
        $result = $db->query("SHOW FULL COLUMNS FROM $table");
        $columns = $result->fetchAll(PDO::FETCH_NUM);
        foreach ($columns as $col) {
            print "<tr><td>$table</td><td>$col[0]</td><td></td></tr>";
        }
    }
    print '</table>';


?></body></html><?php App::cleanExit(); ?>