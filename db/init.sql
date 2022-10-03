-- ---
-- Globals
-- ---
-- SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

-- ---
-- Table 'User'
-- ---
DROP TABLE IF EXISTS `User`;
CREATE TABLE `User` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `password` VARCHAR(200) NOT NULL,
  PRIMARY KEY (`id`)
);

-- ---
-- Table 'Content'
-- ---
DROP TABLE IF EXISTS `Content`;
CREATE TABLE `Content` (
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`id`)
);

-- ---
-- Table 'RefreshTokens'
-- ---
DROP TABLE IF EXISTS `RefreshTokens`;
CREATE TABLE `RefreshTokens` (
  `id` INTEGER(10) AUTO_INCREMENT,
  `userId` INTEGER(10) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
);