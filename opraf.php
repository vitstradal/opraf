<?
/*
 * configuration
 */
# where pdf files lives  
$pdf_dir = '..';

# where to store .png (must have write access)
$img_dir = 'tmp';

# where it is on web
$img_uri = 'tmp';

/*
 * end of configuration
 */
$pdf_file = 'letak.pdf';

/*
 * connection string
 */
$db_conn = "sqlite:db/opraf.db";

/*
 * opraf.db:
 *
$ sqlite3 db/opaf.db

CREATE TABLE opravy(
	id INTEGER PRIMARY KEY,
	pdf  TEXT,
	img_id TEXT,
	txt TEXT,
	status TEXT,
	au  TEXT,
	x INTEGER,
	y INTEGER
);

# set permissions 
$ chomd o+w db/ db/opraf.db

 *
 */

$help_doc = <<<EOL

Bastl pro přidávání poznámek k .pdf souborům v adresáři /provedouci/.

Před začátkem nebo pokud se .pdf aktalizovalo, je třeba vygenerovat obrázky
(tlačítko generuj obrázky).

<a href="?action=ls">ls</a> ukáže seznam všech .pdf v /provedouci/.

Oprava se provede kliknutím do textu, a zadání opravy.
Ctrl-Enter odešle formulář.
Zadejte svoji značku nebo jméno, ať v tom není zmatek. 

Poznámky se dají mazat, nebo označit jako zpracované (tlačítka del, done).

Dotazy, požadavky:

au: vitas(šnek)matfyz(tečka)cz

INSTALACE a CONFIGURACE (nezajimavé):

opraf/opraf.{php,js,css}
db/opraf.db sqlite databaze ve které jsou poznámky
tmp/ zde jsou obrázky stránek pdf.
imgs/ ikony.
db/ tmp/ musi mít zápis pro oth (nebo pro www-data).

configurace pomocí editace opraf.php:

# where pdf files lives  
\$pdf_dir = '..';

# where to store .png (must have write access)
\$img_dir = 'tmp';

# where it is on web
\$img_uri = 'tmp';

# database connection string
\$db_conn = "sqlite:db/opraf.db";

BUGS

* misto kliknuti a pak pointer neni to same (nekolik pixelu mimo)
* design error: kolize tmp obrazku: letak.pdf (vice str) a letak-1.pdf (1 stranka)
* CSRF, kriminalnici jsou vsude.
* jak resit aktualizovani pdf? nejlepe jinym nazvem .pdf

* na velkých .pdf (ročenka) se convert vysype. taky na fonts.pdf. trosku fixed
* znaky html se pri preeditaci preescapovavaji. fixed
* nefunguje v IE8, (testovano FF7, Op?, Chr14, adroid-browser), snad fixed (IE tfujtajbl!)
* kdyz se zmensi pocet stranek, nebo zvetsi z jedne stranky na vic: hodito vice stranek nez se ocekava, fixed
* pridavani komentaru v dolni polovine stranky, skoci na zacatek stranky. fixed
* nejde kliknout na místa která jsou 'uvnitř' vodící čáry. fixed.
* kdyz je .pdf kratke jen na jednu stranku, tak convert vygeneruje obrazek bez cisla,
  cele to nefunguje.  fixed.
* kdyz je hodne komentaru u konce stranky, tak prekryvaji komentare na dalsi strance, asi fixed.

WISH LIST -- Nenaplněná přání

* permalinky na opravy.


* velke soubory .pdf, done by hack,
* zmena velikosti, trosku done
* editovat existujicí komentar, done
* po najeti na opravu, ztucnit pointer, done
* o editaci, nebo zadání, zůstat na stejnem místě, done

EOL;

$img_undo = 'undo.png';
$img_done = 'check.png';
$img_dele = 'delete.png';
$img_edit = 'edit.png';

$db = null;

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
	global $pdf_file_esc;
	$pdf_file = get_get('pdf');
	$pdf_file_esc = escape($pdf_file);

	return true;
}


// connect to db
function myconnect()
{
	global $db;
	global $db_conn;
	try {
		$db = new PDO($db_conn);
	}
	catch(PDOException $e)
	{
		echo escape($e->getMessage());
	}
}
$pdf_file = null;

