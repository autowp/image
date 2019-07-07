DROP TABLE IF EXISTS formated_image;
DROP TABLE IF EXISTS image;

CREATE TABLE image (
  id SERIAL,
  filepath varchar(255) NOT NULL,
  filesize int NOT NULL,
  width int NOT NULL,
  height int NOT NULL,
  crop_left smallint NOT NULL DEFAULT 0,
  crop_top smallint NOT NULL DEFAULT 0,
  crop_width smallint NOT NULL DEFAULT 0,
  crop_height smallint NOT NULL DEFAULT 0,
  date_add timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dir varchar(255) NOT NULL,
  s3 boolean NOT NULL DEFAULT false,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX ON image (filepath,dir);
CREATE INDEX ON image (dir);

CREATE TABLE formated_image (
  image_id SERIAL,
  format varchar(255) NOT NULL,
  status int NOT NULL DEFAULT 0,
  formated_image_id int NULL DEFAULT NULL REFERENCES image (id),
  PRIMARY KEY (image_id,format)
);

CREATE INDEX ON formated_image (formated_image_id,image_id);

DROP TABLE IF EXISTS image_dir;
CREATE TABLE image_dir (
  dir varchar(255) NOT NULL,
  count int NOT NULL,
  PRIMARY KEY (dir)
);
