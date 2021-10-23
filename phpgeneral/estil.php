<?php
//include que conté els estils de capçalera i peu de plana

function estil_capcal($titol="Hauries de pasar alguna cosa com a titol no?",$nom="./",$viewport=false){
//html5 
echo  "<!DOCTYPE html><html lang=\"ct\"><head><meta charset=\"utf-8\">";
//echo'<link href="https://fonts.googleapis.com/css?family=Indie+Flower" rel="stylesheet">';
$uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
$uri.=$_SERVER['HTTP_HOST'];
echo"<link rel=\"stylesheet\" type=\"text/css\" href=\"{$uri}/scale//estil.css\"><title>$titol</title></head><body><a href=\"$nom\"><h1>$titol</h1></a>";
if($viewport)echo'<meta name="viewport" content="width=device-width, initial-scale=1">';

}
function estil_tancament($link,$origen=__FILE__)
{
require("{$_SERVER['DOCUMENT_ROOT']}/scale/vars.php");
$uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
$uri.=$_SERVER['HTTP_HOST'];
$filemod = filemtime($origen);
$filemodtime = date(" j/m/Y h:i:s A", $filemod);
divclass('peu');
echo '&copy;'.date(Y).' '.$ownername.' '.
ar($mytelegram,imgr("{$_SERVER['DOCUMENT_ROOT']}/scale/".'/img/telegram.png',"10px","10px" ,'mytelegram'),'blank').
ar($mytwitter,imgr("{$_SERVER['DOCUMENT_ROOT']}/scale/".'/img/twitter.png',"10px","10px" ,'mytwitter'),'blank').
ar($myfacebook,imgr("{$_SERVER['DOCUMENT_ROOT']}/scale/".'/img/facebook.png',"10px","10px" ,'myfacebook'),'blank').
ar($myinstagram,imgr("{$_SERVER['DOCUMENT_ROOT']}/scale/".'/img/instagram.png',"10px","10px",'mytelegram'),'blank').
' '.
ar($uri.'/cookies/',imgr("{$_SERVER['DOCUMENT_ROOT']}/scale/".'/img/cookies.gif',"10px","10px",'cookies').$s[$lang]['see privacy']).$filemodtime;
tdiv();
//phpinfo();

// divclass('cookie-bar');
// echo ar('https://'.$webname.'/cookies/',imgr('/img/cookies.gif',"10px","10px",'cookies').$s[$lang]['see privacy']);
// tdiv();
     //divclass('address');print ("Modificada el  $filemodtime");tdiv();
echo'
<script src='."{$_SERVER['DOCUMENT_ROOT']}/scale/".'"phpgeneral/bootstrap.min.js"></script>
<script src='."{$_SERVER['DOCUMENT_ROOT']}/scale/".'"phpgeneral/jquery-2.1.4.min.js"></script>
';
echo "</body>
</html>";
}
?>
