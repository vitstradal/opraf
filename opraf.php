<?php

// configuration file
require 'config.php';

function get_help_doc()
{
	return  <<<EOL
Nástroj pro korektury převzatý od Pikomatu.

Základní používání
* Odkaz ls ukáže seznam všech PDF dostupných pro korektury.
  Soubory jsou řazeny dle data nahrání do systému, nejnovější nahoře.
* Odkaz help zobrazí tuto nápovědu.

* Po vybrání příslušného souboru opravu provedete kliknutím do textu
  a zadáním opravy. Ctrl+Enter odešle formulář s korekturou.
* Korektury (i cizí!) lze mazat, upravovat, či označit jako opravené
  (viz tlačítka u korektury).
* Pokud není předvyplněno správné jméno (mělo by být), opravte ho.

Přidání nového PDF
* Stačí nahrát soubor do /akce/MaM/WWW/opraf/pdf a následně ho normálním
  způsobem zobrazit. Místo zobrazení souboru se objeví tlačítko na
  generování obrázků.

Autorem nástroje je Viťas, vitas(šnek)matfyz(tečka)cz
a zdrojové kódy jsou dostupné na GitHubu na adrese
https://github.com/vitstradal/opraf.

Úpravy pro potřeby M&M provedl Kuba, na něj můžete
směřovat i případné otázky.


BUGS
* misto kliknuti a pak pointer neni to same (nekolik pixelu mimo)
* design error: kolize png obrazku: letak.pdf (vice str) a letak-1.pdf (1 stranka)
* CSRF, kriminalnici jsou vsude.
* jak resit aktualizovani pdf? nejlepe jinym nazvem .pdf

EOL;
} /* get_help_doc() */

#image names

$icons = array(
	'undo' => 'imgs/undo.png',
	'done' => 'imgs/check.png',
	'dele' => 'imgs/delete.png',
	'edit' => 'imgs/edit.png',
	'link' => 'imgs/link.png',
	'next' => 'imgs/next.png',
	'nextgr' => 'imgs/next-gr.png',
	);

# database hanlder
$db = null;

# current pdf file name (from param 'pdf')
$pdf_file = null;

// init db, dispach post, or get
function init()
{
	myconnect();
	if( count($_POST) > 0 ) { 
		do_post();
		redir_to_get();
	}

	# get ###################################################

	global $pdf_file;
	$pdf_file = get_get('pdf');

	return true;
}


// connect to database
function myconnect()
{
	global $db;
	global $db_conn;
	try {
		$db = new PDO($db_conn);
	}
	catch(PDOException $e)
	{
		ee($e->getMessage());
	}
}

function get_pdf_file_post()
{
	global $pdf_file;
	$pdf_file = get_post('pdf');
}
// after post handled, redirect to get
function redir_to_get()
{
	global $pdf_file;
	get_pdf_file_post();

	$scroll = get_post('scroll');

	$header = 'Location: opraf.php?pdf=' . urlencode($pdf_file)  ;

	if( $scroll !== null ) {
		$header .= "&scroll=" . urlencode($scroll);
	}

	header( $header);
	die();
}

// get param from _GET
function get_get($var, $default=null)
{
	if(isset($_GET[$var])) {
		return $_GET[$var];
	}
	return $default;
}

// get param from _POST
function get_post($var, $default=null)
{
	if(isset($_POST[$var])) {
		return $_POST[$var];
	}
	return $default;
}


// hande POST
function do_post()
{

	global $pdf_file;
	global $icons;

	get_pdf_file_post();
	$action = get_post('action', 'none');

	//IE, posila obsah butonu, wtfff?
	if( false !== strpos($action, $icons['done'])) { $action = 'done';}
	if( false !== strpos($action, $icons['undo'])) { $action = 'undone';}
	if( false !== strpos($action, $icons['dele'])) { $action = 'del';}

	switch($action) {
	case 'delall':
		do_delall();
		break;
	case 'del':
		do_del();
		break;
	case 'done':
		do_done(true);
		break;
	case 'undone':
		do_done(false);
		break;
	case 'regen':
		regen_pdf_img();
		break;
	case 'regen2':
		regen2_pdf_img();
		break;
	case 'update':
		do_update_correction();
		break;
	default:
		do_insert_correction();
	}
}

