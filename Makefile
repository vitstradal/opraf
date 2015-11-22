init:
	sqlite3 < schema.sql db/opraf.db
	chmod o+w db/ db/opraf.db png/
