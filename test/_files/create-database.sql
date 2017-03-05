create database autowp_image_test default character set utf8;
GRANT ALL PRIVILEGES ON autowp_image_test.* TO autowp_image_test@localhost IDENTIFIED BY "test";
flush privileges;
\. test/_files/dump.sql

mysqldump -u autowp_image_test -p autowp_image_test --complete-insert --skip-add-locks --hex-blob --default-character-set=utf8 -r dump.sql