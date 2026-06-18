<?php
require_once __DIR__ . '/lib.php';
start_session();
session_destroy();
redirect('/icct-queue-thesis/');

