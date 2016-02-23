CREATE TABLE `image` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `filepath` varchar(255) NOT NULL,
    `filesize` int(10) unsigned NOT NULL,
    `width` int(10) unsigned NOT NULL,
    `height` int(10) unsigned NOT NULL,
    `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dir` varchar(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `filename` (`filepath`,`dir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `formated_image` (
    `image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `format` varchar(255) NOT NULL,
    `formated_image_id` int(10) unsigned NOT NULL,
    PRIMARY KEY (`image_id`, `format`),
    KEY(formated_image_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE `image_dir` (
    `dir` varchar(255) NOT NULL,
    `count` int unsigned NOT NULL,
    PRIMARY KEY (`dir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
