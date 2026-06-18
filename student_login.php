<?php
require_once __DIR__ . '/ui.php';
start_session();
redirect(login_url('student'));
