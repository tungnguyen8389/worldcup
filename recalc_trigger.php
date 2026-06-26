<?php
$_GET['recalc'] = '1';
include 'api/sync.php';
unlink(__FILE__);
