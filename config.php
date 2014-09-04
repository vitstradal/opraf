<?php
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