function get_pdf_file_post()
{
	global $pdf_file;
	global $pdf_file_esc;
	$pdf_file = get_post('pdf');
	$pdf_file_esc = escape($pdf_file);
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
	global $img_undo;
	global $img_done;
	global $img_dele;

	get_pdf_file_post();
	$action = get_post('action', 'none');

	//IE, posila obsah butonu, wtfff?
	if( false !== strpos($action, $img_done)) { $action = 'done';}
	if( false !== strpos($action, $img_undo)) { $action = 'undone';}
	if( false !== strpos($action, $img_dele)) { $action = 'del';}

	switch($action) {
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
	ee("<pre>Generuji obrázky, čekej...\n");
	flush();
	assert(preg_match('/^[-a-z_A-Z0-9\.]+$/', $pdf_file));

	$pdf_base = strip_ext($pdf_file);
	remove_images(get_images($pdf_base));

	$pdf_path = "$pdf_dir/$pdf_file";

	if( !is_file($pdf_path) ) { 
		die("file " . escape($pdf_path). " not exists");
	}
	$img_path = "$img_dir/$pdf_base.png";
	$cmd = "convert -density 180x180 \"$pdf_path\" \"$img_path\"";
	$cmd_esc = escape($cmd);
	system($cmd, $rc);
	if( $rc != 0) { 
		echo ("</pre><p>Nepovedlo se spusit '$cmd_esc',<br/>Sorry... zkus:");
		gen_gen_form(false);
		exit(0);
	}
	echo ("hotovo ($cmd_esc).</br>");
	echo ("Pokračovaní <a href='?pdf=". escape($pdf_file) . "'>zde</a>\n");
	die("");

}

function regen2_pdf_img()
{
	global $pdf_file;
	global $pdf_dir;
	global $img_dir;

	get_pdf_file_post();
	echo "<pre>Generuji obrázky, čekej...\n";
	flush();
	assert(preg_match('/^[-a-z_A-Z0-9\.]+$/', $pdf_file));

	$pdf_base = strip_ext($pdf_file);
	remove_images(get_images($pdf_base));

	$pdf_path = "$pdf_dir/$pdf_file";

	if( !is_file($pdf_path) ) { 
		die("file " . escape($pdf_path). " not exists");
	}
	$pageno = 0;
	while(true) { 
		$img_path = "$img_dir/$pdf_base-$pageno.png";
		$pdf_path_page = "${pdf_path}[$pageno]";
		$cmd = "convert -density 180x180 \"$pdf_path_page\" \"$img_path\"";
		$cmd_esc = escape($cmd);
		ee("$img_path\n");
		flush();
		system($cmd, $rc);
		if( $rc != 0) { 
			echo ("asi hotovo ($cmd_esc).</br>");
			echo ("Pokračovaní <a href='?pdf=". escape($pdf_file) . "'>zde</a>\n");
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
			die("wrong img name: " . escape($img));
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
		print_r($db->errorInfo());
		die("cannt del from db. '" . escape($sql). "'");
	}
}

// set correction status
function do_done($done)
{

	global $db;
	$new_status = $done ? 'DONE' : 'NONE';
	$id = substr(get_post('id'), 2);
	$sql = "UPDATE opravy SET status = " . $db->quote($new_status) . "  WHERE id = " . $db->quote($id);
	$rc = $db->exec($sql);
	//die("do_done($sql)$done.");
	if( $rc == 0 ) {
		print_r($db->errorInfo());
		die("cannt update from db. '" . escape($sql). "'");
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

	setcookie('opraf-au', $au);

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
		print_r($db->errorInfo());
		die("cannt update db '" .escape($sql). "'");
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

	setcookie('opraf-au', $au);

	$sql = "INSERT INTO opravy (pdf, img_id, x, y, txt, au) VALUES (" .
			$db->quote($pdf) . "," .
			$db->quote($img_id) . "," .
			$db->quote($x) . "," .
			$db->quote($y) . "," .
			$db->quote($txt) . "," .
			$db->quote($au) . ")";

	$rc = $db->exec($sql);
	if( $rc == 0 ) {
		print_r($db->errorInfo());
		die("cannt insert into db '".escape($sql)."'");
	}
}

// rid off .pdf
function strip_ext($file)
{
	return substr($file, 0, strrpos($file, '.')); 
}

// find image files, based on $pdf_base (letak.pdf -> letak-0.png, letak-1.png ...)
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
function gen_images()
{
	#TODO
	global $pdf_file;
	global $img_dir;
	global $img_uri;
	$pdf_base = strip_ext($pdf_file);

	$imgs = get_images($pdf_base);
	if( count($imgs) == 0 ) { 
		echo "Žádný obrázek. Obrázky je nutné vygenerovat.\n";
		gen_gen_form();
		echo "<hr/>\n";
		return;
	}
	check_update($pdf_base, $imgs[0]);

	foreach($imgs as $idx => $img) {
		$img_uri_path = "$img_uri/$img";
		$img_path = escape("$img_dir/$img");
		list($w, $h) = getimagesize($img_path);
		$id = "img-" . escape($idx);
		echo " <div class='imgdiv'><img width='$w' height='$h' onclick='img_click(this,event)' id='$id' src='$img_path'/></div><hr/>\n";
	}
}

// check if .pdf is newer than first img
// if yes: display warning, and regen button
function check_update($pdf_base, $img_first)
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
		echo "Obrázky jsou starší, než původní .pdf. Zkus je přegenerovat.\n";
		gen_gen_form();
		echo "<hr/>\n";
	}
	
}

// escape to html
function escape($txt)
{
	return htmlspecialchars($txt, ENT_QUOTES);
}

function ee($txt) 
{
	echo escape($txt);
}

// generate html corrections section
function gen_opravy()
{
	global $db;
	global $pdf_file;
	global $img_undo;
	global $img_done;
	global $img_dele;
	global $img_edit;
	global $pdf_file_esc;
	echo "<div class='opravy'>\n";
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
		arsort(&$statistika);
		if( count($statistika) > 1 ) {
			echo "Děkujeme opravovatelům: \n";
		}
		else {
			echo "Děkujeme: \n";
		}
		$sep ='';
		foreach($statistika as $au => $count) {
			echo   $sep . escape($au) . "($count)";
			$sep = ",\n";
		}
		echo ".\n<hr>\n";
	}
	usort($opravy, 'opravy_cmp');
	for($i = 0; $i < count($opravy); $i++) {
		$row = $opravy[$i];
		$next_id =  $i + 1< count($opravy) ?  escape('op' .$opravy[$i+1]['id']) : null;
		$id = escape('op' . $row['id']);
		$x  = escape($row['x']);
		$y  = escape($row['y']);
		$img_id = escape($row['img_id']);
		$txt =escape($row['txt']);
		$st =escape($row['status']);
		$au = escape($row['au']);
		$imgs[$img_id][] = array($id, $x, $y);
		if( $st == 'DONE' ) {
			echo "<div onclick='img_click(this,event)' id='$id-pointer' class='pointer-done'></div>\n";
			echo "<div name='$id' id='$id' class='box-done' onmouseover='box_onmouseover(this,1)' onmouseout='box_onmouseout(this,1)' >\n";
		}else {
			echo "<div onclick='img_click(this,event)' id='$id-pointer' class='pointer'></div>\n";
			echo "<div name='$id' id='$id' class='box' onmouseover='box_onmouseover(this,0)' onmouseout='box_onmouseout(this,0)' >\n";
		}
		echo "   <span id='$id-text'>$txt</span>\n";
		echo "   <br/><i>au:$au</i>\n";
		echo " <div class='float-right'>\n";
		echo "   <form  action='#' onsubmit='save_scroll(this)' method='POST'>\n";
		#echo "     <button><a style='text-decoration:none' href='#$id'>#</a></button>\n";
		echo "     <input type='hidden' name='pdf' value='$pdf_file_esc'>\n";
		echo "     <input type='hidden' name='id' value='$id'>\n";
		echo "     <input type='hidden' name='scroll' value='$id'>\n";
		echo "     <button type='submit' name='action' value='del' title='Smaž opravu'><img src='imgs/$img_dele'/></button>\n";
		if( $st == 'DONE' ) {
			echo "     <button type='submit' name='action' value='undone' title='Označ jako neopravené'><img src='imgs/$img_undo'/></button>\n";
		} else {
			echo "     <button type='submit' name='action' value='done' title='Označ jako opravené'><img src='imgs/$img_done'/></button>\n";
		}
		echo "     <button type='button' onclick='box_edit(this);' title='Oprav opravu'>";
		echo                 "<img src='imgs/$img_edit'/></button>\n";
		#echo "     <a href='#$id'><button type='button' title='Link na opravu'><img src='imgs/link.png'/></button></a>";
		echo "     <a href='#$id'><img title='Link na opravu' src='imgs/link.png'/></a>";
		if( $next_id ) { 
			echo "     <a href='#$next_id'><img title='Další oprava' src='imgs/next.png'/></button></a>";
		} else {
			echo "     <img title='Toto je poslední oprava' src='imgs/next-gr.png'/>";
		}
		echo " </form>\n";
		echo "</div>\n";
		echo "</div>\n";
	}
	echo "</div>\n";
	echo "<script>\n";
	foreach($imgs as $img_id => $opravy) { 
		$img_id_esc = escape($img_id);
		echo "place_comments_one_div('$img_id',[\n";
		foreach($opravy as $oprava) {
			$id = escape($oprava[0]);
			$x = escape((int)$oprava[1]);
			$y = escape((int)$oprava[2]);
			echo  "\t['$id', $x, $y],\n";
		}
		echo "\t]);\n";
	}
	echo "</script>\n";


}
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
function gen_scroll()
{
	$scroll = get_get('scroll');
	if( $scroll === null ) { 
		return;

	}
	$scroll_esc = escape((int)$scroll);
	echo "<script>";
	//echo "function onload() { window.scrollTo(0,$scroll_esc);}";
	echo "window.scrollTo(0,$scroll_esc);";
	echo "</script>\n";
}
// gen html documentation
function gen_doc()
{
	global $help_doc;
	echo "<pre>". escape($help_doc) . "</pre>\n";
}

