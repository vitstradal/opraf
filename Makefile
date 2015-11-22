init:
	sqlite3 < schema.sql db/opraf.db
	mkdir -p png
	chmod o+w db/ db/opraf.db png/
