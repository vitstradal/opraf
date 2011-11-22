<?
require 'config.php';

function get_help_doc()
{
	return  <<<EOL
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
		ee("file ($pdf_path)  not exists");
		die('');
	}
	$img_path = "$img_dir/$pdf_base.png";
	$cmd = "convert -density 180x180 \"$pdf_path\" \"$img_path\"";
	if( $rc != 0) { 
		render_regen_form($pdf_file, "Nepovedlo se spusit '$cmd',Sorry... zkus:", false);
		exit(0);
	}
	render_regen_pdf_img_done($cmd, $pdf_file);
	die('');

}
function render_regen_pdf_img_done($cmd, $pdf_file)
{?>
	
	hotovo (<?ee($cmd)?></br>
	Pokračovaní <a href='?pdf=<?ee($pdf_file)?>'>zde</a>.
<?}

function render_regen_pdf_img_init()
{?>
	<pre>Generuji obrázky, čekej...
<?}

function regen2_pdf_img()
{
	global $pdf_file;
	global $pdf_dir;
	global $img_dir;

	get_pdf_file_post();
	render_regen_pdf_img_init();
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
		$cmd = "convert -density 180x180 \"$pdf_path_page\" \"$img_path\"";
		ee("$img_path\n");
		flush();
		system($cmd, $rc);
		if( $rc != 0) { 
			render_regen_pdf_img_done($cmd, $pdf_file);
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
		render_regen_form($pdf_file, 'Žádný obrázek. Obrázky je nutné vygenerovat');
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
}
function render_image($img_path, $id, $w, $h)
{?>
	 <div class='imgdiv'><img width=<?eea($w)?> height=<?eea($h)?> onclick='img_click(this,event)' id=<?eea($id)?> src=<?eea($img_path)?>/></div><hr/>
<?}

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
 * b) thruu ee() eea() and ejs() 
 */

// escape to html
function escape($txt)
{
	return htmlspecialchars($txt, ENT_QUOTES);
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
// echo html-escaped attribute
function eea($txt, $id = null) 
{
	if( $id !== null ) { 
		echo "'" . escape($txt[$id]) . "'";
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
		arsort(&$statistika);

		$msg =  "Děkujeme opravovatelům:";

		if( count($statistika) == 1 ) {
			$msg =  "Děkujeme:";
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
	<? foreach($imgs as $img_id => $opravy): ?> 
	  place_comments_one_div(<?ejs($img_id)?>, <?ejs($opravy)?>);
	<? endforeach ?>
	</script>
<?}

function render_statistika($pre_msg, $text)
{?>
	<?ee($pre_msg)?><?ee($msg)?>
	<hr>
<?}

function render_oprava($pdf_file, $id, $next_id, $pdf_file, $row, $icons)
{?>
	<? if( $row['status'] == 'DONE' ) : ?>
	<div onclick='img_click(this,event)' id='<?ee($id)?>-pointer' class='pointer-done'></div>
	<div name=<?eea($id)?> id=<?eea($id)?> class='box-done' onmouseover='box_onmouseover(this,1)' onmouseout='box_onmouseout(this,1)' >
	<? else: ?>
	<div onclick='img_click(this,event)' id='<?ee($id)?>-pointer' class='pointer'></div>
	<div name=<?eea($id)?> id=<?eea($id)?> class='box' onmouseover='box_onmouseover(this,0)' onmouseout='box_onmouseout(this,0)' >
	<? endif ?> 

	<span id='<?ee($id)?>-text'><?ee($row, 'txt')?></span>
	<br/><i>au:<?ee($row, 'au')?></i>
	<div class='float-right'>
	<form  action='#' onsubmit='save_scroll(this)' method='POST'>
	<input type='hidden' name='pdf' value=<?eea($pdf_file)?>>
	<input type='hidden' name='id' value=<?eea($id)?>>
	<input type='hidden' name='scroll'>
	<button type='submit' name='action' value='del' title='Smaž opravu'><img src=<?eea($icons,'dele')?>/></button>

	<? if( $row['status'] == 'DONE' ) : ?>
	<button type='submit' name='action' value='undone' title='Označ jako neopravené'><img src=<?eea($icons,'undo')?>/></button>
	<? else: ?>
	<button type='submit' name='action' value='done' title='Označ jako opravené'><img src=<?eea($icons,'done')?>/></button>
	<? endif ?> 

	<button type='button' onclick='box_edit(this);' title='Oprav opravu'><img src=<?eea($icons, 'edit')?>/></button>
	<a href='#<?ee($id)?>'><button type='button' title='Link na opravu'><img src=<?eea($icons, 'link')?>/></button></a>
	<? if( $next_id ) : ?>
	  <a href='#<?ee($next_id)?>'><img title='Další oprava' src=<?eea($icons, 'next')?>/></button></a>
	<? else: ?>
	   <img title='Toto je poslední oprava' src=<?eea($icons, 'nextgr')?>/>
	<? endif ?> 
	</form>
	</div>
	</div>
<?}

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
	<? if( $scroll === null ) { return; } ?>
	<script>
	  window.scrollTo(0,<?ejs((int)$scroll)?>);
	</script>

<?}

// gen html documentation
function render_doc()
{?>
	<pre><? ee(get_help_doc()) ?></pre>
<?}


function render_ls_files()
{
	global $pdf_dir;
	$dir = opendir($pdf_dir);
	$files = array();
	while (($file = readdir($dir)) !== false)  {
		if( substr($file, -4) != '.pdf' ){ 
			continue;
		}
		$files[] = $file;
	}
	sort($files);
	return $files;
}

// gen dir list of .pdf files
function render_ls()
{?>
	<? foreach(render_ls_files()  as $file): ?>
	  <a href='?pdf=<? ee($file) ?>'><?ee($file)?></a><br/>
	<? endforeach  ?>
<?}

// generate html form which regenerate .pdf
function render_regen_form($pdf_file, $text, $small_pdf_button = true)
{?>
	<? ee($text)?>
 	<form method='POST'>
    	<input type=hidden name='pdf' value=<?eea($pdf_file)?>/>
	<?if ($small_pdf_button): ?>  
		<button type='submit' name='action' value='regen'>Generovat obrázky</button>
	<?endif?>
    	<button type='submit' name='action' value='regen2'>Generovat obrázky z velkých .pdf</button>
  	</form>
<?}
#######################################################################
# main
if( ! init() ) {
	die("neco se pdfelalo\n");
}

$au = isset($_COOKIE['opraf-au'])? $_COOKIE['opraf-au'] : 'anonym';

render_html($pdf_file, $au);

function render_html($pdf_file, $au)
{?><html>
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
	  <button type="button" onclick="close_commform()">Zavřít</button>
	  <br/>
	  <textarea onkeypress="textarea_onkey(event);" id="commform-text" cols=40 rows=10 name="txt"></textarea>
	  <br/>
	  <input type="hidden" size="3" name="pdf" value=<? eea($pdf_file); ?>/>
	  <input type="hidden" size="3" id="commform-x" name="x"/>
	  <input type="hidden" size="3" id="commform-y" name="y"/>
	  <input type="hidden" size="3" id="commform-img-id" name="img-id"/>
	  <input type="hidden" size="3" id="commform-id" name="id"/>
	  <input type="hidden" size="3" id="commform-action" name="action"/>
	  <input type="hidden" size="3" id="commform-action" name="scroll"/>
	</form>
	</div>
	<? render_content() ?>
</body> </html>
<?}

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
