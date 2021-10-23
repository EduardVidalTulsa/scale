<?php
/************
*
* faig proves
*
************/
require("login.php");
if($login){
$decimalsing=',';
$thousandsing=' ';
	pre(detallrelacio($_REQUEST['uuid'],$ID_Empresa,date('Y'),$link,true));
	}
else echo "nologin";
$link->close();
