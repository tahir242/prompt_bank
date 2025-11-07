<?php
$db = new PDO('sqlite:database/prompts.db');
$db->exec('DELETE FROM registration_attempts');
echo "Rate limit table cleared.\n";
