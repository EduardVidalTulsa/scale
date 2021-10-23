<?php
function debugtelegram($debug,$valor='debug',$telegramchannel=FALSE,$return=FALSE){
	global $telegramtoken,$telegramdebugchannel;
	if($telegramchannel==FALSE)$telegramchannel=$telegramdebugchannel;
	if (is_array($debug)){
		foreach ($debug AS $k=> $v){
			$telegramme.=debugtelegram($v,"$valor".'['.$k.']',$telegramchannel,true)."\n";
		}
	}
	else $telegramme="$valor: ".urldecode($debug);
	if($return)return $telegramme;
	else $response = file_get_contents("https://api.telegram.org/bot$telegramtoken/sendMessage?chat_id=-$telegramchannel&text=".urlencode($telegramme) );
}
function myErrorHandler($errno, $errstr, $errfile, $errline){
   /* if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }
	*/
	  // $errstr may need to be escaped:
    $errstr = htmlspecialchars($errstr);

    switch ($errno) {
    case E_USER_ERROR:
        $d=debugtelegram( "<b>My ERROR</b> [$errno] $errstr<br />\n",true);
        $d.=debugtelegram( "  Fatal error on line $errline in file $errfile",true);
      	$d.=debugtelegram(", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n",true);
        $d.=debugtelegram( "Aborting...<br />\n",true);
		debugtelegram($d);
        exit(1);

    case E_USER_WARNING:
        debugtelegram("<b>My WARNING</b> [$errno] $errstr<br />\n");
        break;

    case E_USER_NOTICE:
        debugtelegram("<b>My NOTICE</b> [$errno] $errstr<br />\n");
        break;
        case E_WARNING:
		 $_SESSION['E_WARNING'].="E_WARNING $errstr on on line: $errline@$errfile\n";
		break;
	case E_NOTICE:
		 $_SESSION['E_NOTICE'].="E_NOTICE $errstr on on line: $errline@$errfile\n";
		break;
    default:
        debugtelegram("Unknown error type: [$errno] $errstr on line: $errline@$errfile <br />\n");
        break;
    }

    /* Don't execute PHP internal error handler */
    return true;

}
function prefered_language(array $available_languages, $http_accept_language) {
    $available_languages = array_flip($available_languages);
    $langs = [];
    preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~', strtolower($http_accept_language), $matches, PREG_SET_ORDER);
    foreach($matches as $match) {
        list($a, $b) = explode('-', $match[1]) + array('', '');
        $value = isset($match[2]) ? (float) $match[2] : 1.0;
        if(isset($available_languages[$match[1]])) {
            $langs[$match[1]] = $value;
            continue;
        }
        if(isset($available_languages[$a])) {
            $langs[$a] = $value - 0.1;
        }
    }
    arsort($langs);
    return $langs;
}
function number_format_trim($value,$maxdecimal=0,$decimalsing='.',$thousandsing=','){
		return rtrim(rtrim(number_format($value,$maxdecimal,$decimalsing,$thousandsing),0),$decimalsing);
}
function normalize($cadena){
    //treu dead keys
      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞ
ßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuy
bsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    $cadena = utf8_decode($cadena);
    $cadena = strtr($cadena, utf8_decode($originales), $modificadas);
    $cadena = strtolower($cadena);
    return utf8_encode($cadena);
}
function treuapostrof($cadena){
    return str_replace("'", ' ',$cadena);
}
function tlinea($index_relacio,$qllista,$link){
if($llista=$link->query($qllista)){
	while ($f = $llista->fetch_assoc()){
		$tlinea=round($f['units']*$f['preu'],2);
		if($f['bulk'])$f['u']='Kg';
		else $f['u']='uni';
		$f['urlde']=urldecode($f['description']);
		if($index_relacio['tax_detall']==0){
			//iva inclos: si la relacio es amb iva inclós el descompte va sobre el preu amb iva
			$f['cost']=round($f['cost']*(1+($f['IVA']/100)),2);
			$f['tline']=round($f['units']*($f['cost']*(1-($f['discount']/100))),2);
			$f['Base']=round($f['tline']*(1-($f['IVA']/100)),2);
		}
		else{//si no, el descompte va sobre el preu de base
			$f['Base']=round($f['units']*$f['cost']*(1-($f['discount']/100)),2);
			$f['tline']=round($f['Base']*(1+($f['IVA']/100)),2);
		}
		//if($index_relacio['equivalency_charge']==1)
			//$f['tec']=round($f['tline']*($f['EQUIVALENCY_CHARGE']/100),2);
			//$f['tec']=round($f['tline']);
		$f['tiva']=$f['tline']-$f['Base'];
		if($f['deleted_line']==0){//si es línea activa sumem la línea als sumatoris totals
			if($index_relacio['equivalency_charge']==1)
            			$index_relacio['EC'][$f['EQUIVALENCY_CHARGE']]=$index_relacio['EC'][$f['EQUIVALENCY_CHARGE']]+$f['Base'];
			$index_relacio['Base']=$index_relacio['Base']+$f['Base'];
			$index_relacio['Total']=$index_relacio['Total']+$f['Base']+$f['tiva']+$f['tec'];
			$index_relacio['IVA'][$f['IVA']]=$index_relacio['IVA'][$f['IVA']]+$f['tiva'];
			$index_relacio['B'][$f['IVA']]=$index_relacio['B'][$f['IVA']]+$f['Base'];
			$index_relacio['T'][$f['IVA']]=$index_relacio['T'][$f['IVA']]+$f['tline']+$f['tec'];
			if(isset($f['cost_price'])){
				$index_relacio['C'][$f['IVA']]=$index_relacio['C'][$f['IVA']]+(round($f['units']*$f['cost_price'],2));
				$index_relacio['Cost']=$index_relacio['Cost']+(round(round($f['units']*$f['cost_price'],2)*(1+($f['IVA']/100)),2));
			}
			$index_relacio['active_lines'][$f['line']]=$f;
			if($f['discount']!=0)$index_relacio['discountinline']=true;
		}
		else $index_relacio['deleted_lines'][$f['line']]=$f;
	}
}
	return $index_relacio;
}
function detallrelacio($uuid,$ID_Empresa,$year,$link,$debug=false){
//retorna tota una relació dintre una matriu.
$FROMindex_relacio="FROM {$year}_Empresa_{$ID_Empresa}_index_relacio WHERE uuid='$uuid'";
if($debug)echo"SELECT * $FROMindex_relacio";
$index_relacio=$link->query("SELECT * $FROMindex_relacio")->fetch_assoc();
$FROMactive_lines="FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID_tipus_relacio='{$index_relacio['ID_tipus_relacio']}' and ID='{$index_relacio['ID']}' and line>0 and deleted_line=0 ";
$FROMdeleted_lines="FROM {$year}_Empresa_{$ID_Empresa}_line where ID_tipus_relacio='{$index_relacio['ID_tipus_relacio']}' and ID='{$index_relacio['ID']}' and line>0 and deleted_line=1";
$index_relacio['Nom_Venedor']=mysql1r("SELECT Value from {$ID_Empresa}_Venedor where ID='{$index_relacio['ID_Venedor']}'",$link);
$index_relacio['activelines']=mysql1r("SELECT COUNT(*) $FROMactive_lines",$link);
$index_relacio['deletedlines']=mysql1r("SELECT COUNT(*) $FROMdeleted_lines",$link);
$FROM="FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID_tipus_relacio='{$index_relacio['ID_tipus_relacio']}' and ID='{$index_relacio['ID']}' and line>0";
$qllista="SELECT * $FROM ORDER BY line";
//poso els contadors a zero perque si fas un print_r estiguin a la part de dalt, no per a res més
$index_relacio['Base']=0;
$index_relacio['Total']=0;
$index_relacio['Cost']='0';
$index_relacio['discountinline']=false;
// $qiva=$link->query("SELECT * from IVA ORDER BY Value");
$index_relacio['Empresa']=$link->query("SELECT * from Empresa where ID='$ID_Empresa'")->fetch_assoc();
$qiva=$link->query("SELECT IVA $FROMactive_lines group by IVA ORDER BY IVA");
while($iva=$qiva->fetch_assoc()){
	$index_relacio['IVA'][$iva['IVA']]=0;
	$index_relacio['B'][$iva['IVA']]=0;
	$index_relacio['T'][$iva['IVA']]=0;
	$index_relacio['C'][$iva['IVA']]=0;
// 	$qec=$link->query("SELECT * from equivalency_charge where ID_iva={$iva['ID']}");
	$qec=$link->query("SELECT EQUIVALENCY_CHARGE $FROMactive_lines AND IVA='{$iva['IVA']}' group by EQUIVALENCY_CHARGE ");
	while($eq=$qec->fetch_assoc()){
		$index_relacio['EC'][$eq['EQUIVALENCY_CHARGE']]=0;
	}
}
if(($index_relacio['Empresa']['sell_wholesale']=='1')&&($index_relacio['equivalency_charge']==1))$index_relacio['tax_detall']=1;//per assegurar-se

$index_relacio=tlinea($index_relacio,$qllista,$link);	//$index_relacio=tlinea($index_relacio,false,$qllista_eliminades,$link);

//if($debug)pre($index_relacio);
//aplicar el descompte total si convé i calcular el EC també serveix amb discount==0 però son milisegons aqui a calcular
	if($index_relacio['tax_detall']==0)		{
// 			echo "hi ha discount sense detall";
		$index_relacio['Total']=0;
		$index_relacio['Base']=0;
		//fer el descompte per cada [t][iva]
		foreach ($index_relacio['T'] as $k=>$v){
			$nou=round($v*(1-($index_relacio['discount']/100)),2);
			$index_relacio['T'][$k]=$nou;
			$index_relacio['Total']=$nou+$index_relacio['Total'];
			$index_relacio['IVA'][$k]=$nou-round($nou/(1+($k/100)),2);
			$index_relacio['B'][$k]=$nou-$index_relacio['IVA'][$k];
			$index_relacio['Base']=$index_relacio['Base']+$index_relacio['B'][$k];
		}
	}
	else{
		$index_relacio['Total']=0;
		$index_relacio['Base']=0;
		//fer el descompte per cada b[iva]
		foreach ($index_relacio['B'] as $k=>$v){
			$nou=round($v*(1-($index_relacio['discount']/100)),2);
			$index_relacio['B'][$k]=$nou;
			$index_relacio['IVA'][$k]=round($nou*($k/100),2);
			$index_relacio['Base']=$index_relacio['Base']+$nou;
			if($index_relacio['Empresa']['sell_wholesale']=='1'){
            			$tax_ec=mysql1r("SELECT equivalency_charge $FROMactive_lines and IVA='$k'",$link);
				$index_relacio['EC'][$tax_ec]=round($nou*($tax_ec/100),2);
			}
			else unset($index_relacio['EC']);//si no venc al major no puc cobrar req.
			$index_relacio['T'][$k]=$nou+$index_relacio['IVA'][$k]+$index_relacio['EC'][$tax_ec];
			$index_relacio['Total']=$index_relacio['T'][$k]+$index_relacio['Total'];
		}
	}
if(is_array($index_relacio['active_lines']))sort($index_relacio['active_lines']);//força el vector de active_lines a començar per 0
$index_relacio['tipus_relacio']=$link->query("SELECT * from tipus_relacio where
	ID='{$index_relacio['ID_tipus_relacio']}'")->fetch_assoc();
return $index_relacio;
}
function asigna_relacio_per_telefon($r,$tel,$ID_Empresa,$year,$link,$debug=FALSE){
	$a=false;
	if($debug)pre($r);
	$ID_Client=mysql1r("SELECT ID_Client FROM Clients_phone WHERE Value='$tel'  ",$link,$debug);
	if($ID_Client!=''){
        if($debug)echo 'tinc client';
		$nouclient=$link->query("select * from Clients where ID='$ID_Client' AND bool_Active=1")->fetch_assoc()or mysqli.error() ;
// 		if($ID_Client!=$nouclient['ID'])$a=false;
		$FROMindex_relacio="FROM {$year}_Empresa_{$ID_Empresa}_index_relacio WHERE uuid='{$r['uuid']}'";
		$FROM="FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID_tipus_relacio='{$r['ID_tipus_relacio']}' and ID='{$r['ID']}' and line>0 ";
		if($debug)echo"SELECT * $FROMindex_relacio";
		$index_relacio=$link->query("SELECT * $FROMindex_relacio")->fetch_assoc();
		$update_index="UPDATE `{$year}_Empresa_{$ID_Empresa}_index_relacio` SET
		`ID_client` = '$ID_Client',
		`tax_detall` = '{$nouclient['bool_tax_detall']}',
		`equivalency_charge` = '{$nouclient['bool_equivalency_charge']}',
		`fiscal_name` = '{$nouclient['Value']}',
		`NIF` = '{$nouclient['NIF']}',
		`fiscal_address` = '{$nouclient['fiscal_address']}',
		`comercial_name` = '{$nouclient['comercial_name']}',
		`deliver_address` = '{$nouclient['deliver_address']}',
		`phone` = '$tel',
		`email` = '{$nouclient['email']}',
        	`Poblacio`='{$nouclient['Poblacio']}'
		WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}';";
		$a=true;
	}
	else { if($debug)echo' telefon anonim';
	if($tel!=$r['phone']){
		$update_index="UPDATE `{$year}_Empresa_{$ID_Empresa}_index_relacio` SET
		`phone` = '$tel'
		WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}';";
		$a=true;
		}
    }
	if($debug)pre($update_index);
	if($a){
		$a=$link->query($update_index);
		if(!$a)echo"ha fallat $update_index";
	}
	if(($a)&&(($ID_Client!=''))) {
		$tarifa=mysql1r("SELECT column_name from tarifa where ID='{$nouclient['id_tarifa']}'",$link,$debug);
		$qllista="SELECT * $FROM ORDER BY line";
		if($llista=$link->query($qllista)){
			while ($f = $llista->fetch_assoc()){
				if($f['units']>=1){
					if($nouclient['id_tarifa']!=10){// sino es detall les altres tarifes venen sense iva
						$noupreu=mysql1r("SELECT $tarifa FROM tarifes where ID='{$f['ID_product']}' AND ultimpreu='1'",$link,$debug);
						$ulinea="update `{$year}_Empresa_{$ID_Empresa}_line` SET cost='$noupreu',`discount`='0'
						WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' AND `line`='{$f['line']}';";
						if($debug)pre($ulinea);
						$a=$link->query($ulinea);
						if(!$a)break;
					}
				}
				if($nouclient['bool_equivalency_charge']==1){
					//aplico recàrreg a cada línea
					$id_iva=mysql1r("SELECT ID from IVA where `Value`='{$f['IVA']}'",$link);
					$rec=mysql1r("Select TAX FROM `equivalency_charge`  WHERE `ID_iva` = '$id_iva'",$link,true);
					$ulinea="update `{$year}_Empresa_{$ID_Empresa}_line` SET EQUIVALENCY_CHARGE='$rec'
					WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' AND `line`='{$f['line']}';";
					if($debug)pre($ulinea);
					$a=$link->query($ulinea);
					if(!$a)break;
				}
			}
		}
	}
	return $a;
}
function pdf($r){
	global $decimalsing,$thousandsing,$link,$prefixrelacio;
//  pre($r);
	$idrelacio="{$r['tipus_relacio']['Value']}&{$r['Empresa']['ID']}.{$r['ID']}";
	$data=latindate($r['data']);
	$nom_client=urldecode($r['fiscal_name']);
	if($r['deliver_address']='')$direccio.="\\\\ Deliver Address: \\\\".urldecode($r['deliver_address']);
	$direccio=urldecode($r['fiscal_address']);
	$poblacio=urldecode($r['Poblacio']);
	$pagat=mysql1r("Select Value from Pagament where `ID`='{$r['ID_Pagament']}'",$link);
	$espaidescripcio=110;
	if($r['tax_detall']==0){
		$espaidescripcio=$espaidescripcio-14;
		$ivainclos='IVA inclós';
	}
	else {
		$espaidescripcio="90";
		$ivainclos='sense IVA&IVA';
		$coliva='r|';
	}
	 if($r['discountinline']== 1)$espaidescripcio=$espaidescripcio-5;
	$linees=0;
	$discount=$r['discount']=='0'?'':'Descompte';
	 if(($r['Empresa']['sell_wholesale']=='1')&&($r['equivalency_charge'] == 1)){
		$prec='\%r. equivalència';
		$rec='recàrrec eq.';
	 }
	$peu='\hline
	Base&\%IVA&IVA&'.$prec.'&'.$rec.'&'.$discount.'&T.'.$r['tipus_relacio']['Value'].'\\\\
	\hline';
	// 	pre($r['IVA']);
	if($r['Empresa']['sell_wholesale']==0)$r['equivalency_charge'] = 0;
	foreach ($r['IVA'] AS $iva => $tiva){
			 $base='\raggedleft{'.number_format($r['B'][$iva],2,$decimalsing,$thousandsing)."€}";
			 $fiva='\raggedleft{'.number_format_trim($iva,2,$decimalsing,$thousandsing).'\%}';
		     	 $ftiva='\raggedleft{'.number_format($tiva,2,$decimalsing,$thousandsing)."€}";
			 if(($r['equivalency_charge'] == 0)){
				 $lb[$iva]="$base&$fiva&{$ftiva}&&&&".'\\\\';
			 }
			 else{
				 	$lrec=0;
				 foreach($r['EC'] AS $rec=>$trec){
					 if($linees==$lrec){
						 	$frec='\raggedleft{'.number_format_trim($rec,2,$decimalsing,$thousandsing).'\%}';
							$ftrec='\raggedleft{'.number_format($trec,2,$decimalsing,$thousandsing)."€}";
						 	$lb[$iva]="$base&$fiva&{$ftiva}&$frec&$ftrec&&".'\\\\';
					 }
					 $lrec++;
				}
			 }
// 			 echo"soc fent el peu aqui $iva, $ftiva ".$lb[$iva].brr();
			 $ultimiva=$iva;
			 $linees++;
		 }
// 		echo 'pagat;'.$pagat;
		$base='\raggedleft{'.number_format($r['B'][$ultimiva],2,$decimalsing,$thousandsing)."€}";
		$iva='\raggedleft{'.number_format_trim($ultimiva,2,$decimalsing,$thousandsing).'\%}';
		$tiva='\raggedleft{'.number_format($r['IVA'][$ultimiva],2,$decimalsing,$thousandsing)."€}";
		$total='\raggedleft{'.number_format($r['Total'],2,$decimalsing,$thousandsing)."€}";
		$discount=$r['discount']=='0'?'':'\raggedleft{'.number_format_trim($r['discount'],2,$decimalsing,$thousandsing).'\%}';
		//\multirow[〈vpos〉]{〈nrows〉}[〈bigstruts〉]{〈width〉}[〈vmove〉]{〈text〉
		//\multirow[t]{5}{*}[-\shiftdown]{\Huge\bfseries B}
// 		 if($r['equivalency_charge'] == 0)
		if($total>999)$lb[$ultimiva]=$base.'&'.$iva.'&'.$tiva.'&'.$frec.'&'.$ftrec.'&'.$discount.'&\multirow[r]{'.$linees.'}{*}{}{'.$total.'}\\\\';
	 	else $lb[$ultimiva]=$base.'&'.$iva.'&'.$tiva.'&'.$frec.'&'.$ftrec.'&'.$discount.'&\multirow[r]{'.$linees.'}{*}{}{\Large '.$total.'}\\\\';
// 		else{//amb recàrreg
// 			$lb[$ultimiva]=$base.'&'.$iva.'&'.$tiva.'&$frec&&'.$discount.'&\multirow[r]{'.$linees.'}{*}{}{\Large '.$total.'}\\\\';
// 		}
//  		pre($lb);
		foreach ($lb AS $linea){
			$peu.=$linea.'
			';
		}
		$peu.='
		\hline';
	$tex='\documentclass[a4paper,10pt]{article}
\usepackage[utf8]{inputenc}
\usepackage{eurosym}
\usepackage{hyperref}
\hypersetup{
    colorlinks=true,
    linkcolor=blue,
    filecolor=magenta,
    urlcolor=cyan,
    pdftitle={'."{$r['tipus_relacio']['Value']}&{$r['Empresa']['ID']}.{$r['ID']} {$r['Empresa']['Value']}".'},
    pdfauthor={'.$r['Empresa']['Value'].'}
}
\DeclareUnicodeCharacter{20AC}{\euro}
\usepackage[spanish]{babel}
\addtolength{\voffset}{-1in}%desplaçament vertical
\setlength{\headheight}{50mm}
\addtolength{\hoffset}{-1.2in}%per defecte esta desplaçat 25.4mm (1 in)
\setlength{\textwidth}{18cm}
%\setlength{\textheight{16cm}}
%marges
%\setlength{\topmargin{15mm}}


%opening
\title{'.$idrelacio.' }
\author{'.$r['Empresa']['Value'].'}
%\usepackage{fontspec}
\usepackage{fancyhdr}
\usepackage{longtable}
\usepackage{graphics}
\usepackage{multirow}
\usepackage{array}
\newcolumntype{L}[1]{>{\raggedright\let\newline\\\arraybackslash\hspace{0pt}}m{#1}}
\newcolumntype{C}[1]{>{\centering\let\newline\\\arraybackslash\hspace{0pt}}m{#1}}
\newcolumntype{R}[1]{>{\raggedleft\let\newline\\hspace{0pt}}m{#1}}
\pagestyle{fancy}%format que permet no estar al estandar article de latex
\begin{document}
\lhead{
	\Huge '.$r['Empresa']['Value'].' \normalsize \\\\
	'.$r['Empresa']['Direccio'].'\\\\
    '.$r['Empresa']['Poblacio'].'\\\\
	NIF '.$r['Empresa']['NIF'].'\\\\
	Tel '.$r['Empresa']['Phone'].'\\\\
	\href{mailto:'.$r['Empresa']['email'].'}{'.$r['Empresa']['email'].'}\\\\
	\vspace{2em}
	\begin{tabular}{|l|l|}
		\hline
		'.$idrelacio.'\\\\
		Data&'.$data.' 	\\\\
	\hline
	\end{tabular}
	\\
	\hspace{35ex}%\Huge \ '.$r['tipus_relacio']['Value'].'
	\normalsize 
	}
\chead{}
\rhead{
	\rotatebox{12}{
		\scalebox{2}[4]{'.$r['tipus_relacio']['Value'].'}
		}
		\\\\
	\begin{tabular}{|p{8.5cm}|}
		\hline
		'.$nom_client.'\\\\
		NIF:'.$r['NIF'].' \\\\
		'.$direccio.'\\\\
		'.$poblacio.'\\\\
		Telef: '.$r['phone'].' \\\\
		\hline
  	\end{tabular}\\\\
	\vspace{2em}
	}

\begin{longtable}{|p{1cm}|p{'.$espaidescripcio.'mm}|r|r|r|'.$coliva.'}
	\hline
	Codi&Descripció\hspace{'.$espaidescripcio.'mm}&Quantitat&Preu '.$ivainclos.'&Import\\\\
	\hline
	\endhead
';
	foreach ($r['active_lines'] as $v){
		$tex.=str_replace('#','\#',str_replace('%','\%','\raggedleft{'.$v['ID_product'].'}&'.str_replace('&','\&',$v['urlde'])));
		if($v['lotnumber']!='')$tex.=str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%'," Lot: ".urldecode($v['lotnumber'])))));
		$tex.="&";
		if($v['bulk']==0)$tex.=number_format_trim($v['units'],3,$decimalsing,$thousandsing);
		else $tex.=number_format($v['units'],3,$decimalsing,$thousandsing);
		$tex.=$v['u']."&".number_format($v['cost'],2,$decimalsing,$thousandsing)."€/{$v['u']}";
		if($v['discount']>0)$tex.=' -'.number_format_trim($v['discount'],2,$decimalsing,$thousandsing).'\% ';
		if($r['tax_detall']==1){
			$tex.='&'.number_format_trim($v['IVA'],2,$decimalsing,$thousandsing).'\% ';
			$tex.="&".number_format($v['Base'],2,$decimalsing,$thousandsing)."€".'\\\\';
		}
		else $tex.="&".number_format($v['tline'],2,$decimalsing,$thousandsing)."€".'\\\\';
	}
	$tex.='
    \hline\end{longtable}
%\vspace{1cm}
\begin{table}[b]
	\begin{tabular}{|p{1.9cm}|p{1.8cm}|p{2cm}|p{2.6cm}|p{2.2cm}|p{1.9cm}||p{2cm}|}
		'.$peu.'
	\end{tabular}
\end{table}
\lfoot{Línees: '.$r['activelines'].'}
\cfoot{Consulteu condicions de venda a \href{https://www.tulsa.eu/condicions}{www.tulsa.eu/condicions}}
\rfoot{'.$pagat.'}
\end{document}
';
// 	pre($tex);
	//$nf=$r['uuid'].'.tex';
	//exec ("rm {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$r['uuid']}.*  -f ");
	//exec ("rm {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/*  -f ");
	// unlink ("pdf/*");//no feia res per aixo l'he comentat
 	//$f=fopen("{$_SERVER['DOCUMENT_ROOT']}/scale/pdf/".$nf,'w')or die ;
	//fwrite($f, $tex);
	//fclose($f);
	//creo el pdf
	//crea_pdf($nf);
	//guardo el tex a la base de dades per crear la factura amb el cron
	$tex=urlencode($tex);
	if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}pdf' ;",$link)){
		$link->query("CREATE TABLE `{$prefixrelacio}pdf` LIKE `meta_pdf`;");
	}
	$link->query("DELETE FROM `{$prefixrelacio}pdf` WHERE `uuid` = '{$r['uuid']}'");
	$i="INSERT INTO `{$prefixrelacio}pdf` (`ID`, `ID_tipus_relacio`,  `uuid`,`fiscal_name`,`phone`,`email`, `LaTeX`,  `datetime_creation`)
	VALUES ('{$r['ID']}', '{$r['ID_tipus_relacio']}',  '{$r['uuid']}','{$r['fiscal_name']}', '{$r['phone']}','{$r['email']}','$tex', now());";
    //pre($r);
	$link->query($i)or die($i.brr().$link->error);
}
function controlDigit($ean){
  $ean= substr($ean,0,12);
  $par=0;
  $impar=0;
  $first=1;

  // Empezamos por el final
  for ($i=strlen($ean)-1; $i>=0; $i--){
    if($first%2 == 0){
      $par += $ean[$i];
    }else{
      $impar += $ean[$i]*3;
    }
    $first++;
  }
  $control = ($par+$impar)%10;
  if($control > 0){$control = 10 - $control;}
  return $control;
}
function legacyscale($codi){
//     comprovo el codi entrat
//     echo $codi;br();echo strlen($codi); br();echo controldigit($codi);br();
	global $prefixrelacio,$link,$scale_uri;
    if (strlen($codi)==13){
		$FROMindex_relacio="FROM {$prefixrelacio}index_relacio";// where ID_tipus_relacio=3";
		$qva="SELECT fiscal_name $FROMindex_relacio where `data` = curdate() AND ID_tipus_relacio=9 and fiscal_name=$codi";
		if((substr($codi,-1,1) == substr(controldigit($codi),-1,1))&&(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio' ;",$link,true)))return true;//si no existeix la taula que la creara el programa a partir d'aquest true
		if ($codi!=mysql1r($qva,$link)){
           		 if(substr($codi,0,1)==2)return( substr($codi,-1,1) == substr(controldigit($codi),-1,1));
        		}
			else {
				estil_capcal(false);
				br();
				br();
				a($scale_uri,h1r("codi ja entrat"));die;
			}
            return false;
        }
/*    else {
        // miro si esta a ametlles per si es del major
        if (($codi!=mysql1r("select codi_factura from caixa where codi_factura=$codi and hidden_now >curdate()-1 ",$link2))|| $codi==0)
            return false;
        else return true;
    }*/
}
function legacyinsert($codi){
	global $ID_tipus_relacio_legacy,$prefixrelacio,$link;
	$valor=intval(substr($codi,7,5));
    $ID_venedor=substr($codi,1,2);
    $tiquet=substr($codi,3,4);
	if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio' ;",$link)){
		$link->query("CREATE TABLE `{$prefixrelacio}index_relacio` LIKE `meta_index_relacio`;");
	}if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}line';",$link)){
		$link->query("CREATE TABLE `{$prefixrelacio}line` LIKE `meta_line`;");
	}
	$ID_relacio=mysql1r("select MAX(ID)+1 from `{$prefixrelacio}index_relacio`  where
		id_tipus_relacio='$ID_tipus_relacio_legacy'",$link); 			
	if(!$ID_relacio){//aixo només pasa un cop l'any o per instalació nova se suposa
		$ID_relacio=1;
	}
	$m['table']=$prefixrelacio.'line';
	$m['ID']=$ID_relacio;
	$m['do']='new';
	$m["{$prefixrelacio}line"]['ID_tipus_relacio']=$ID_tipus_relacio_legacy;
	$m["{$prefixrelacio}line"]['line']=1;
	$m["{$prefixrelacio}line"]['ID_product']=1;
	$m["{$prefixrelacio}line"]['description']='legacy';
	$m["{$prefixrelacio}line"]['units']=1;
	$m["{$prefixrelacio}line"]['bulk']=1;
	$m["{$prefixrelacio}line"]['cost']=$valor/100;
	$m["{$prefixrelacio}line"]['IVA']='0';
	$m["{$prefixrelacio}line"]['EQUIVALENCY_CHARGE']=0;
	$m2['table']=$prefixrelacio.'index_relacio';
	$m2['ID']=$ID_relacio;
	$m2['do']='new';
	$d['ID_client']=1;
	$d['ID_Venedor']=$ID_venedor;
	$d['ID_tipus_relacio']=$ID_tipus_relacio_legacy;
	$d['tax_detall']=0;
	$d['fiscal_name']=$codi;
	$d['ID_Pagament']=2;
	$d['relacio_oberta']=1;
	$d['data']['mday']=date('j');
	$d['data']['mon']=date('n');
	$d['data']['year']=date('Y');
	$d['uuid']=uniqid($ID_Empresa);
	$m2[$m2['table']]=$d;
	new_update($m2,$link);
	new_update($m,$link);

}
//final bascula heredada
//
function llistarelacions($e,$my){
	//listo relacions 
	global $w,$h,$decimalsing,$thousandsing;
	if ($_REQUEST['ID_Pagament']>0)$and_tipus_pagament="and `ID_Pagament`='{$_REQUEST['ID_Pagament']}'";
	if ($_REQUEST['ID_client']>0)$and_client="and `ID_client`='{$_REQUEST['ID_client']}'";
	$prefixrelacio="{$_REQUEST['anyinici']}_Empresa_{$e['ID']}_";
	$q="SELECT * FROM {$prefixrelacio}index_relacio where ( `data` between '{$_REQUEST['anyinici']}/{$_REQUEST['mesinici']}/{$_REQUEST['diainici']}' AND '{$_REQUEST['anyinici']}/{$_REQUEST['mesfi']}/{$_REQUEST['diafi']}') AND `ID_tipus_relacio`='{$_REQUEST['ID_tipus_relacio']}' $and_tipus_pagament $and_client ORDER BY `ID` DESC ";
	//debugtelegram($q);
	if(isset($_REQUEST['ID_tipus_relacio'])){
		//tenim llista!
		//pre($_SESSION);
		unset($_SESSION['llistaclients']);
		$_SESSION['llistaclients']['ID'][0]=0;
		$_SESSION['llistaclients']['fiscal_name'][0]="Tots els clients";
		$l=$my->query($q) or die( $q.$my->error);
		if($l->num_rows>=1){
			//echo $q;
			while($f=$l->fetch_assoc()){
				unset($r);
				$r=detallrelacio($f['uuid'],$_SESSION['Empresa']['ID'],$_REQUEST['anyinici'],$my);
				if (!in_array($r['ID_client'],$_SESSION['llistaclients']['ID'])){ 
					$_SESSION['llistaclients']['ID'][]=$r['ID_client'];
					$_SESSION['llistaclients']['fiscal_name'][]=urldecode($r['fiscal_name']);
				}
				$i=0;
				//do{
				//	$i++;
				//}while (is_array($trelacions[(($r['activelines']*10000)+$i)]));
				////}while (is_array($trelacions[(($r['Total']*10000)+$i)]));//(is_array($trelacions[(($r['Total']*10000)+$i)]));
				//$trelacions[(($r['activelines']*10000)+$i)]=$r;
				//$trelacions[(($r['Total']*10000)+$i)]=$r;
				$trelacions[]=$r;
				$t['cost']=$r['Total']+$t['cost'];
				$t['cost_price']=$r['Cost']+$t['cost_price'];
				$t[$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]]=$t[$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]]+1;
			}
			if(is_array($_SESSION['llistaclients'])){
				global $fontsize,$height;
				select2llistes_js("ID_client",$_SESSION['llistaclients']['ID'],$_SESSION['llistaclients']['fiscal_name'],
				$_REQUEST['ID_client']?$_REQUEST['ID_client']:0,"no",false,
				"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
			}	
			tdiv();
			tform();

			divclass("caixa","style =\"background:#ACAB00; position: relative;top:3px; text-align: center;height: unset;width:unset; padding:1%;display: inline-block; \"");
			//if($_REQUEST['resum']){
				divclass("caixadescripcio","style =\"background:#ACAB00;text-align: justify; position: relative; top: 5px; display: inline-block; left:0px; padding:1%; width:98%; font-size:30px;height: unset;\"");//width:".($w<400?($w):350)."px;
				echo "En aquest periode les {$l->num_rows} relacions \"{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}\" estan valorades en {$t['cost']} i el cost de compra ha sigut (aprox) de {$t['cost_price']} deixant un marge brut de ".(is_numeric($t['cost_price'])?round($t['cost']-$t['cost_price'],2):"NaN")."€"; 
				tdiv();
			//}
				unset($r);
			//ksort($trelacions);
			//ksort($_SESSION['llistaclients']['ID']);
			//ksort($_SESSION['llistaclients']['fiscal_name']);
			//debugtelegram($_SESSION['llistaclients']);
			$trelacions=array_reverse($trelacions);	
			//pre($trelacions);

			foreach ($trelacions as $k=>$r){
			//pre($r);
				//divclass("caixadescripcio","style =\"background:#ACAB00; position:relative; top:110px; left:5px; width:".($w)."px; height:815px; font-size:33px;padding:1%;\"");
				divclass("caixadescripcio","style =\"background:#ACAB00; text-align: center; position: relative; top: 5px; display: inline-block; left:0px; width:".($w<400?($w):350)."px;".($r['deletedlines']>1?" color:red;":"")." font-size:22px;height: unset;\"");//width:".($w<420?($w):420)."px;
				//echo $k;
				ainf("./?do=edit&uuid={$r['uuid']}&ID_Empresa={$_SESSION['Empresa']['ID']}&year={$_REQUEST['anyinici']}","✍️");
				ainf("../scale/tiquet.php?uuid={$r['uuid']}&ID_Empresa={$_SESSION['Empresa']['ID']}&year={$_REQUEST['anyinici']}&close=true","🧾");
				if($r['phone']!=""){
					ainf("./?do=pdf&uuid={$r['uuid']}&ID_Empresa={$_SESSION['Empresa']['ID']}&year={$_REQUEST['anyinici']}","🖨️");					  ainf("tel:{$r['phone']}","📲 {$r['phone']}");
					$body=urlencode("https://ametlles.tulsa.eu/pdf/{$r['uuid']}.pdf");
					$subject=str_replace(" ","%20","Enviem enllaç a la factura de la data {$r['data']} ");
					ainf("https://api.whatsapp.com/send?phone=&text=$subject$body",imgr("../scale/img/whatsapp.svg","22px","22px"));
					if(isset($r['email'])){
						ainf("mailto:{$r['email']}?subject=$subject&body=$body","📧");
					}
				}
				br();
				echo urldecode($r['fiscal_name'])." {$e['ID']}.{$r['ID']}";
				br();
				echo "({$r['activelines']}) ({$r['deletedlines']})". number_format($r['Total'],2,$decimalsing,$thousandsing)."€ @";
				//br();
				//echo $r['active_lines'][1]['hidden_now'].($r['activelines']>1?"-".substr($r['active_lines'][$r['activelines']]['hidden_now'],11):"");
				//br();
				echo substr($r['active_lines'][0]['hidden_now'],5,11).($r['activelines']>1?"-".(strtotime($r['active_lines'][($r['activelines']-1)]['hidden_now'])-strtotime($r['active_lines'][0]['hidden_now']))."s":"");

				//pre($r);
				
				tdiv();

			}
			//array_multisort($trelacions,SORT_DESC,'Total');
			//sort($trelacions);
			//pre($trelacions);
			tdiv();
		}
		else{ 
			tdiv();
			tform();
			h2("No hi ha {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]} en aquest periode");
			//pre($_REQUEST);
		}

	}
	return($t);
	//pre($e);
}
?>