// regenerate imgs from .pdf using convert
function regen_pdf_img()
{
	global $pdf_file;
	get_pdf_file_post();
	global $pdf_file;
	global $pdf_dir;
	global $img_dir;

	render_short_begin();
	echo "<pre>Generuji obrázky, čekej...\n";

	flush();
	assert(preg_match('/^[-a-z_A-Z0-9\.]+$/', $pdf_file));

	$pdf_base = strip_ext($pdf_file);
	remove_images(get_images($pdf_base));

	$pdf_path = "$pdf_dir/$pdf_file";

	if( !is_file($pdf_path) ) { 
		ee("CHYBA: Soubor ($pdf_path) neexistuje.");
		die('');
	}

	$img_path = "$img_dir/$pdf_base.png";
	$cmd = "PATH=/usr/bin convert -density 180x180 -geometry 1024x1448 \"$pdf_path\" \"$img_path\"";
	ee("\t$cmd\n");
	system($cmd, $rc);
	if( $rc != 0) { 
		render_regen_form($pdf_file, "CHYBA: Prikaz vratil chybovy kod $rc. Zkus:", false);
		exit(0);
	}

	$cmd = "chmod 664 \"$img_dir/$pdf_base\"*";
	ee("\t$cmd\n");
	system($cmd, $rc);
	if($rc != 0){
		echo "\n CHYBA: Vygenerovanym souborum se nepodarilo nastavit potrebna prava.";
		exit(0);
	}
	
	echo "hotovo.\n</pre><br>";
	echo "Pokračovaní <a href=\"?pdf=".escape($pdf_file)."\">zde</a>.";

    render_short_end();
	exit(0);

}
function render_regen_pdf_img_done($cmd, $pdf_file)
{?>
	
	hotovo (<?php ee($cmd)?></br>
	Pokračovaní <a href='?pdf=<?php ee($pdf_file)?>'>zde</a>.
<?php }

function render_regen_pdf_img_init()
{?>
	<pre>Generuji obrázky, čekej...
<?php }

function render_short_begin() {
        echo("<html><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'></head><body>");
}
function render_short_end() {
        echo("</body></html>");
}

function regen2_pdf_img()
{
	global $pdf_file;
	global $pdf_dir;
	global $img_dir;

	get_pdf_file_post();
	render_regen_pdf_img_init();
        render_short_begin();

	flush();
	assert(preg_match('/^[-a-z_A-Z0-9\.]+$/', $pdf_file));

	$pdf_base = strip_ext($pdf_file);
	remove_images(get_images($pdf_base));

	$pdf_path = "$pdf_dir/$pdf_file";

	if( !is_file($pdf_path) ) { 
		ee("file ($pdf_path) not exists");
		die('');
	}
	$pageno = 0;
	while(true) { 
		$img_path = "$img_dir/$pdf_base-$pageno.png";
		$pdf_path_page = "${pdf_path}[$pageno]";
		$cmd = "convert -density 180x180 -geometry 1024x1448  \"$pdf_path_page\" \"$img_path\"";
		ee("$img_path\n");
		flush();
		system($cmd, $rc);
		if( $rc != 0) { 
			render_regen_pdf_img_done($cmd, $pdf_file);
                        render_short_end();
			die("");
		}
		$pageno++;
	}
	#die("");

}

// remove given images 
function remove_images($imgs)
{
	global $img_dir;
	foreach($imgs as $img) { 
		if( strpos($img, '/') != False ) { 
			ee("wrong img name: $img");
			die('');
		}
		unlink("$img_dir/$img");
	}
}

// del correction
function do_del()
{

	global $db;
	$id = substr(get_post('id'), 2);
	$sql = "DELETE FROM opravy WHERE id = " . $db->quote($id);
	$rc = $db->exec($sql);
	if( $rc == 0 ) {
		#print_r($db->errorInfo());
		ee("cannt del from db.'$sql'");
		die('');
	}
}
// del correction
function do_delall()
{
	global $db;
	$pdf = get_post('pdf');
	$yes = get_post('yes');

	if( $yes == 'on' ) { 
		$sql = "DELETE FROM opravy WHERE pdf = " . $db->quote($pdf);
		$rc = $db->exec($sql);
		if( $rc == 0 ) {
			#print_r($db->errorInfo());
			ee("cannt del from db.'$sql'");
			die('');
		}
	}
	else {
		die("cannt del all comments to '$pdf', check agreement checkbox!");
	}
}

// set correction status
function do_done($done)
{

	global $db;
	#echo "do_done: $done.";
	$new_status = $done ? 'DONE' : 'NONE';
	$id = substr(get_post('id'), 2);
	$sql = "UPDATE opravy SET status = " . $db->quote($new_status) .
		"  WHERE id = " . $db->quote($id);
	$rc = $db->exec($sql);
	#die("do_done($sql)$done:$rc");
	if( $rc == 0 ) {
		#print_r($db->errorInfo());
		ee("cannt update from db. '$sql'. "); 
		die('');
	}
}

// update correction
function do_update_correction()
{
	global $db;

	#$img_id = get_post('img-id', 'img-0');
	$id =     get_post('id', null);
	#$pdf =	  get_post('pdf');
	#$x =      get_post('x', 0);
	#$y =      get_post('y', 0);
	$txt =    get_post('txt', 'notxt');
	$au =     get_post('au', 'anonym');

	setcookie('author', $au);

	if( $id === null ){ 
		return;
	}

	$sql = 'UPDATE opravy SET ' .
			#'pdf = '   . $db->quote($pdf) . "," .
			#'img_id = '. $db->quote($img_id) . "," .
			#'x = '     . $db->quote($x) . "," .
			#'y = '     . $db->quote($y) . "," .
			'txt = '   . $db->quote($txt) . "," .
			'au = '    . $db->quote($au) .  " " .
			'WHERE id = ' . $db->quote($id) ;

	$rc = $db->exec($sql);
	if( $rc == 0 ) {
		#print_r($db->errorInfo());
		ee("cannt update db. '$sql'");
		die('');
	}
}

// insert new correction into dabase
function do_insert_correction()
{
	global $db;

	$img_id = get_post('img-id', 'img-0');
	$pdf =	  get_post('pdf');
	$x =      get_post('x', 0);
	$y =      get_post('y', 0);
	$txt =    get_post('txt', 'notxt');
	$au =     get_post('au', 'anonym');

	setcookie('author', $au);

	$sql = "INSERT INTO opravy (pdf, img_id, x, y, txt, au) VALUES (" .
			$db->quote($pdf) . "," .
			$db->quote($img_id) . "," .
			$db->quote($x) . "," .
			$db->quote($y) . "," .
			$db->quote($txt) . "," .
			$db->quote($au) . ")";

	$rc = $db->exec($sql);
	if( $rc == 0 ) {
		#print_r($db->errorInfo());
		ee("cannt insert into db. '$sql'");
		die('');
	}
}

// rid off .pdf
function strip_ext($file)
{
	return substr($file, 0, strrpos($file, '.')); 
}

// find image files, based on $pdf_base
// (letak.pdf -> letak-0.png, letak-1.png ...)
function get_images($pdf_base)
{
	global $img_dir;
	$ret = array();
	$img =  "$pdf_base.png";
	$img_path = "$img_dir/$img";

	if( is_file($img_path) ) { 
		$ret[] = $img; 
	}

	for($i = 0; true; $i++) { 
		$img =  "$pdf_base-$i.png";
		$img_uri_path = "$img_uri/$img";
		$img_path = "$img_dir/$img";
		if( !is_file($img_path) ) { 
			break;
		}
		$ret[] = $img;
	}
	return $ret;
}

// generate html with images
function render_images()
{
	#TODO
	global $pdf_file;
	global $img_dir;
	global $img_uri;
	$pdf_base = strip_ext($pdf_file);

	$imgs = get_images($pdf_base);
	if( count($imgs) == 0 ) { 
		render_regen_form($pdf_file, 'Obrázky je nutné nejdříve vygenerovat.');
		return;
	}
	render_check_update($pdf_base, $imgs[0]);

	foreach($imgs as $idx => $img) {
		$img_uri_path = "$img_uri/$img";
		$img_path = "$img_dir/$img";
		list($w, $h) = getimagesize($img_path);
		$id = "img-$idx";
		render_image($img_path, $id, $w, $h);
	}
	render_delall($pdf_file);
}
function render_delall($pdf_file)
{?>

	<form method="post">
	  <input type='hidden' name='action' value='delall'/>
	  <input type='submit' value='Smazat všechny komentáře'/>
	  <input type='hidden' name='pdf' value=<?php eeq($pdf_file)?>/>
	  <input type='checkbox' name='yes'/> Souhlasím se smazáním všech kometářů
	</form>
	<hr/>

<?php }
function render_image($img_path, $id, $w, $h)
{?>
	 <div class='imgdiv'><img width=<?php eeq($w)?> height=<?php eeq($h)?> onclick='img_click(this,event)' id=<?php eeq($id)?> src=<?php eeq($img_path)?>/></div><hr/>
<?php }

// check if .pdf is newer than first img
// if yes: display warning, and regen button
function render_check_update($pdf_base, $img_first)
{
	global $img_dir;
	global $pdf_dir;
	global $pdf_file;

	$pdf_path = "$pdf_dir/$pdf_file";
	$img0_path = "$img_dir/$img_first";
	if( !is_file($img0_path) ) {
		return;
	}
	if( !is_file($pdf_path) ) {
		return;
	}
	if( filemtime($pdf_path) > filemtime($img0_path) ) { 
		render_regen_form($pdf_file, "Obrázky jsou starší, než původní .pdf. Zkus je přegenerovat.");
	}
	
}

/**
 * 
 * silly template system
 * 
 * a) output is in render_*()
 * b) thruu ee() eeq() and ejs() 
 */

// escape to html
function escape($txt)
{
	return htmlspecialchars($txt, ENT_QUOTES,  "ISO-8859-1");
}

// echo html-escaped text
function ee($txt, $id = null) 
{
	if( $id !== null ) { 
		echo escape($txt[$id]);
		return;
	}
	echo escape($txt);
}
// echo html-escaped quote
function eeq($txt, $id = null) 
{
	if( $id !== null ) { 
		echo '"' . escape($txt[$id]) . '"';
		return;
	}
	echo "'" . escape($txt, $id) . "'";
}

// echo js-escaped object
function ejs($obj)
{
	echo json_encode($obj);
}

// generate html corrections section
function render_opravy()
{
	global $db;
	global $icons;
	global $pdf_file;
	$imgs = array();

	$sql = "SELECT * from opravy WHERE pdf = " . $db->quote($pdf_file);
	$opravy = array();
	$statistika = array();
	foreach( $db->query($sql) as $row) {
		$row['img_id_num'] = (int) substr($row['img_id'], 4);
		$opravy[] = $row;
		$statistika[$row['au']]++;
	}
	if( count($opravy) > 0 ) {
		arsort($statistika);

		$msg =  "Děkujeme opravovatelům: ";

		if( count($statistika) == 1 ) {
			$msg =  "Děkujeme: ";
		}
		$sep ='';
		$text ='';
		foreach($statistika as $au => $count) {
			$text .= "${sep}$au($count)";
			$sep = ", ";
		}
		render_statistika($msg, $text);
	}

	usort($opravy, 'opravy_cmp');
	for($i = 0; $i < count($opravy); $i++) {
		$row = $opravy[$i];
		$next_id =  $i + 1< count($opravy) ?  'op' . $opravy[$i+1]['id'] : null;
		$id = 'op' . $row['id'];
		$img_id = $row['img_id'];
		$imgs[$img_id][] = array($id, (int)$row['x'], (int)$row['y']);
		render_oprava($pdf_file, $id, $next_id, $pdf_file, $row, $icons);
	}
	render_opravy_script($imgs);
}


function render_opravy_script($imgs)
{?>
	<script>
	<?php  foreach($imgs as $img_id => $opravy): ?> 
	  place_comments_one_div(<?php ejs($img_id)?>, <?php ejs($opravy)?>);
	<?php  endforeach ?>
	</script>
<?php }

function render_statistika($pre_msg, $text)
{?>
	<?php ee($pre_msg)?><?php ee($text)?>
	<hr>
<?php }

function render_oprava($pdf_file, $id, $next_id, $pdf_file, $row, $icons)
{?>
	<?php  if( $row['status'] == 'DONE' ) : ?>
	<div onclick='img_click(this,event)' id='<?php ee($id)?>-pointer' class='pointer-done'></div>
	 <div name=<?php eeq($id)?> id=<?php eeq($id)?> class='box-done' onmouseover='box_onmouseover(this,1)' onmouseout='box_onmouseout(this,1)' >
	 <?php  else: ?>
	<div onclick='img_click(this,event)' id='<?php ee($id)?>-pointer' class='pointer'></div>
	 <div name=<?php eeq($id)?> id=<?php eeq($id)?> class='box' onmouseover='box_onmouseover(this,0)' onmouseout='box_onmouseout(this,0)' >
	 <?php  endif ?> 
         
	 <b><?php ee($row, 'au')?></b>
	 <div class='float-right'>
	  <form  action='' onsubmit='save_scroll(this)' method='POST'>
	   <input type='hidden' name='pdf' value=<?php eeq($pdf_file)?>>
	   <input type='hidden' name='id' value=<?php eeq($id)?>>
	   <input type='hidden' name='scroll'>
	   <button type='submit' name='action' value='del' title='Smaž opravu' onclick='return confirm("Opravdu smazat korekturu?");'><img src=<?php eeq($icons,'dele')?>/></button>
         
	   <?php  if( $row['status'] == 'DONE' ) : ?>
	   <button type='submit' name='action' value='undone' title='Označ jako neopravené'><img src=<?php eeq($icons,'undo')?>/></button>
	   <?php  else: ?>
	   <button type='submit' name='action' value='done' title='Označ jako opravené'><img src=<?php eeq($icons,'done')?>/></button>
	   <?php  endif ?> 
         
	   <button type='button' onclick='box_edit(this);' title='Oprav opravu'><img src=<?php eeq($icons, 'edit')?>/></button>
	   <a href='#<?php ee($id)?>'><button type='button' title='Link na opravu'><img src=<?php eeq($icons, 'link')?>/></button></a>
	   <?php  if( $next_id ) : ?>
	    <a href='#<?php ee($next_id)?>'><img title='Další oprava' src=<?php eeq($icons, 'next')?>/></button></a>
	   <?php  else: ?>
	     <img title='Toto je poslední oprava' src=<?php eeq($icons, 'nextgr')?>/>
	   <?php  endif ?> 
	  </form>
	 </div> <?php /* float-right*/?>
	 <div id='<?php ee($id)?>-text'><?php ee($row, 'txt')?></div>
         
	</div><?php /* box-done|box */?>
<?php }

// prvky jsou radky ze selecty * from opravy
function opravy_cmp(&$a, &$b)
{
	$a_id = $a['img_id_num'];
	$b_id = $b['img_id_num'];
	if( $a_id != $b_id ) { return $a_id - $b_id; }
	$a_y = $a['y'];
	$b_y = $b['y'];
	if( $a_y  != $b_y ) { return $a_y - $b_y; }
	$a_x = $a['x'];
	$b_x = $b['x'];
	return $a_x - $b_x;
}

// gen javascript sectin which scrolls to page offset
// defined by scroll= param
function render_scroll($scroll)
{?>
	<?php  if( $scroll === null ) { return; } ?>
	<script>
	  window.scrollTo(0,<?php ejs((int)$scroll)?>);
	</script>

<?php }

// gen html documentation
function render_doc()
{
	echo'<pre>'.escape(get_help_doc()).'</pre>';
}


function render_ls_files()
{
	global $pdf_dir;
	$files = array();
	if( $dir = opendir($pdf_dir) ) {
    	while (($file = readdir($dir)) !== false)  {
        	if( substr($file, -4) != '.pdf' ) continue;
			$files[] = $file;
        }
		usort($files, function($a, $b) {
			 global $pdf_dir;
   			 return filemtime("$pdf_dir/$a") < filemtime("$pdf_dir/$b");
		});
        //sort($files);
    }
	return $files;
}

// gen dir list of .pdf files
function render_ls()
{?>
	<p>
	<?php  foreach(render_ls_files()  as $file): ?>
	  <a href='?pdf=<?php  ee($file) ?>'><?php ee($file)?></a><br/>
	<?php  endforeach  ?>
	</p>
<?php }

// generate html form which regenerate .pdf
function render_regen_form($pdf_file, $text, $small_pdf_button = true)
{?>
	<?php  ee($text)?>
 	<form method='POST'>
    	<input type=hidden name='pdf' value=<?php eeq($pdf_file)?>/>
	<?php if ($small_pdf_button): ?>  
		<button type='submit' name='action' value='regen'>Generovat obrázky</button>
	<?php endif?>
    	<!-- <button type='submit' name='action' value='regen2'>Generovat obrázky z velkých .pdf</button> -->
  	</form>
<?php }
#######################################################################
# main
if( ! init() ) {
	die("neco se pdfelalo\n");
}

$au = isset($_COOKIE['author'])? $_COOKIE['author'] : 'anonym';

render_html($pdf_file, $au);

function render_html($pdf_file, $au)
{?><html>
	<head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="stylesheet" type="text/css" media="screen, projection" href="opraf.css" />
	<script src="opraf.js"></script>
	<title>Korektury <?php  ee($pdf_file) ?></title>
	</head>
	<body> 

	<h1>Korektury <?php  ee($pdf_file) ?></h1>
	<i>Klikni na chybu, napiš komentář</i>  |
	<a href="?action=ls">ls</a> |
	<a href="?action=doc">help</a> |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|
	<a href="https://mam.mff.cuni.cz/">hlavní stránka</a> |
	<a href="https://mam.mff.cuni.cz/admin">admin</a> |
	<a href="https://mam.mff.cuni.cz/wiki">wiki</a> |
	<hr/>

	<div id="commform-div">
	<form action='' onsubmit='save_scroll(this)' id="commform" method="POST">
	  <input size="8" name="au" value="<?php  ee($au); ?>"/>
	  <input type=submit value="Oprav!"/>
	  <button type="button" onclick="close_commform()">Zavřít</button>
	  <br/>
	  <textarea onkeypress="textarea_onkey(event);" id="commform-text" cols=40 rows=10 name="txt"></textarea>
	  <br/>
	  <input type="hidden" size="3" name="pdf" value=<?php  eeq($pdf_file); ?>/>
	  <input type="hidden" size="3" id="commform-x" name="x"/>
	  <input type="hidden" size="3" id="commform-y" name="y"/>
	  <input type="hidden" size="3" id="commform-img-id" name="img-id"/>
	  <input type="hidden" size="3" id="commform-id" name="id"/>
	  <input type="hidden" size="3" id="commform-action" name="action"/>
	  <input type="hidden" size="3" id="commform-action" name="scroll"/>
	</form>
	</div>
	<?php  render_content() ?>
	<!-- Google analytics -->
	<script>
  	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
 	 m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
 	 })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

 	 ga('create', 'UA-44119587-2', 'auto');
 	 ga('send', 'pageview');

	</script>
</body> </html>
<?php }

function render_content()
{
	global $pdf_file;
	$action = get_get('action', 'none');
	if( $action == 'doc') {
		render_doc();
	}
	elseif( $pdf_file == null || $action == 'ls') {
		render_ls();
	}
	else{ 
		render_images();
		render_opravy();

		render_scroll(get_get('scroll'));

	}
}
