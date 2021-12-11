CREATE DATABASE IF NOT EXISTS phalcon CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
create table articles
(
    id int auto_increment,
    title varchar(255) null,
    PRIMARY KEY (`id`)
);
