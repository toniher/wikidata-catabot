CREATE TABLE modif(
  "page" TEXT,
  "size" INTEGER,
  "modified" DATETIME,
  "batch" DATETIME,
  "class" TEXT,
  PRIMARY KEY( "page", "modified" )
);


CREATE INDEX idx_page ON modif (page);
CREATE INDEX idx_size ON modif (size);
CREATE INDEX idx_key ON modif (page, modified);
CREATE INDEX idx_modified ON modif (modified);
CREATE INDEX idx_batch ON modif (batch);
CREATE INDEX idx_class ON modif (class);