// gen dir list of .pdf files
function gen_ls()
{
	global $pdf_dir;
	$dir = opendir($pdf_dir);
	echo "<br/>\n";
	$files = array();
	while (($file = readdir($dir)) !== false)  {
		$files[] = $file;
	}
	sort($files);
	foreach($files as $file) {
		if( substr($file, -4) != '.pdf' ){ 
			continue;
		}
		echo "<a href='?pdf=" . urlencode($file) . "'>";
		echo escape($file) .  "</a><br/>\n";
	}
}

// generate html form which regenerate .pdf
function gen_gen_form($small_pdf_button = true)
{
	global $pdf_file_esc;
 	echo "<form method='POST'>\n";
    	echo "<input type=hidden name='pdf' value='$pdf_file_esc'/>\n";
	if($small_pdf_button){ 
		echo "<button type='submit' name='action' value='regen'>Generovat obrázky</button>\n";
	}
    	echo "<button type='submit' name='action' value='regen2'>Generovat obrázky z velkých .pdf</button>\n";
  	echo "</form>\n";
}
#######################################################################
# main
if( ! init() ) {
	echo "neco se pdfelalo\n";
	die();
}

$au = isset($_COOKIE['opraf-au'])? $_COOKIE['opraf-au'] : 'anonym';

?>
<html>
<head>
<link rel="stylesheet" type="text/css" media="screen, projection" href="opraf.css" />
<script src="opraf.js"></script>
<title>opraf <? ee($pdf_file) ?></title>
</head>
<body> 

