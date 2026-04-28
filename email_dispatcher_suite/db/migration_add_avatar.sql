-- Migration: Add avatar column to users table
-- Run this query in your database to add avatar support

ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL AFTER password_hash;

-- Create avatars directory (should be done manually)
-- Files: /assets/img/avatars/avatar1.svg through avatar6.svg
