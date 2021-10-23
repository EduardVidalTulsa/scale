<?php
require("login.php");
if ($login==false){header("Location: {$scale_uri}");$link->close();die;}

$r=detallrelacio($_REQUEST['uuid'],$ID_Empresa,date("Y"),$link);

//Aqui faria falta la lògica de via de pago, en cas que n'hi hagi... Ara no en tenim i si convé tornar enrera abans del update
//switch ($_REQUEST['via']){
// 	 case 1: //efectiu
//  case 2: //tarjeta presencial
//  case 3: //bizum
//  case 4: //tarjeta no presencial
//  case 5: //coinbase
// }

$r['ID_Pagament']=$_REQUEST['via'];

if(($r['ID_tipus_relacio']!=3)and($r['ID_tipus_relacio']!=9)){//assignem el tipus de relació depenent de quina sigui la forma de "pagament" 
        // si és una factura no es pot canviar de forma de tipus de relació 
	//es pot triar entre factura (si paguen)o altres tipus relacions proforma, albarà, pressupost...
	//possiblement si hi ha molta i molta gent treballant, potser faria falta bloquejar temporalment index_relacio 
	//per que no cobrin dos a la vegada. 
	$NEW_tipus_relacio=mysql1r("select ID_tipus_relacio from Pagament where ID='{$_REQUEST['via']}'",$link);
	$ID_Empresa_a_mxgestio=mysql1r("SELECT `ID_Empresa_a_mxgestio` FROM `Empresa` WHERE `ID` = '$ID_Empresa'",$link);
	if(($ID_Empresa_a_mxgestio=='1')&&($NEW_tipus_relacio=='3'))$NEW_tipus_relacio=4;
	$NEWID=mysql1r("select COALESCE(MAX(ID), 0)+1 from `{$prefixrelacio}index_relacio` where ID_tipus_relacio='$NEW_tipus_relacio' ",$link);
	$qupdate_lines="UPDATE `{$prefixrelacio}line` SET`ID` = '$NEWID',`ID_tipus_relacio` = '$NEW_tipus_relacio' WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' ";
	if(!$link->query($qupdate_lines)) {error(mysqli_error($link));echo "error assignant línees";die;}
}
else {
	$NEW_tipus_relacio=$r['ID_tipus_relacio'];
	$NEWID=$r['ID'];
}

$qtanca="UPDATE `{$prefixrelacio}index_relacio` SET `relacio_oberta` = '0', `ID_Pagament`='{$_REQUEST['via']}', `ID`='$NEWID',`ID_tipus_relacio` ='$NEW_tipus_relacio'
		WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' ";
$r['email']=urldecode($r['email']);
if(!$link->query($qtanca)) {error(mysqli_error($link));echo "no l'he trobada $qtanca ";die;}
$r['ID_tipus_relacio']=$NEW_tipus_relacio;
$r['ID']=$NEWID;
$r['tipus_relacio']['ID']=$NEW_tipus_relacio;
$r['tipus_relacio']['Value']=mysql1r("SELECT Value from tipus_relacio where ID='$NEW_tipus_relacio'",$link);

if(($_SESSION['Empresa']['cashkeeper']!="")&&($r['ID_Pagament']==1)){ //obro cashkepper
	if (!extension_loaded('sockets')) {die('The sockets extension is not loaded.');}
            $service_port=16501;
            $address=$_SESSION['Empresa']['cashkeeper'];
            //debugtelegram($_SESSION);
            $socket=socket_create(AF_INET, SOCK_STREAM,SOL_TCP);
            if ($socket === false) die( "socket_create() fall<C3><B3>: raz<C3><B3>n: " . socket_strerror(socket_last_error()) . "\n");
            $result=socket_connect($socket,$address,$service_port);
            if ($result === false) echo("no he pugut conectar amb el cash keeper no esta obert<B3>.\nRaz<C3><B3>n: ($result) " . socket_strerror(socket_last_error($socket)) . "\n");
            $total=(number_format($r['Total'],2)*100);
			$in="C|m".mysql1r("select curdate()",$link)."{$r['tipus_relacio']['Value']}:{$ID_Empresa}.{$r['ID']}.{$r['fiscalname']}|$total";
			socket_write($socket,$in,strlen($in));
			socket_close ($socket);
}
if($r['phone']!=''){
	pdf($r);

}
unset ($_SESSION['r']);
$link->close();
//header("Location: {$scale_uri}tiquet.php?uuid={$r['uuid']}");
header("Location: {$scale_uri}?{$r['uuid']}");
?>