<h1>opraf <? ee($pdf_file) ?></h1>
<i>klikni na chybu, napiš komentář</i>  |
<a href="?action=ls">ls</a> |
<a href="?action=doc">doc</a> |
<hr/>

<div id="commform-div">
<form action='#' onsubmit='save_scroll(this)' id="commform" method="POST">
  <input size="8" name="au" value="<? ee($au); ?>"/>
  <input type=submit value="Oprav!"/>
  <button type="button" onclick="close_commform()">Close</button>
  <br/>
  <textarea onkeypress="textarea_onkey(event);" id="commform-text" cols=40 rows=10 name="txt"></textarea>
  <br/>
  <input type="hidden" size="3" name="pdf" value="<? ee($pdf_file); ?>"/>
  <input type="hidden" size="3" id="commform-x" name="x"/>
  <input type="hidden" size="3" id="commform-y" name="y"/>
  <input type="hidden" size="3" id="commform-img-id" name="img-id"/>
  <input type="hidden" size="3" id="commform-id" name="id"/>
  <input type="hidden" size="3" id="commform-action" name="action"/>
  <input type="hidden" size="3" id="commform-action" name="scroll"/>
</form>
</div>

<?php

$action = get_get('action', 'none');
if( $action == 'doc') {
	gen_doc();
}
elseif( $pdf_file == null || $action == 'ls') {
	gen_ls();
}
else{ 
	gen_images();
	gen_opravy();
	gen_scroll();

}

?>
</body> </html>
