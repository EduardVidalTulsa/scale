<?php

/*
Aquest script està pensat per executar-se en segon pla a través d'una crida periòdica
a la línea de comandes tipus:
  while [ 1 ] ; do curl http[s?]://$_SERVER['HTTP_HOST']/scale/p.php\?ID\=$ID_Empresa ; sleep 10;done;
Només un dels ordinadors de la granja ha de fer correr això. 
En cas contrari surtirien etiquetes duplicades
mireu a /etc/rc.local
ara també compila els .tex en .pdf i els envia per telegram i opcionalment per correu.

*/
date_default_timezone_set('Europe/Andorra');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once('../../keys.php');
require_once('phpgeneral/estil.php');
require_once('phpgeneral/taula.php');
require_once('phpgeneral/mysqli.php');
require_once('phpgeneral/lib.php');
require_once('phpgeneral/etiquetapreu.php');
require_once('vars.php');
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();
$link=new mysqli($myserver,$myuser,$mypassword,$mydatabase);
$empresa=$link->query("SELECT * FROM Empresa where `ID`='{$_REQUEST['ID']}'")->fetch_assoc();
#$args=explode("/",$_SERVER['REQUEST_URI']);//$lang=new lang();
$lang='ca';
function tag_print_command($tag_filename,$empresa){
	//debugtelegram(" ssh {$empresa['printerserver']} \" inkscape /tmp/$tag_filename -o /tmp/tmp.ps \"","tag_print_command");
	exec(" ssh {$empresa['printerserver']} \" inkscape /tmp/$tag_filename -o /tmp/tmp.ps \"");
	exec(" ssh {$empresa['printerserver']} \" lp /tmp/tmp.ps -d {$empresa['sticker_printer']}\" ");
	exec(" ssh {$empresa['printerserver']} \" rm /tmp/tmp.ps -f \" ");
	//exec(" ssh {$empresa['printerserver']} \" rm /tmp/$tag_filename -f \" ");
}
function pmail($taula,$empresa){
	global $localuser,$link, $em;


$q="SELECT * FROM $taula WHERE (`pdf_fet`='0'or(`email` like '%@%' AND `email_enviat`<'1')) AND (`datetime_creation` <= DATE_SUB( now(), INTERVAL 5 MINUTE ))";
if ($empresa['bool_print_pdf']==1){
	$qprint="SELECT * FROM $taula WHERE (`pdf_fet`='0' or `print_A4`='0') AND (`datetime_creation` <= DATE_SUB( now(), INTERVAL 3 SECOND))";
	if ($link->query($qprint)->num_rows>='1')$q=$qprint;
}	
//echo $q;br();echo$qprint;
//debugtelegram($q,"{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);

$i=$link->query($q)or die ($q.$link->error);
while ($pdf=$i->fetch_assoc()){
	$f=fopen("pdf/{$pdf['uuid']}.tex",'w')or die ('no he pugut crear arxiu');
	fwrite($f,urldecode($pdf['LaTeX']));
	fclose($f);
	//localuser set in keys.php usualy empty but if ssh localhost from http fail (in rpi often happen) set to "user@" in keys.php or whatever
	exec("ssh {$localuser}localhost \"cd {$_SERVER['DOCUMENT_ROOT']}/scale/pdf; texi2pdf {$pdf['uuid']}.tex; chown http * -f\"");
	exec("cd {$_SERVER['DOCUMENT_ROOT']}/scale/pdf; texi2pdf {$pdf['uuid']}.tex; chown http * -f",$rexec);
	exec ("scp {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf ametlles.tulsa.eu:/https/ametlles.tulsa.eu/html/pdf/ ");
	$public="https://ametlles.tulsa.eu/pdf/{$pdf['uuid']}.pdf";
	$telegramme=urlencode("Hola hem fet: ".mysql1r("SELECT `Value` FROM `tipus_relacio` WHERE `ID`='{$pdf['ID_tipus_relacio']}'",$link)." {$empresa['ID']}.{$pdf['ID']} \na nom de ".urldecode("{$pdf['fiscal_name']}+{$pdf['email']}")."\nté el teléfon: {$pdf['phone']}\n".$public);
	//$public=urlencode($public);
	$response = file_get_contents("https://api.telegram.org/bot$telegramtoken/sendMessage?chat_id={$empresa['telegram_relacions']}&text=$telegramme" );
	debugtelegram(urldecode($telegramme),"{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);

	//$data[ 'chat_id' => '-'.$empresa['telegram_relacions'] , 'document' => $public ];
	//$response = file_get_contents("https://api.telegram.org/bot$telegramtoken/sendDocument?chat_id=-{$empresa['telegram_relacions']}&document=$public");
	//update pdf_fet aqui
	$u="UPDATE `$taula` SET `pdf_fet` = '1' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
	$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
	if (correuvalid($pdf['email']) and ($pdf['email_enviat']<1)and ($pdf['email_enviat']>-4)){
		$mail = new PHPMailer(true);
		try {
			//Server settings
		// 	$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
			$mail->isSMTP();                                            // Send using SMTP
			$mail->Host       = $em['host'];                    // Set the SMTP server to send through
    			$mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    			//$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
    			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
  	 		$mail->Port       = 587;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
    			$mail->Username   = $em['user'];                     		// SMTP username
    			$mail->Password   = $em['passwd'];                               // SMTP password
			$mail->CharSet = 'UTF-8';
   		 //Recipients
    			$mail->setFrom($empresa['email'], $empresa['Value']);
    			//$mail->setFrom($em['user'], $empresa['Value']);
    			$mail->addAddress($pdf['email'],urldecode($pdf['fiscal_name']));     // Add a recipient
    			$mail->addBCC($empresa['email']);
    		// Attachments
    			$mail->addAttachment("{$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf");         // Add attachments
		// Content
    			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = 'Enviem '.
				mysql1r("SELECT `Value` FROM `tipus_relacio` WHERE `ID`='{$pdf['ID_tipus_relacio']}'",$link).": 
			{$empresa['ID']}.{$pdf['ID']}";
    			$mail->Body    = "
Moltes gràcies per confiar amb nosaltres
<br/>
L’informem que les seves dades personals,  que puguin constar en aquesta comunicació, estan incorporades en un fitxer propietat de {$empresa['Value']}, amb la finalitat d'enviar-li comunicacions de caire comercial o de prestació de serveis. Si desitja exercitar els drets d'accés, rectificació, cancel•lació o oposició pot dirigir-se per escrit a la següent direcció: {$empresa['Direccio']} {$empresa['Poblacio']}
<br/>
En el cas que no desitgi rebre més comunicacions a través del correu electrònic, pot enviar un missatge a la següent adreça electrònica: {$empresa['email']}<br/>
<small>
Advertència: La informació inclosa en aquest e-mail és CONFIDENCIAL, essent per a ús exclusiu del destinatari a dalt esmentat. Si vostè llegeix aquest missatge i no és el destinatari indicat, li informem que està totalment prohibida qualsevol utilització, divulgació, distribució i/o reproducció d’aquesta comunicació, total o parcial, sense autorització expressa en virtut de la legislació vigent. Si ha rebut aquest missatge per error, li preguem que ens ho notifiqui immediatament per aquesta via i procedeixi a la seva eliminació juntament amb els fitxers annexes sense llegir-lo, ni difondre, ni emmagatzemar o copiar el seu contingut.<br/>

Advertencia: La Información incluida en este e-mail es CONFIDENCIAL, siendo para uso exclusivo del destinatario arriba mencionado. Si Ud. lee este mensaje y no es el destinatario indicado, le informamos que está totalmente prohibida cualquier utilización, divulgación, distribución y/o reproducción de esta comunicación, total o parcial, sin autorización expresa en virtud de la legislación vigente. Si ha recibido este mensaje por error, le rogamos nos lo notifique inmediatamente por esta vía y proceda a su eliminación junto con sus ficheros anexos sin leerlo, ni difundir, ni almacenar o copiar su contenido.
</small>
";
    			$mail->AltBody = "
Moltes gràcies per confiar amb nosaltres
L’informem que les seves dades personals,  que puguin constar en aquesta comunicació, estan incorporades en un fitxer propietat de {$empresa['Value']}, amb la finalitat d'enviar-li comunicacions de caire comercial o de prestació de serveis. Si desitja exercitar els drets d'accés, rectificació, cancel•lació o oposició pot dirigir-se per escrit a la següent direcció: {$empresa['Direccio']} {$empresa['Poblacio']}  En el cas que no desitgi rebre més comunicacions a través del correu electrònic, pot enviar un missatge a la següent adreça electrònica: {$empresa['email']}

Advertència: La informació inclosa en aquest e-mail és CONFIDENCIAL, essent per a ús exclusiu del destinatari a dalt esmentat. Si vostè llegeix aquest missatge i no és el destinatari indicat, li informem que està totalment prohibida qualsevol utilització, divulgació, distribució i/o reproducció d’aquesta comunicació, total o parcial, sense autorització expressa en virtut de la legislació vigent. Si ha rebut aquest missatge per error, li preguem que ens ho notifiqui immediatament per aquesta via i procedeixi a la seva eliminació juntament amb els fitxers annexes sense llegir-lo, ni difondre, ni emmagatzemar o copiar el seu contingut.

Advertencia: La Información incluida en este e-mail es CONFIDENCIAL, siendo para uso exclusivo del destinatario arriba mencionado. Si Ud. lee este mensaje y no es el destinatario indicado, le informamos que está totalmente prohibida cualquier utilización, divulgación, distribución y/o reproducción de esta comunicación, total o parcial, sin autorización expresa en virtud de la legislación vigente. Si ha recibido este mensaje por error, le rogamos nos lo notifique inmediatamente por esta vía y proceda a su eliminación junto con sus ficheros anexos sin leerlo, ni difundir, ni almacenar o copiar su contenido.
";

 		$mail->send();
		//echo 'Message has been sent';
		//debugtelegram("correu enviat a {$pdf['email']}","{$_SERVER['HTTP_HOST']}");
		//update pdf enviat aqui
		$u="UPDATE `$taula` SET `email_enviat` = '1' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
			$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
		} 
		catch (Exception $e) {
		if ($pdf['email_enviat']=='')$pdf['email_enviat']=-1;
		else $pdf['email_enviat']--;
		$u="UPDATE `$taula` SET `datetime_creation` = now() , `email_enviat`= '{$pdf['email_enviat']}'  WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
		$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
		debugtelegram(date('His')."/>correu NO enviat a {$pdf['email']}\nError: {$mail->ErrorInfo}","{$_SERVER['HTTP_HOST']}");
		//debugtelegram($mail);
		//debugtelegram($u);
	}
	}//fi if correu_valid
	else { 
		echo "no mail";
		if ($pdf['email']=="") debugtelegram("PDF sense correu","{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);
		else debugtelegram('correu no valid '.$pdf['email'],"{$_SERVER['HTTP_HOST']}");
		$u="UPDATE `$taula` SET `email_enviat` = '2' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
		$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
	}
	if(($empresa['bool_print_pdf']=='1')&&($pdf['print_A4']=='0')){
        debugtelegram("imprimeixo una copia de ".mysql1r("SELECT `Value` FROM `tipus_relacio` WHERE `ID`='{$pdf['ID_tipus_relacio']}'",$link)." {$empresa['ID']}.{$pdf['ID']}",$_SERVER['HTTP_HOST'],$empresa['telegram_relacions']);
		exec ("scp {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf {$empresa['printerserver']}:/tmp ");
		exec ("ssh {$empresa['printerserver']} \"lp /tmp/{$pdf['uuid']}.pdf -d {$empresa['a4_printer']}\"");
	}
	if(($empresa['bool_print_pdf']=='1')&&($pdf['ID_tipus_relacio']=='2')&&($pdf['print_A4']=='0')){
        debugtelegram("imprimeixo una 2a copia de Albarà",$_SERVER['HTTP_HOST'],$empresa['telegram_relacions']);
		exec ("scp {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf {$empresa['printerserver']}:/tmp ");
		exec ("ssh {$empresa['printerserver']} \"lp /tmp/{$pdf['uuid']}.pdf -d {$empresa['a4_printer']}\"");
		//exec ("ssh {$empresa['printerserver']} \"lp /tmp/{$pdf['uuid']}.pdf -d {$empresa['a4_printer']}\"");
	}
	if($empresa['bool_print_pdf']=='1'){
	  $u="UPDATE `$taula` SET `print_A4` = '1' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}'";
	  $link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
	}
	$rm="ssh {$localuser}localhost \" rm {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/* -f \"";
	//debugtelegram($localuser."es localuser");
	exec ($rm);
	}//fi del while dels pdf
}



//main 
//
//
//
//
//part etiquetes de preu: 
$qprint=$link->query("SELECT * FROM imprimeix");
while($print=$qprint->fetch_assoc()){
	//debugtelegram($print);
	$borra="DELETE FROM `imprimeix` WHERE `ID` = '{$print['ID']}';";
   	if($link->query($borra)===FALSE){ debugtelegram($q.mysqli_error($link));die;}
	for ($i=1;$i<=$print['units'];$i++){
		if($print['preu_or_etiqueta']==0){
			$decimalsing=',';
			$thousandsing=' ';
			$ID=$print['ID_product'];
			$d=$link->query("select * from scalelist where ID='$ID'")->fetch_assoc();
			$pvp=number_format($d['pvp'],2,$decimalsing,$thousandsing);
			$nom=strtoupper(urldecode($d['value']));
			$d['bulk']==1?$u='Kg':$u='uni';
			$origen='Preguntar a botiga';
        		$arxiu=etiquetapreu($ID,$pvp,$nom,$u,$origen);
			$nf=str_pad($ID,4, "0", STR_PAD_LEFT).'_'.urlencode(str_replace(' ','_',$nom)).'.svg';
			$f=fopen($nf,'w')or die ;
			fwrite($f, $arxiu);
			fclose($f);
			$scp="ssh {$localuser}localhost \"scp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf {$empresa['printerserver']}:/tmp \"";
			//debugtelegram($scp);//echo $scp;
        	  	exec ($scp) ;
        	   	tag_print_command($nf,$empresa);
			exec ("ssh {$localuser}localhost \" rm -f {$_SERVER['DOCUMENT_ROOT']}/scale/$nf \"");
		}
        	else {//és una etiqueta de producte
//      	       echo imprimeixo;
        	    exec ("ssh  {$empresa['printerserver']} \"lp /home/etiquetes/{$print['ID_product']}.ps -d  {$empresa['sticker_printer']}\"");
		}
	}
}

//part dels pdf i mails 
//$taula=date('Y')."_Empresa_{$empresa['ID']}_pdf";//ara $taula es dinàmic envia tot lo que hi hagi al servidor, segurament acabarem centralitzant aquesta feina a ametlles
$q="show tables like '%_pdf'";
$l=$link->query($q);
while($f=$l->fetch_row()){
	//pre($f);
	if($f[0]!="meta_pdf"){
		$a=explode("_",$f[0]);
		//pre($a);
		$taula=$f[0];
		$empresa=$link->query("SELECT * FROM Empresa where `ID`='{$a[2]}'")->fetch_assoc();
		//pre($empresa);
		//pre($taula);
		pmail($taula,$empresa);
	}
}
$link->close();
?>
