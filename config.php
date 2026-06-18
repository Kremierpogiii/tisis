<?php
declare(strict_types=1);

// Update these to match your phpMyAdmin database.
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'icct_queue_thesis';

// Base URL used for QR links (adjust port if needed, e.g. http://localhost:8080)
$BASE_URL = 'http://localhost/icct-queue-thesis';

// SMS is "simulated" by default (stored in DB). You can later wire Semaphore/Twilio.
$SMS_MODE = 'simulated'; // simulated | semaphore | twilio

// How long a called ticket stays on the public Now Serving display before auto-advancing.
$DISPLAY_SERVING_MINUTES = 5;

