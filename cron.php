<?php
$conf['embed'] = 'standalone';
$conf['index_abs'] = dirname(__FILE__);
require_once('zina/index.php');
zina_init($conf);
zina_cron_run(array('nojs'=>true));
?>
