<?php

$pdo = new PDO('sqlite:d:/PI_WEB_3A21/var/data.db');

// Insert user
$pdo->exec("INSERT INTO users (id, email, password, first_name, last_name, phone, is_active, created_at, forget_code, forget_code_expires, face_person_id, face_enabled) VALUES (1, 'recruiter@example.com', 'password', 'John', 'Doe', '123456789', 1, datetime('now'), 'code', datetime('now'), 'id', 0)");

// Insert recruiter
$pdo->exec("INSERT INTO recruiter (id, user_id, company_name, company_location, company_description) VALUES (1, 1, 'Example Corp', 'Location', 'Description')");

echo "Recruiter created\n";