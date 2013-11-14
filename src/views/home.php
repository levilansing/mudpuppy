<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');
?>
<h1>Mudpuppy</h1>
This is your home page.
<ul>
    <li><a href="/api/home/getJson?title=hello&message=This is the result of a REST get">Send a sample REST get</a></li>
    <li><a href="/api/home/getJson">Send a malformed REST get</a></li>
    <li><a href="/errorlog">View the error log (requires database configured)</a></li>
</ul>