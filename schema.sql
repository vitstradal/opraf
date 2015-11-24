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

CREATE TABLE komentare(
	id INTEGER PRIMARY KEY,
    cas DATETIME DEFAULT CURRENT_TIMESTAMP,
	oprava_id INTEGER,
	text TEXT,
	au TEXT,
	FOREIGN KEY(oprava_id) REFERENCES opravy(id)
);
